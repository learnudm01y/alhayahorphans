import numpy as np, onnxruntime as ort, os
from PIL import Image

n = 64
a = np.fromfile("scratch/in_sharp64_f32.bin", dtype=np.float32).reshape(1, 3, n, n)
print("input mean", float(a.mean()), "min", float(a.min()), "max", float(a.max()))

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
y = sess.run(None, {"input": a})[0]
print("py out mean", float(y.mean()))

if os.path.exists("scratch/out_node_sharp64.bin"):
    yn = np.fromfile("scratch/out_node_sharp64.bin", dtype=np.float32).reshape(y.shape)
    print("max abs diff py vs node:", float(np.abs(yn - y).max()))

Image.fromarray(np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)).save("scratch/py_sharp64_out.png")
# grade
out = np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255)
print("dy grad", float(np.abs(np.diff(out, axis=0)).mean()))
