# -*- coding: utf-8 -*-
import io, re, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
p = r'android-v4\app\src\main\assets\public\assets\syncMonitor-Bf50IX2L.js'
s = io.open(p, encoding='utf-8').read()
print('v4 occurrences:', len(re.findall(r'/mobile/v4/', s)))
seen = set()
for m in re.finditer(r'.{60}/mobile/v4/[^\'"`\s]{0,70}', s):
    t = m.group(0).replace('\n', ' ')[:150]
    if t not in seen:
        seen.add(t)
        print('---', t)
print('=== upload-ish patterns ===')
for pat in ['upload', 'offline', 'drive-status', 'initial', 'actions', 'stats']:
    print(pat, len(re.findall(pat, s, re.I)))
