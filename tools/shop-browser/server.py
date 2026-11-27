"""Single-user, read-only shop inspection browser behind an authenticated PHP gateway."""
import asyncio
import contextlib
import hmac
import ipaddress
import os
import secrets
import socket
import time
from pathlib import Path
from urllib.parse import urlsplit, parse_qs

from aiohttp import web
from playwright.async_api import async_playwright, TimeoutError as BrowserTimeout

ROOT = Path(os.environ.get('SHOP_BROWSER_STATE', '/var/lib/doujin-shop-browser'))
SOCKET = os.environ.get('SHOP_BROWSER_SOCKET', '/run/doujin-shop-browser/agent.sock')
WIDTH, HEIGHT = 1200, 800
TTL = 900
HOSTS = {
    'melon': {'www.melonbooks.co.jp', 'melonbooks.co.jp'},
    'suruga': {'www.suruga-ya.jp', 'suruga-ya.jp'},
    'mandarake': {'order.mandarake.co.jp'},
}


def product_site(url):
    p = urlsplit(url)
    if p.scheme != 'https' or p.username or p.password or p.port:
        raise ValueError('Unsupported URL')
    q = parse_qs(p.query)
    if p.hostname in HOSTS['melon'] and p.path == '/detail/detail.php' and q.get('product_id', [''])[0].isdigit():
        return 'melon'
    if p.hostname in HOSTS['suruga'] and p.path.startswith('/product/detail/') and p.path.removeprefix('/product/detail/').isalnum():
        return 'suruga'
    if p.hostname in HOSTS['mandarake'] and p.path == '/order/detailPage/item' and q.get('itemCode', [''])[0].isdigit():
        return 'mandarake'
    raise ValueError('Unsupported product URL')


