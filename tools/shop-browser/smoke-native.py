"""Transport/lifecycle checks only; does not click or solve a challenge."""
import asyncio
from aiohttp import ClientSession, UnixConnector


async def main():
    async with ClientSession(connector=UnixConnector(path='/run/doujin-shop-browser/agent.sock')) as client:
        async def call(data):
            response = await client.post('http://localhost/command', json=data)
            return response.status, await response.read()
        import json
        code, _ = await call({'action':'frame', 'token':'invalid'})
        assert code == 401
        code, _ = await call({'action':'start','url':'http://169.254.169.254/latest/meta-data/'})
        assert code == 400
        code, body = await call({'action':'start','url':'https://www.suruga-ya.jp/product/detail/ZHOA91774'})
        print('start', code, flush=True)
        assert code == 200, body
        state = json.loads(body)
        assert state['mode'] == 'native' and state['read_supported'] is True
        base = {key:state[key] for key in ('token','generation')}
        try:
            code, _ = await call({'action':'start','url':'https://www.suruga-ya.jp/product/detail/ZHOA91774','token':'other'})
            assert code == 409
            code, frame = await call({'action':'frame', **base})
            print('frame', code, len(frame), flush=True)
            assert code == 200 and frame.startswith(b'\xff\xd8')
            code, _ = await call({'action':'read', **base})
            assert code == 200
            code, _ = await call({'action':'input', 'kind':'move','x':1190,'y':790, **base})
            assert code == 200
            code, _ = await call({'action':'input', 'kind':'move','x':-1,'y':0, **base})
            assert code == 400
            print('Native browser security/lifecycle checks passed', flush=True)
        finally:
            code, _ = await call({'action':'close', **base})
            assert code == 200


asyncio.run(main())
