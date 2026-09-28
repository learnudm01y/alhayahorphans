import numpy as np, onnxruntime as ort
from PIL import Image, ImageFilter

sess = ort.InferenceSession("storage/app/face_models/real_esrgan_x4.onnx", providers=["CPUExecutionProvider"])
src = Image.open("storage/app/public/uploads/000208/12_000208_438396335.jpg").convert("RGB")

def run(img, name):
    a = np.asarray(img, dtype=np.float32) / 255.0
    y = sess.run(None, {"input": a.transpose(2, 0, 1)[None]})[0]
    out = np.clip(y[0].transpose(1, 2, 0) * 255, 0, 255).astype(np.uint8)
    Image.fromarray(out).save(f"scratch/py_{name}.png")
    print(name, img.size, "->", y.shape, "mean", round(float(y.mean()), 4))

run(src.resize((64, 64), Image.LANCZOS), "p64")
run(src.resize((32, 32), Image.LANCZOS), "p32")
run(src.resize((64, 64), Image.LANCZOS).filter(ImageFilter.GaussianBlur(3)), "p64blur")
run(src.resize((128, 128), Image.LANCZOS).filter(ImageFilter.GaussianBlur(4)), "p128blur")
run(src.resize((256, 256), Image.LANCZOS), "p256")
