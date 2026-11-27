#!/bin/bash
set -euo pipefail
systemctl is-active doujin-ticket-browser doujin-shop-browser
systemctl show doujin-ticket-browser -p User -p MainPID -p IPAddressDeny
/opt/doujin-shop-browser/venv/bin/python - <<'PY'
import asyncio,json,pathlib
import aiohttp
async def main():
    connector=aiohttp.UnixConnector(path='/run/doujin-ticket-browser/agent.sock')
    async with aiohttp.ClientSession(connector=connector) as client:
        async def cmd(data):
            async with client.post('http://localhost/command',json=data) as response:
                return response.status,await response.read()
        for data,expected in [({'action':'read'},400),({'action':'frame'},401),({'action':'start','url':'https://example.com/'},400)]:
            status,_=await cmd(data)
            assert status==expected,(data['action'],status)
        status,body=await cmd({'action':'start','url':'https://ticketpay.jp/'})
        if status==409:
            print('Already in use; did not interrupt user session.')
            return
        assert status==200,(status,body)
        state=json.loads(body)
        auth={k:state[k] for k in ('token','generation')}
        try:
            await asyncio.sleep(6)
            status,frame=await cmd({'action':'frame',**auth})
            assert status==200 and frame.startswith(b'\xff\xd8'),(status,len(frame))
            path=pathlib.Path('/home/artick/shop-browser-stage/ticket-home.jpg')
            path.write_bytes(frame)
            path.chmod(0o644)
            print('Start/frame successful. JPEG bytes:',len(frame))
            assert state['read_supported'] is False
        finally:
            status,_=await cmd({'action':'close',**auth})
            assert status==200,status
        assert not pathlib.Path('/var/lib/doujin-ticket-browser/native-ticketpay').exists()
        print('Close/profile cleanup successful.')
asyncio.run(main())
PY
for path in tools/ticket-browser tools/ticket-browser/frame; do
  curl -sS --max-time 15 -o /dev/null -w "$path anonymous HTTP %{http_code} redirect=%{redirect_url}\n" "https://doujin.artick.tw/$path"
done
curl -sS --max-time 15 -H 'X-Requested-With: XMLHttpRequest' -o /dev/null -w 'Anonymous command HTTP %{http_code}\n' -X POST https://doujin.artick.tw/tools/ticket-browser/command
journalctl -u doujin-ticket-browser -n 8 --no-pager
