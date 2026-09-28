import re, io, os

ROOT = r'C:\xampp\htdocs\alhayahorphans'

def scan(path, label):
    p = os.path.join(ROOT, path)
    if not os.path.exists(p):
        print('MISSING:', path)
        return
    s = io.open(p, encoding='utf-8', errors='replace').read()
    print('=' * 8, label, path, 'len=%d' % len(s))
    hits = set()
    for pat in [r'["\'`](/api/[^"\'`]+)', r'["\'`](/mobile/[^"\'`]+)', r'["\'`](/sync/[^"\'`]+)', r'["\'`](/uploads/[^"\'`]+)', r'["\'`](/civil-registry[^"\'`]+)', r'["\'`](/registration[^"\'`]+)']:
        for m in re.findall(pat, s):
            hits.add(m)
    for x in sorted(hits):
        print('  PATH:', x)
    for m in re.finditer(r'.{0,80}(API_URL|baseUrl|BASE_URL|alhayahorphans).{0,80}', s):
        t = m.group(0).replace('\n', ' ')
        print('  CTX:', t.strip()[:170])

targets = [
    ('android-v4\\app\\src\\main\\assets\\public\\assets\\syncMonitor-Bf50IX2L.js', 'syncMonitor bundle'),
    ('android-v4\\app\\src\\main\\assets\\public\\search.html', 'search.html'),
    ('android-v4\\app\\src\\main\\assets\\public\\detail.html', 'detail.html'),
    ('android-v4\\app\\src\\main\\assets\\public\\index.html', 'index.html'),
    ('android-v4\\app\\src\\main\\assets\\public\\photography.html', 'photography.html'),
]
for t in targets:
    scan(t[0], t[1])
