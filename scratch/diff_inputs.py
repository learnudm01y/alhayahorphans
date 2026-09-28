import numpy as np
from PIL import Image, ImageChops

src = Image.open("storage/app/public/uploads/000208/12_000208_438396335.jpg")
print("size", src.size, "exif orientation", src.getexif().get(274), "mode", src.mode, "icc", bool(src.info.get("icc_profile")))
pil = src.convert("RGB").resize((64, 64), Image.LANCZOS)
p = np.asarray(pil, dtype=np.float32) / 255.0
print("pil mean", p.mean(), "min", p.min(), "max", p.max())

sh = np.fromfile("scratch/in_sharp64.bin", dtype=np.uint8).reshape(64, 64, 3).astype(np.float32) / 255.0
print("sharp mean", sh.mean(), "min", sh.min(), "max", sh.max())

d = np.abs(p - sh)
print("diff max", d.max(), "mean", d.mean(), "p99", np.percentile(d, 99))
# per-channel max location
idx = np.unravel_index(d.argmax(), d.shape)
print("worst pixel at", idx, "pil", p[idx], "sharp", sh[idx])

Image.fromarray((p * 255).astype(np.uint8)).resize((256, 256), Image.NEAREST).save("scratch/in_pil64.png")
Image.fromarray((sh * 255).astype(np.uint8)).resize((256, 256), Image.NEAREST).save("scratch/in_sharp64.png")

# also check: is in64.bin equal to PIL p?
a = np.fromfile("scratch/in64.bin", dtype=np.float32).reshape(3, 64, 64).transpose(1, 2, 0)
print("in64.bin vs p maxdiff", float(np.abs(a - p).max()))
