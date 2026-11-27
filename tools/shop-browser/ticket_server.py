"""Isolated human-only TicketPay session; no DOM extraction or saved profile."""
import asyncio
import shutil
from aiohttp import web
import native_server


def ticket_site(url):
    if url != 'https://ticketpay.jp/':
        raise ValueError('Unsupported URL')
    return 'ticketpay'


class TicketAgent(native_server.NativeAgent):
    async def close(self):
        await super().close()
        profile = native_server.ROOT / 'native-ticketpay'
        if native_server.ROOT != native_server.Path('/var/lib/doujin-ticket-browser'):
            raise RuntimeError('Unexpected private profile root')
        if profile.exists():
            shutil.rmtree(profile)

    async def start(self, url, token):
        result = await super().start(url, token)
        result['read_supported'] = False
        return result

    async def handle(self, request):
        data = await request.json()
        if data.get('action') not in ('start', 'input', 'frame', 'close'):
            raise web.HTTPBadRequest(text='Unsupported action')
        return await super().handle(request)


if __name__ == '__main__':
    # Overrides are confined to this dedicated process, not the shop service.
    native_server.product_site = ticket_site
    native_server.NativeAgent = TicketAgent
    asyncio.run(native_server.main())
