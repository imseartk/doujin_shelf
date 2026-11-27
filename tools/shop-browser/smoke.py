"""Run locally on the server against the private socket; never solve a challenge."""
import asyncio
from aiohttp import ClientSession, UnixConnector


async def main():
    async with ClientSession(connector=UnixConnector(path='/run/doujin-shop-browser/agent.sock')) as client:
        async def call(payload):
            response = await client.post('http://localhost/command', json=payload)
            return response.status, await response.read()
        code, _ = await call({'action':'read','token':'invalid'})
        assert code == 401
        code, _ = await call({'action':'start','url':'http://169.254.169.254/latest/meta-data/'})
        assert code == 400
        token = None
        for label, url in [
            ('Melonbooks', 'https://www.melonbooks.co.jp/detail/detail.php?product_id=3062038'),
            ('Surugaya', 'https://www.suruga-ya.jp/product/detail/646009493?tenpo_cd=400379'),
            ('Mandarake', 'https://order.mandarake.co.jp/order/detailPage/item?itemCode=1132124837'),
        ]:
            import json
            code, body = await call({'action':'start','url':url,'token':token})
            if code != 200:
                print(label, 'START FAILED', code, body.decode()); continue
            state = json.loads(body)
            token = state['token']
            base = {'token':token, 'generation':state['generation']}
            await asyncio.sleep(3)
            code, frame = await call({'action':'frame', **base})
            print(label, 'frame', code, len(frame), 'JPEG', frame[:2] == b'\xff\xd8')
            code, body = await call({'action':'read', **base})
            data = json.loads(body)
            from urllib.parse import urlsplit
            print(label, 'read', code, 'host', urlsplit(data.get('url', '')).hostname, 'HTML bytes',len(data.get('html','')))
            print(label, 'diagnostics', json.dumps(data.get('diagnostics', {})))
            # Keyboard/scroll round trip without activating any links or confirmations.
            code, _ = await call({'action':'input', 'kind':'wheel', 'delta':0, **base})
            print(label, 'input', code)
        if token:
            code, _ = await call({'action':'close','token':token})
            print('close', code)

asyncio.run(main())
