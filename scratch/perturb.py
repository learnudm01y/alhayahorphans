import numpy as np, onnxruntime as ort

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
a = np.fromfile("scratch/in64.bin", dtype=np.float32).reshape(1, 3, 64, 64).copy()
y0 = sess.run(None, {"input": a})[0]
dy0 = float(np.abs(np.diff(y0[0], axis=1)).mean())
print("base dy", round(dy0, 3))

b = a.copy(); b[0, 0, 32, 32] += 1 / 255
y1 = sess.run(None, {"input": b})[0]
print("perturb +1/255 one px: maxdiff", float(np.abs(y1 - y0).max()), "dy", round(float(np.abs(np.diff(y1[0], axis=1)).mean()), 3))

c = a.copy(); c += (np.random.rand(*c.shape).astype(np.float32) - 0.5) / 255
y2 = sess.run(None, {"input": c})[0]
print("perturb uniform +-0.5/255: maxdiff", float(np.abs(y2 - y0).max()), "dy", round(float(np.abs(np.diff(y2[0], axis=1)).mean()), 3))

d = a.copy(); d = np.clip(d + (np.random.rand(*d.shape).astype(np.float32) - 0.5) / 128, 0, 1)
y3 = sess.run(None, {"input": d})[0]
print("perturb uniform +-1/128: dy", round(float(np.abs(np.diff(y3[0], axis=1)).mean()), 3))

# sharp-style input for comparison
s = np.fromfile("scratch/in_sharp64_f32.bin", dtype=np.float32).reshape(1, 3, 64, 64)
print("input diff PIL vs sharp: max", float(np.abs(s - a).max()), "mean", float(np.abs(s - a).mean()))
