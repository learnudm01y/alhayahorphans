import re, os, collections

root = r'C:\xampp\htdocs\alhayahorphans\android-v4\app\src\main'
pat = re.compile(r'[\'"`](/?((?:api/)?(?:mobile|uploads|sync|registration|civil-registry|sponsorships|lookups|photos|payments|orphans)[a-zA-Z0-9/_\-{}$.]*))[\'"`]')
hits = collections.defaultdict(set)

for dp, dn, fn in os.walk(root):
    for f in fn:
        if f.endswith(('.java', '.js', '.html')):
            p = os.path.join(dp, f)
            try:
                t = open(p, encoding='utf-8', errors='ignore').read()
            except Exception:
                continue
            for m in pat.finditer(t):
                s = m.group(1)
                if len(s) > 3:
                    hits[s].add(os.path.relpath(p, root))

for k in sorted(hits):
    print(k, '  <-  ', ', '.join(sorted(hits[k]))[:150])
