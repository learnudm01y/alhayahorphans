import numpy as np, onnxruntime as ort
from PIL import Image

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
src = Image.open("storage/app/public/uploads/000208/12_000208_438396335.jpg").convert("RGB")

def psnr(a, b):
    mse = float(np.mean((a - b) ** 2))
    return 99.0 if mse == 0 else 10 * np.log10(255.0 ** 2 / mse)

def test(n, tag=None, img=None):
    im = (img or src).resize((n, n), Image.LANCZOS)
    a = (np.asarray(im, dtype=np.float32) / 255.0).transpose(2, 0, 1)
    y = sess.run(None, {"input": a[None]})[0]
    out = np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255)
    # downscale output back to input size (area) and compare with input
    back = np.asarray(Image.fromarray(out.astype(np.uint8)).resize((n, n), Image.BOX), dtype=np.float32)
    ref = np.asarray(im, dtype=np.float32)
    print(f"{tag or n:>10} n={n:<4} out={y.shape[3]:<5} psnr={psnr(back, ref):6.2f} dB mean={y.mean():.4f}")
    return out

for n in [32, 48, 64, 80, 96, 128, 160, 192, 256]:
    test(n)

print("--- same size, different content ---")
for n in [64, 128]:
    test(n, tag=f"solid{n}", img=Image.new("RGB", (n, n), (200, 160, 120)))
