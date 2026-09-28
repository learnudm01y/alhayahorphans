import re, io, glob, os

ROOT = r'C:\xampp\htdocs\alhayahorphans\android-v4\app\src\main\assets\public'
files = []
for ext in ('*.js', '*.html'):
    files += glob.glob(ROOT + '\\**\\' + ext, recursive=True)

BAD = [
    ('old-mobile', re.compile(r'/mobile/(?!v4/)')),
    ('old-apimobile', re.compile(r'/api/mobile/(?!v4/)')),
    ('old-apimobile-bare', re.compile(r"/api/mobile(?![/'\"\`\s]*v4)")),
    ('old-apiuploads', re.compile(r'/api/uploads/')),
    ('old-apisync', re.compile(r'/api/sync/')),
    ('old-civil', re.compile(r'/api/civil-registry/')),
    ('double-v4', re.compile(r'/mobile/v4/v4/')),
    ('double-apiv4', re.compile(r'/api/mobile/v4/v4/')),
    ('old-syncstr', re.compile(r"(['\"`])/sync/[a-z]")),
    ('old-uploadsstr', re.compile(r"(['\"`])/uploads/[a-z]")),
]

problems = 0
for f in sorted(files):
    rel = os.path.relpath(f, ROOT)
    s = io.open(f, encoding='utf-8', errors='replace').read()
    for name, pat in BAD:
        for m in pat.finditer(s):
            ctx = s[max(0, m.start() - 60):m.end() + 60].replace('\n', ' ')
            # تجاهل داخل ملف الحزمة إن كان مساراً جديداً بالفعل (يظهر بعد تعديله نظيفاً)
            print('%s | %s | %s' % (rel, name, ctx.strip()[:130]))
            problems += 1
print('TOTAL PROBLEMS:', problems)
