import ort from 'onnxruntime-node';
import sharp from 'sharp';
import fs from 'node:fs';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const n = 64;
const bin = fs.readFileSync('scratch/in64.bin');
const arr = new Float32Array(bin.buffer, bin.byteOffset, n * n * 3);

const sess = await ort.InferenceSession.create(MODEL);
const r = await sess.run({ input: new ort.Tensor(arr.slice(), [1, 3, n, n]) });
const d = r.output.data, m = r.output.dims[3];
fs.writeFileSync('scratch/out_node64.bin', Buffer.from(d.buffer, d.byteOffset, d.byteLength));
const nn = m * m, buf = Buffer.alloc(nn * 3);
for (let i = 0; i < nn; i++) for (let c = 0; c < 3; c++) {
  let v = d[c * nn + i] * 255;
  if (v < 0) v = 0; if (v > 255) v = 255;
  buf[i * 3 + c] = v;
}
await sharp(buf, { raw: { width: m, height: m, channels: 3 } }).png().toFile('scratch/cmp_node_pre.png');
console.log('node mean', Array.from(d).reduce((a, b) => a + b, 0) / d.length);
await sess.release?.();
