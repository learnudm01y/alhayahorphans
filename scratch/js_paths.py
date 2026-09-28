import re, io, sys, os

ROOT = r'C:\xampp\htdocs\alhayahorphans'

def scan(path, label):
    p = os.path.join(ROOT, path)
    if not os.path.exists(p):
        print('MISSING:', path)
        return
    s = io.open(p, encoding='utf-8', errors='replace').read()
    print('=' * 8, label, path, 'len=%d' % len(s))
    # endpoint strings
    hits = set()
    for pat in [r'["\'`](/?api/[^"\'`]+)', r'["\'`](/mobile/[^"\'`]+)', r'["\'`](/sync/[^"\'`]+)', r'["\'`](/uploads/[^"\'`]+)']:
        for m in re.findall(pat, s):
            hits.add(m)
    for x in sorted(hits):
        print('  PATH:', x)
    # base urls
    for m in re.finditer(r'.{0,70}(API_URL|baseUrl|BASE_URL).{0,70}', s):
        t = m.group(0).replace('\n', ' ')
        print('  CTX:', t.strip()[:150])

targets = [
    ('android-v4\\app\\src\\main\\assets\\public\\js\\api-service.js', 'api-service'),
    ('android-v4\\app\\src\\main\\assets\\public\\js\\sync-service-real.js', 'sync-service-real'),
    ('android-v4\\app\\src\\main\\assets\\public\\sync-service.js', 'sync-service-root'),
    ('android-v4\\app\\src\\main\\assets\\public\\js\\sync-service.js', 'js/sync-service'),
    ('android-v4\\app\\src\\main\\assets\\public\\js\\photo-store.js', 'photo-store'),
    ('android-v4\\app\\src\\main\\assets\\public\\js\\config.js', 'js/config'),
    ('android-v4\\app\\src\\main\\assets\\public\\config.js', 'root/config'),
]
for t in targets:
    scan(t[0], t[1])
