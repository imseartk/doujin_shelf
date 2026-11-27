import asyncio
import json
from pathlib import Path
from aiohttp import ClientSession, UnixConnector


async def main():
    async with ClientSession(connector=UnixConnector(path='/run/doujin-shop-browser/agent.sock')) as client:
        async def call(payload):
            response = await client.post('http://localhost/command', json=payload)
            return response.status, await response.json()
        token = None
        try:
            for code in ['ZHOA91774', 'ZHOU27935']:
                status, state = await call({'action':'start', 'url':'https://www.suruga-ya.jp/product/detail/'+code, 'token':token})
                assert status == 200, state
                token=state['token']
                base={key:state[key] for key in ('token','generation')}
                for _ in range(6):
                    await asyncio.sleep(5)
                    _, data=await call({'action':'read', **base})
                    snapshot=data.get('snapshot',{})
                    if snapshot.get('schemaVersion') == 2 and snapshot.get('headings'): break
                # Local private evidence; not published in the web root.
                Path('/var/lib/doujin-shop-browser/case-'+code+'.json').write_text(json.dumps(snapshot,ensure_ascii=False),encoding='utf-8')
                print(code,json.dumps({key:snapshot.get(key) for key in ('schemaVersion','canonical','suruga','structured','challenge')},ensure_ascii=False),flush=True)
        finally:
            if token: await call({'action':'close','token':token})


asyncio.run(main())
