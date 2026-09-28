import numpy as np, onnxruntime as ort
from PIL import Image

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
print("in", [i.name for i in sess.get_inputs()], [i.shape for i in sess.get_inputs()])
print("out", [o.name for o in sess.get_outputs()], [o.shape for o in sess.get_outputs()])

n = 128
x = np.zeros((1, 3, n, n), dtype=np.float32)
for yy in range(n):
    for xx in range(n):
        x[0, 0, yy, xx] = xx / (n - 1)
        x[0, 1, yy, xx] = 0.5
        x[0, 2, yy, xx] = 0.5

y = sess.run(None, {"input": x})[0]
print("shape", y.shape, "min", y.min(), "max", y.max(), "mean", y.mean())
img = np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)
Image.fromarray(img).save("scratch/py_ramp.png")

# natural input
src = np.asarray(Image.open("storage/app/public/uploads/000208/12_000208_438396335.jpg").convert("RGB").resize((n, n)), dtype=np.float32) / 255.0
y2 = sess.run(None, {"input": src.transpose(2, 0, 1)[None]})[0]
img2 = np.clip(y2[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)
Image.fromarray(img2).save("scratch/py_photo.png")
print("photo ok", y2.shape, float(y2.mean()))
