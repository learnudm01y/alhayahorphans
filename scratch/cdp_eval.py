# -*- coding: utf-8 -*-
import json, sys, urllib.request
import websocket

def main(expr, timeout=15):
    pages = json.loads(urllib.request.urlopen('http://127.0.0.1:9228/json', timeout=5).read())
    page = next((p for p in pages if p.get('type') == 'page'), None)
    if not page:
        print('NO PAGE')
        return
    ws = websocket.create_connection(page['webSocketDebuggerUrl'], suppress_origin=True, timeout=timeout)
    ws.send(json.dumps({'id': 1, 'method': 'Runtime.evaluate',
                        'params': {'expression': expr, 'awaitPromise': True, 'returnByValue': True}}))
    while True:
        msg = json.loads(ws.recv())
        if msg.get('id') == 1:
            res = msg.get('result', {})
            if 'exceptionDetails' in res:
                print('EXC:', json.dumps(res['exceptionDetails'])[:800])
            else:
                val = res.get('result', {}).get('value')
                print(json.dumps(val, ensure_ascii=False, indent=1) if not isinstance(val, str) else val)
            break
    ws.close()

if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'location.href')