class Agent:
    def __init__(self):
        self.context = self.page = self.playwright = None
        self.owner = self.site = self.source_url = None
        self.touched = 0
        self.lock = asyncio.Lock()
        self.frame_time = 0
        self.frame = None
        self.generation = ''
        self.diagnostics = []

    def record(self, kind, url, detail):
        # Hostnames and fixed reason/status codes only; never retain URL tokens.
        self.diagnostics.append({'kind': kind, 'host': urlsplit(url).hostname, 'detail': str(detail)[:80]})
        self.diagnostics = self.diagnostics[-30:]

    async def close(self):
        if self.context:
            await self.context.close()
        self.context = self.page = self.owner = self.frame = None

    def authorized(self, token):
        return isinstance(token, str) and self.owner and hmac.compare_digest(self.owner, token) and time.monotonic() - self.touched < TTL

    async def route(self, route):
        request = route.request
        p = urlsplit(request.url)
        if p.scheme not in ('http', 'https') or p.username or p.password or (p.port and p.port not in (80, 443)):
            self.record('blocked', request.url, 'scheme_or_port')
            return await route.abort()
        # Keep the main frame in this platform. Third-party assets/challenge frames still work.
        if request.is_navigation_request() and request.frame == self.page.main_frame and p.hostname not in HOSTS[self.site]:
            self.record('blocked', request.url, 'navigation_host')
            return await route.abort()
        try:
            addresses = await asyncio.get_running_loop().getaddrinfo(p.hostname, p.port or 443, type=socket.SOCK_STREAM)
            if not addresses or any(not ipaddress.ip_address(item[4][0]).is_global for item in addresses):
                self.record('blocked', request.url, 'non_public_address')
                return await route.abort()
        except (OSError, ValueError):
            self.record('blocked', request.url, 'dns_failure')
            return await route.abort()
        # Kernel IP filtering additionally prevents private/metadata traffic after DNS changes.
        await route.continue_()

    async def start(self, url, token):
        site = product_site(url)
        if self.context and time.monotonic() - self.touched >= TTL:
            await self.close()
        if self.owner and not self.authorized(token):
            raise web.HTTPConflict(text='Browser is in use by another session')
        owner = self.owner or secrets.token_urlsafe(32)
        if self.context and self.site != site:
            await self.close()
        self.site, self.owner = site, owner
        if not self.context:
            self.context = await self.playwright.chromium.launch_persistent_context(
                str(ROOT / site), headless=False, chromium_sandbox=True,
                viewport={'width': WIDTH, 'height': HEIGHT}, locale='ja-JP',
                accept_downloads=False, service_workers='block',
                args=['--disable-dev-shm-usage'],
            )
            self.page = self.context.pages[0] if self.context.pages else await self.context.new_page()
            for page in self.context.pages:
                if page != self.page:
                    await page.close()
            self.context.on('page', lambda page: asyncio.create_task(page.close()))
            self.page.on('dialog', lambda dialog: asyncio.create_task(dialog.dismiss()))
            self.page.on('requestfailed', lambda request: self.record('failed', request.url, request.failure or 'unknown'))
            self.page.on('response', lambda response: self.record('http', response.url, response.status) if response.status >= 400 else None)
            await self.context.route('**/*', self.route)
            # Websocket requests cannot contact internal services either (kernel filtering).
            self.context.set_default_timeout(5000)
        self.touched = time.monotonic()
        self.source_url, self.frame = url, None
        self.diagnostics = []
        self.generation = secrets.token_hex(8)
        warning = None
        try:
            await self.page.goto(url, wait_until='domcontentloaded', timeout=25000)
        except Exception:
            warning = 'Navigation did not finish; inspect the browser before continuing.'
        return {'token': self.owner, 'generation': self.generation, 'url': self.page.url, 'warning': warning}

    async def handle(self, request):
        data = await request.json()
        async with self.lock:
            action = data.get('action')
            if action == 'start':
                try:
                    return web.json_response(await self.start(data['url'], data.get('token')))
                except ValueError:
                    raise web.HTTPBadRequest(text='Unsupported product URL')
                except Exception:
                    if not self.context:
                        self.owner = None
                    raise
            if not self.authorized(data.get('token')):
                raise web.HTTPUnauthorized(text='Browser session expired')
            if action == 'close':
                await self.close()
                return web.json_response({'closed': True})
            if data.get('generation') != self.generation:
                raise web.HTTPConflict(text='Browser selection changed; reopen the product')
            # Frame polling does not extend the idle lease. Human interaction or reading does.
            if action != 'frame':
                self.touched = time.monotonic()
            if action == 'frame':
                if self.frame is None or time.monotonic() - self.frame_time > 0.8:
                    self.frame = await self.page.screenshot(type='jpeg', quality=65, timeout=5000)
                    self.frame_time = time.monotonic()
                return web.Response(body=self.frame, content_type='image/jpeg')
            if action == 'input':
                kind = data.get('kind')
                if kind in ('click', 'down', 'move', 'up'):
                    x, y = float(data.get('x', 0)), float(data.get('y', 0))
                    if not 0 <= x < WIDTH or not 0 <= y < HEIGHT:
                        raise web.HTTPBadRequest()
                    await self.page.mouse.move(x, y)
                    if kind == 'click': await self.page.mouse.click(x, y)
                    if kind == 'down': await self.page.mouse.down()
                    if kind == 'up': await self.page.mouse.up()
                elif kind == 'wheel':
                    await self.page.mouse.wheel(0, max(-800, min(800, float(data.get('delta', 0)))))
                elif kind == 'text':
                    await self.page.keyboard.insert_text(str(data.get('text', ''))[:2000])
                elif kind == 'key' and data.get('key') in ('Enter','Tab','Shift+Tab','Backspace','Delete','Escape','ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Control+A','Space'):
                    await self.page.keyboard.press(data['key'])
                else:
                    raise web.HTTPBadRequest(text='Unsupported input')
                self.frame = None
                return web.json_response({'ok': True})
            if action == 'read':
                html = await self.page.content()
                if len(html.encode('utf-8')) > 4 * 1024 * 1024:
                    raise web.HTTPRequestEntityTooLarge(max_size=4*1024*1024, actual_size=len(html))
                cookies = await self.context.cookies([self.source_url])
                diagnostics = {
                    'clearance_present': any(cookie['name'] == 'cf_clearance' for cookie in cookies),
                    'events': self.diagnostics,
                }
                return web.json_response({'html': html, 'url': self.page.url, 'source_url': self.source_url, 'diagnostics': diagnostics})
            raise web.HTTPBadRequest(text='Unsupported action')


@web.middleware
async def errors(request, handler):
    try:
        response = await handler(request)
    except web.HTTPException as error:
        response = web.json_response({'error': error.text}, status=error.status)
    except Exception:
        # Never log page HTML, cookies, passwords, or interaction payloads.
        response = web.json_response({'error': 'Browser operation failed'}, status=503)
    response.headers['Cache-Control'] = 'no-store'
    return response


async def main():
    ROOT.mkdir(parents=True, exist_ok=True)
    os.umask(0o007)
    agent = Agent()
    agent.playwright = await async_playwright().start()
    app = web.Application(middlewares=[errors], client_max_size=16384)
    app.router.add_post('/command', agent.handle)
    runner = web.AppRunner(app, access_log=None)
    await runner.setup()
    with contextlib.suppress(FileNotFoundError):
        os.unlink(SOCKET)
    await web.UnixSite(runner, SOCKET).start()
    os.chmod(SOCKET, 0o660)
    try:
        while True:
            await asyncio.sleep(20)
            async with agent.lock:
                if agent.context and time.monotonic() - agent.touched >= TTL:
                    await agent.close()
    finally:
        await agent.close()
        await agent.playwright.stop()
        await runner.cleanup()


if __name__ == '__main__':
    asyncio.run(main())
