import numpy as np, sys, glob, os
from PIL import Image

def score(p):
    a = np.asarray(Image.open(p).convert("RGB"), dtype=np.float32)
    if a.shape[0] < 8 or a.shape[1] < 8:
        return None
    dx = np.abs(np.diff(a, axis=1)).mean()
    dy = np.abs(np.diff(a, axis=0)).mean()
    # periodic row energy: difference between row i and row i+2 vs i and i+1
    return dx, dy

files = sorted(glob.glob("scratch/*.png"))
for f in files:
    r = score(f)
    if not r: continue
    print(f"{os.path.basename(f):<26} dx={r[0]:7.2f} dy={r[1]:7.2f}  {'STRIPES?' if max(r) > 18 else 'ok'}")
