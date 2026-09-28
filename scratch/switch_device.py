# -*- coding: utf-8 -*-
"""Switch device: local server -> production (API base + token, Java side + WebView)."""
import json
import os
import sys
import urllib.request
import websocket

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

PROD = 'https://alhayahorphans.org'
tok_path = os.path.join(os.environ['TEMP'], 'opencode', 'prod_token.txt')
TOKEN = open(tok_path).read().strip()

EXPIRES = '2026-10-26 09:13:13'  # from production login response
USER = json.dumps({
    'id': 3, 'name': 'Admin', 'email': 'admin@gmail.com', 'role': 'admin',
}, ensure_ascii=False)

STEPS = [
    ("java setApiUrl",
     "Capacitor.Plugins.BackgroundSync.setApiUrl({url:'%s'}).then(r=>JSON.stringify(r))" % PROD),
    ("java setAuthToken",
     "Capacitor.Plugins.BackgroundSync.setAuthToken({token:%s}).then(r=>JSON.stringify(r))" % json.dumps(TOKEN)),
    ("localStorage base",
     "localStorage.setItem('api_base_url', %s); localStorage.getItem('api_base_url')" % json.dumps(PROD)),
    ("localStorage token+user",
     "localStorage.setItem('auth_token', %s); localStorage.setItem('user_data', %s); localStorage.setItem('token_expires', %s); "
     "(localStorage.getItem('auth_token')||'').slice(0,10)+' / '+JSON.parse(localStorage.getItem('user_data')).email"
     % (json.dumps(TOKEN), json.dumps(USER), json.dumps(EXPIRES))),
]


def cdp(expr, timeout=20):
    pages = json.loads(urllib.request.urlopen('http://127.0.0.1:9228/json', timeout=5).read())
    page = next((p for p in pages if p.get('type') == 'page'), None)
    ws = websocket.create_connection(page['webSocketDebuggerUrl'], suppress_origin=True, timeout=timeout)
    ws.send(json.dumps({'id': 1, 'method': 'Runtime.evaluate',
                        'params': {'expression': expr, 'awaitPromise': True, 'returnByValue': True}}))
    while True:
        msg = json.loads(ws.recv())
        if msg.get('id') == 1:
            res = msg.get('result', {})
            ws.close()
            if 'exceptionDetails' in res:
                return 'EXC: ' + json.dumps(res['exceptionDetails'])[:500]
            v = res.get('result', {}).get('value')
            return v if isinstance(v, str) else json.dumps(v, ensure_ascii=False)


for name, expr in STEPS:
    print(f'{name}: {cdp(expr)}')
