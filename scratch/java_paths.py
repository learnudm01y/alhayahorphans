import re, io, glob, os

ROOT = r'C:\xampp\htdocs\alhayahorphans'
files = glob.glob(ROOT + r'\android-v4\app\src\main\java\**\*.java', recursive=True)
pats = [
    ('mobile', r'["\'](/api/mobile/[^"\']+)'),
    ('mobile-rel', r'["\'](/mobile/[^"\']+)'),
    ('uploads-api', r'["\'](/api/uploads/[^"\']+)'),
    ('uploads-rel', r'["\'](/uploads/[^"\']+)'),
    ('sync-api', r'["\'](/api/sync/[^"\']+)'),
    ('verify', r'["\'](/api/verify-password)'),
    ('sponsors', r'mobile/sponsors'),
    ('associations', r'associations'),
]
hits = {}
for f in files:
    s = io.open(f, encoding='utf-8', errors='replace').read()
    rel = os.path.relpath(f, ROOT)
    for name, pat in pats:
        for m in re.findall(pat, s):
            hits.setdefault(rel, set()).add((name, m if isinstance(m, str) else m))
    # also ApiConfig URL constants usage lines
for f in sorted(hits):
    print(f)
    for x in sorted(hits[f]):
        print('   ', x[0].ljust(12), x[1])
