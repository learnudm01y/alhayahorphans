import io, os, difflib

ROOT = r'C:\xampp\htdocs\alhayahorphans'
V3 = ROOT + r'\android-v3\app\src\main'
V4 = ROOT + r'\android-v4\app\src\main'


def tree(base, exts):
    out = {}
    for dirpath, dirnames, filenames in os.walk(base):
        for fn in filenames:
            if any(fn.endswith(e) for e in exts):
                full = os.path.join(dirpath, fn)
                rel = os.path.relpath(full, base).replace('\\', '/')
                out[rel] = full
    return out


def compare(label, base3, base4, exts):
    t3, t4 = tree(base3, exts), tree(base4, exts)
    only3 = sorted(set(t3) - set(t4))
    only4 = sorted(set(t4) - set(t3))
    both = sorted(set(t3) & set(t4))
    diff = []
    for rel in both:
        a = io.open(t3[rel], encoding='utf-8', errors='replace').read().splitlines()
        b = io.open(t4[rel], encoding='utf-8', errors='replace').read().splitlines()
        if a != b:
            diff.append(rel)
    print('== %s == only-in-v3: %d | only-in-v4: %d | differ: %d' % (label, len(only3), len(only4), len(diff)))
    for r in only3:
        print('   ONLY-V3:', r)
    for r in diff:
        print('   DIFF:', r)
    return t3, t4, diff


compare('JAVA', V3 + r'\java', V4 + r'\java', ['.java'])
t3a, t4a, wdiff = compare('ASSETS', V3 + r'\assets\public', V4 + r'\assets\public', ['.js', '.html', '.css', '.json'])

# تحليل سطور v3 الغائبة في v4 لكل ملف ويب مختلف
for rel in wdiff:
    a = io.open(t3a[rel], encoding='utf-8', errors='replace').read().splitlines()
    b = io.open(t4a[rel], encoding='utf-8', errors='replace').read().splitlines()
    bs = set(x.strip() for x in b)
    missing = [x for x in a if x.strip() and x.strip() not in bs]
    print('---- %s : %d v3-lines not-in-v4 (non-empty) ----' % (rel, len(missing)))
    for x in missing[:60]:
        print('   |', x.strip()[:150])
