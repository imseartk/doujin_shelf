"""Human-operated Chrome desktop. No WebDriver, CDP, DOM access or challenge solver."""
import asyncio
import contextlib
import math
import json
import os
import secrets
import signal
import tempfile
import time
from pathlib import Path

from aiohttp import web
from server import Agent, ROOT, SOCKET, WIDTH, HEIGHT, TTL, errors, product_site
from native_host import identity


class NativeAgent(Agent):
    def __init__(self):
        super().__init__()
        self.process = None

    async def command(self, *args, stdin=None):
        proc = await asyncio.create_subprocess_exec(
            *args, stdin=asyncio.subprocess.PIPE if stdin is not None else asyncio.subprocess.DEVNULL,
            stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.DEVNULL)
        try:
            output, _ = await asyncio.wait_for(proc.communicate(stdin), 8)
        except asyncio.TimeoutError:
            proc.kill()
            await proc.wait()
            raise
        if proc.returncode:
            raise RuntimeError('Desktop operation failed')
        return output

    async def close(self):
        if self.process:
            with contextlib.suppress(ProcessLookupError):
                os.killpg(self.process.pid, signal.SIGTERM)
            try:
                await asyncio.wait_for(self.process.wait(), 5)
            except asyncio.TimeoutError:
                with contextlib.suppress(ProcessLookupError):
                    os.killpg(self.process.pid, signal.SIGKILL)
                await self.process.wait()
        self.process = self.context = self.owner = self.frame = None

    async def start(self, url, token):
        site = product_site(url)
        if self.owner and time.monotonic() - self.touched >= TTL:
            await self.close()
        if self.owner and not self.authorized(token):
            raise web.HTTPConflict(text='Browser is in use by another session')
        owner = self.owner or secrets.token_urlsafe(32)
        await self.close()
        profile = ROOT / ('native-' + site)
        profile.mkdir(mode=0o700, exist_ok=True)
        self.process = await asyncio.create_subprocess_exec(
            '/usr/bin/google-chrome-stable', '--user-data-dir=' + str(profile),
            '--no-first-run', '--no-default-browser-check', '--disable-dev-shm-usage',
            '--window-size=1200,800', '--window-position=0,0', '--app=' + url,
            stdin=asyncio.subprocess.DEVNULL, stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL, start_new_session=True)
        # Wait for a real visible window, not page content or a verification result.
        try:
            for _ in range(40):
                if self.process.returncode is not None:
                    raise RuntimeError('Chrome exited before opening')
                try:
                    windows = await self.command('xdotool', 'search', '--onlyvisible', '--class', 'Google-chrome')
                    if windows.strip():
                        window = windows.splitlines()[0].decode('ascii')
                        await self.command('xdotool', 'windowmove', window, '0', '0')
                        await self.command('xdotool', 'windowsize', window, str(WIDTH), str(HEIGHT))
                        await self.command('xdotool', 'windowfocus', window)
                        break
                except RuntimeError:
                    pass
                await asyncio.sleep(0.25)
            else:
                raise RuntimeError('Chrome window unavailable')
        except Exception:
            await self.close()
            raise
        self.owner, self.site, self.source_url = owner, site, url
        self.context = True
        self.touched = time.monotonic()
        self.generation = secrets.token_hex(8)
        self.started_at = time.time()
        return {'token': owner, 'generation': self.generation, 'url': url,
                'mode': 'native', 'read_supported': True}

    async def handle(self, request):
        data = await request.json()
        async with self.lock:
            action = data.get('action')
            if action == 'start':
                try:
                    return web.json_response(await self.start(data['url'], data.get('token')))
                except ValueError:
                    raise web.HTTPBadRequest(text='Unsupported product URL')
            if not self.authorized(data.get('token')):
                raise web.HTTPUnauthorized(text='Browser session expired')
            if action == 'close':
                await self.close()
                return web.json_response({'closed': True})
            if data.get('generation') != self.generation:
                raise web.HTTPConflict(text='Browser selection changed')
            if not self.process or self.process.returncode is not None:
                await self.close()
                raise web.HTTPServiceUnavailable(text='Chrome exited')
            if action != 'frame':
                self.touched = time.monotonic()
            if action == 'frame':
                if self.frame is None or time.monotonic() - self.frame_time > 0.8:
                    fd, name = tempfile.mkstemp(suffix='.jpg', dir='/run/doujin-shop-browser')
                    os.close(fd)
                    try:
                        await self.command('scrot', '--overwrite', '--quality', '65', name)
                        self.frame = Path(name).read_bytes()
                    finally:
                        Path(name).unlink(missing_ok=True)
                    self.frame_time = time.monotonic()
                return web.Response(body=self.frame, content_type='image/jpeg')
            if action == 'read':
                path = ROOT / ('dom-' + self.site + '.json')
                try:
                    snapshot = json.loads(path.read_text(encoding='utf-8'))
                except (FileNotFoundError, ValueError):
                    return web.json_response({'dom_pending': True, 'url': self.source_url})
                captured = snapshot.get('captured_at', 0)
                if captured < self.started_at or time.time() - captured > 30:
                    return web.json_response({'dom_pending': True, 'url': self.source_url})
                if identity(snapshot.get('url', '')) != identity(self.source_url):
                    return web.json_response({'dom_pending': True, 'url': self.source_url})
                return web.json_response({'snapshot': snapshot, 'url': snapshot['url']})
            if action != 'input':
                raise web.HTTPBadRequest(text='Unsupported action')
            kind = data.get('kind')
            if kind in ('click', 'down', 'move', 'up'):
                x, y = float(data.get('x', 0)), float(data.get('y', 0))
                if not (0 <= x < WIDTH and 0 <= y < HEIGHT):
                    raise web.HTTPBadRequest(text='Invalid coordinates')
                await self.command('xdotool', 'mousemove', str(int(x)), str(int(y)))
                if kind != 'move':
                    await self.command('xdotool', {'click':'click', 'down':'mousedown', 'up':'mouseup'}[kind], '1')
            elif kind == 'wheel':
                delta = float(data.get('delta', 0))
                if not math.isfinite(delta):
                    raise web.HTTPBadRequest()
                if delta:
                    await self.command('xdotool', 'click', '--repeat', str(min(8, max(1, int(abs(delta)/100)))), '5' if delta > 0 else '4')
            elif kind == 'text':
                await self.command('xdotool', 'type', '--clearmodifiers', '--file', '-', stdin=str(data.get('text', ''))[:2000].encode())
            elif kind == 'key':
                keys = {'Enter':'Return','Tab':'Tab','Shift+Tab':'shift+Tab','Backspace':'BackSpace',
                        'Delete':'Delete','Escape':'Escape','ArrowUp':'Up','ArrowDown':'Down',
                        'ArrowLeft':'Left','ArrowRight':'Right','Control+A':'ctrl+a','Space':'space'}
                if data.get('key') not in keys:
                    raise web.HTTPBadRequest(text='Unsupported key')
                await self.command('xdotool', 'key', '--clearmodifiers', keys[data['key']])
            else:
                raise web.HTTPBadRequest(text='Unsupported input')
            self.frame = None
            return web.json_response({'ok': True})


async def main():
    ROOT.mkdir(parents=True, exist_ok=True)
    os.umask(0o007)
    agent = NativeAgent()
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
        await runner.cleanup()


if __name__ == '__main__':
    asyncio.run(main())
