"""Open known product pages, inspect bounded snapshots, never click confirmations."""
import asyncio
import json
from aiohttp import ClientSession, UnixConnector


async def main():
    async with ClientSession(connector=UnixConnector(path='/run/doujin-shop-browser/agent.sock')) as client:
        async def call(payload):
            response = await client.post('http://localhost/command', json=payload)
            return response.status, await response.json()
        token = None
        try:
            for site, url in [
                ('suruga', 'https://www.suruga-ya.jp/product/detail/ZHOA91774'),
                ('melon', 'https://www.melonbooks.co.jp/detail/detail.php?product_id=3867175'),
                ('mandarake', 'https://order.mandarake.co.jp/order/detailPage/item?itemCode=1132124837'),
            ]:
                code, state = await call({'action':'start','url':url,'token':token})
                print(site, 'start', code, flush=True)
                if code != 200: continue
                token = state['token']
                base = {key:state[key] for key in ('token','generation')}
                for _ in range(6):
                    await asyncio.sleep(5)
                    code, data = await call({'action':'read', **base})
                    if data.get('snapshot', {}).get('prices'): break
                snapshot = data.get('snapshot', {})
                print(site, 'read', code, 'snapshot', bool(snapshot), 'challenge', snapshot.get('challenge'), flush=True)
                print(site, 'counts', {key:len(snapshot.get(key, [])) for key in ('headings','prices','stock','purchase','structured')}, flush=True)
                print(site, 'price/stock candidates', json.dumps({key:snapshot.get(key, [])[:8] for key in ('prices','stock')}, ensure_ascii=False), flush=True)
        finally:
            if token: await call({'action':'close','token':token})


asyncio.run(main())
