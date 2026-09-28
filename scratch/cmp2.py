import numpy as np, onnxruntime as ort
from PIL import Image

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
a = np.fromfile("scratch/in64.bin", dtype=np.float32).reshape(1, 3, 64, 64).copy()

def out(x):
    y = sess.run(None, {"input": x})[0]
    return y, np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)

y0, img0 = out(a)
print("clean mean", float(y0.mean()))

# perturb by 1/255 in one channel
b = a.copy(); b[0, 0, 32, 32] += 1 / 255
y1, img1 = out(b)
print("perturbed mean", float(y1.mean()), "maxdiff vs clean", float(np.abs(y1 - y0).max()))
Image.fromarray(img1).save("scratch/cmp_perturb.png")

# sharp-style input (as produced by node pipeline)
raw = np.frombuffer(open("scratch/node_input64.bin", "rb").read() if False else b"", dtype=np.uint8)
