import re, io, os, sys

ROOT = r'C:\xampp\htdocs\alhayahorphans\android-v4\app\src\main\assets\public'

# (relative path, extra rules)
TARGETS = [
    'js/api-service.js',
    'js/sync-service.js',
    'sync-service.js',
    'js/sync-service-real.js',
    'js/photo-store.js',
    'js/civil-registry.js',
    'js/full-file-edit.js',
    'js/registration.js',
    'search.html',
    'detail.html',
    'photography.html',
]

def rewrite(s, rel):
    total = 0
    # 1) /api/civil-registry/  ->  /api/mobile/v4/civil-registry/   (search.html)
    s, n = re.subn(r'/api/civil-registry/', '/api/mobile/v4/civil-registry/', s)
    total += n
    # 2) /mobile/...(ليست v4)  ->  /mobile/v4/...
    s, n = re.subn(r'/mobile/(?!v4/)', '/mobile/v4/', s)
    total += n
    # 3) /api/mobile المكتوب بلا شرطة مائلة بعده (مثال: BASE_URL بانتهاء api/mobile)
    s, n = re.subn(r"/api/mobile(?!/v4)(?=['\"\`\s\\])", '/api/mobile/v4', s)
    total += n
    return s, total

changed = 0
for rel in TARGETS:
    p = os.path.join(ROOT, rel)
    if not os.path.exists(p):
        print('MISSING:', rel)
        continue
    orig = io.open(p, encoding='utf-8').read()
    new, n = rewrite(orig, rel)
    if n:
        io.open(p, 'w', encoding='utf-8', newline='').write(new)
        changed += 1
        print('%-28s replacements=%d' % (rel, n))
    else:
        print('%-28s NO CHANGE' % rel)

# حزمة syncMonitor المجمّعة (مسارات /sync/* و /uploads/* )
bundle = os.path.join(ROOT, 'assets', 'syncMonitor-Bf50IX2L.js')
if os.path.exists(bundle):
    orig = io.open(bundle, encoding='utf-8').read()
    s = orig
    s, n1 = re.subn(r"(['\"`])/sync/", r"\1/mobile/v4/sync/", s)
    s, n2 = re.subn(r"(['\"`])/uploads/", r"\1/mobile/v4/uploads/", s)
    s, n3 = re.subn(r'/api/mobile(?!/v4)', '/api/mobile/v4', s)
    if s != orig:
        io.open(bundle, 'w', encoding='utf-8', newline='').write(s)
        changed += 1
        print('%-28s sync=%d uploads=%d apimobile=%d' % ('assets/syncMonitor-Bf50IX2L.js', n1, n2, n3))
    else:
        print('%-28s NO CHANGE' % 'assets/syncMonitor-Bf50IX2L.js')

print('files changed:', changed)
