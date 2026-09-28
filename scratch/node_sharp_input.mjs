import ort from 'onnxruntime-node';
import sharp from 'sharp';
import fs from 'node:fs';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const n = 64;
const raw = await sharp('storage/app/public/uploads/000208/12_000208_438396335.jpg')
  .resize(n, n, { fit: 'fill', kernel: 'lanczos3' }).removeAlpha().raw().toBuffer();
fs.writeFileSync('scratch/in_sharp64.bin', raw);
const arr = new Float32Array(n * n * 3);
for (let i = 0; i < n * n * 3; i++) arr[i] = raw[i] / 255;
fs.writeFileSync('scratch/in_sharp64_f32.bin', Buffer.from(arr.buffer));

const sess = await ort.InferenceSession.create(MODEL);
const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, n, n]) });
fs.writeFileSync('scratch/out_node_sharp64.bin', Buffer.from(r.output.data.buffer, r.output.data.byteOffset, r.output.data.byteLength));
console.log('node out mean', Array.from(r.output.data).reduce((a, b) => a + b, 0) / r.output.data.length);
await sess.release?.();
