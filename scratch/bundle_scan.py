import re, io, glob, os

ROOT = r'C:\xampp\htdocs\alhayahorphans\android-v4\app\src\main\assets\public'
files = glob.glob(ROOT + r'\assets\*.js') + glob.glob(ROOT + r'\assets\*.js.map')
pats = [
    ('apiMobile', r'/api/mobile/[A-Za-z0-9/_\-{}$]+'),
    ('mobile', r'(?<![A-Za-z0-9])/mobile/[A-Za-z0-9/_\-{}$]+'),
    ('sync', r'(?<![A-Za-z0-9])/sync/[a-z][A-Za-z0-9/_\-{}$]+'),
    ('uploads', r'(?<![A-Za-z0-9])/uploads/[a-z][A-Za-z0-9/_\-{}$]+'),
]
for f in sorted(files):
    s = io.open(f, encoding='utf-8', errors='replace').read()
    out = set()
    for name, pat in pats:
        for m in re.findall(pat, s):
            out.add((name, m))
    if out:
        print(os.path.basename(f), 'len=%d' % len(s))
        for x in sorted(out):
            print('    ', x[0].ljust(10), x[1])
