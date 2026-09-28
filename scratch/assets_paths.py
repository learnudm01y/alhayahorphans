import re, io, glob, os

ROOT = r'C:\xampp\htdocs\alhayahorphans'
base = ROOT + r'\android-v4\app\src\main\assets\public'
files = []
for ext in ('*.js', '*.html', '*.json'):
    files += glob.glob(base + '\\**\\' + ext, recursive=True)
pats = [
    ('apiMobile', r'["\'`](/api/mobile/[^"\'`\s]+)'),
    ('mobile', r'["\'`](/mobile/[^"\'`\s]+)'),
    ('apiUploads', r'["\'`](/api/uploads/[^"\'`\s]+)'),
    ('uploads', r'["\'`](/uploads/[^"\'`\s]+)'),
    ('apiSync', r'["\'`](/api/sync/[^"\'`\s]+)'),
    ('sync', r'["\'`](/sync/[a-z][^"\'`\s]*)'),
    ('civilApi', r'["\'`](/api/civil-registry/[^"\'`\s]+)'),
]
skip = {'android-v4\\app\\src\\main\\assets\\public\\assets\\syncMonitor-Bf50IX2L.js'}
for f in sorted(files):
    rel = os.path.relpath(f, ROOT)
    if rel in skip:
        continue
    s = io.open(f, encoding='utf-8', errors='replace').read()
    out = set()
    for name, pat in pats:
        for m in re.findall(pat, s):
            out.add((name, m))
    if out:
        print(rel)
        for x in sorted(out):
            print('    ', x[0].ljust(10), x[1])
