import numpy as np, onnxruntime as ort, os
from PIL import Image

n = 64
# input: LANCZOS photo resize -> identical bytes for both runtimes
src = Image.open("storage/app/public/uploads/000208/12_000208_438396335.jpg").convert("RGB").resize((n, n), Image.LANCZOS)
a = (np.asarray(src, dtype=np.float32) / 255.0).transpose(2, 0, 1)
a.tofile("scratch/in64.bin")

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
y = sess.run(None, {"input": a[None]})[0]
y.tofile("scratch/out_py64.bin")
Image.fromarray(np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)).save("scratch/cmp_py.png")
print("py mean", float(y.mean()))

if os.path.exists("scratch/out_node64.bin"):
    yn = np.fromfile("scratch/out_node64.bin", dtype=np.float32).reshape(y.shape)
    print("max abs diff", float(np.abs(yn - y).max()), "mean abs diff", float(np.abs(yn - y).mean()))
    Image.fromarray(np.clip(yn[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)).save("scratch/cmp_node.png")
    # feed python output back into node? no — just report
    d = np.abs(yn - y)
    print("pct pixels differing >1e-3:", float((d > 1e-3).mean()) * 100)
