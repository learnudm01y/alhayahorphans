# -*- coding: utf-8 -*-
"""Website admin edit test: login → change sponsorship status (real HTTP)."""
import http.cookiejar
import json
import re
import sys
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

BASE = 'http://127.0.0.1/alhayahorphans/public'
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))


def get(path):
    with opener.open(BASE + path, timeout=30) as r:
        return r.status, r.read().decode('utf-8', 'replace'), r.geturl()


def post(path, data, allow_redirect=True):
    body = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=body, method='POST')
    req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    if not allow_redirect:
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *a, **k):
                return None
        op2 = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(cj), NoRedirect)
        try:
            with op2.open(req, timeout=30) as r:
                return r.status, r.read().decode('utf-8', 'replace'), dict(r.headers)
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers)
    with opener.open(req, timeout=30) as r:
        return r.status, r.read().decode('utf-8', 'replace'), r.geturl()


def token_of(html):
    m = re.search(r'name="_token"\s+value="([^"]+)"', html)
    if not m:
        m = re.search(r'value="([^"]+)"\s+name="_token"', html)
    if not m:
        m = re.search(r'name=\'_token\'\s+value=\'([^\']+)\'', html)
    return m.group(1) if m else None


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else 'status'
    sp_id = sys.argv[2] if len(sys.argv) > 2 else '1294'
    new_status = sys.argv[3] if len(sys.argv) > 3 else '3'

    # 1. login page → csrf
    st, html, _ = get('/login')
    tok = token_of(html)
    print(f'GET /login => {st}, csrf={bool(tok)}')

    # 2. login (Breeze)
    st, body, url = post('/login', {
        'email': 'admin@gmail.com', 'password': 'password', '_token': tok,
    }, allow_redirect=False)
    loc = None
    for k, v in body and {} or {}:
        pass
    print(f'POST /login => {st}')
    if st in (302, 303):
        pass  # ok
    else:
        # maybe failed login page
        print('LOGIN BODY HEAD:', body[:400])
        return 1

    # 3. fresh csrf from an authed page
    st, html, url = get('/admin')
    print(f'GET /admin => {st} final={url}')
    tok2 = token_of(html)
    if not tok2:
        st, html, url = get(f'/admin/sponsorships/{sp_id}/edit')
        tok2 = token_of(html)
        print(f'GET edit page => {st}, csrf={bool(tok2)}')
    if not tok2:
        print('NO CSRF after login — aborting')
        return 2

    # 4. change status through the real admin endpoint
    st, body, url = post(f'/admin/sponsorships/{sp_id}/update-status', {
        'sponsorship_status_id': new_status, '_token': tok2,
    }, allow_redirect=False)
    print(f'POST update-status({sp_id} -> {new_status}) => {st}')
    print('RESP:', body[:300])
    return 0


if __name__ == '__main__':
    sys.exit(main())
