import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import sharp from 'sharp';
import ort from 'onnxruntime-web';

const here = path.dirname(fileURLToPath(import.meta.url));
ort.env.wasm.numThreads = 1;
ort.env.wasm.proxy = false;
import { pathToFileURL } from 'node:url';
const root = path.resolve(here, '..');
ort.env.wasm.wasmPaths = pathToFileURL(path.join(root, 'node_modules', 'onnxruntime-web', 'dist')).href + '/';

const bytes = fs.readFileSync('storage/app/face_models/real_esrgan_x4.onnx');
console.log('file bytes', bytes.length);

const t0 = Date.now();
const sess = await ort.InferenceSession.create(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
console.log('loaded ms', Date.now() - t0, sess.inputNames, sess.outputNames);

async function run(raw, n) {
  const arr = new Float32Array(n * n * 3);
  for (let i = 0; i < n * n * 3; i++) arr[i] = raw[i] / 255;
  const r = await sess.run({ input: new ort.Tensor('float32', arr, [1, 3, n, n]) });
  const d = r.output.data, m = r.output.dims[3], nn = m * m;
  const buf = Buffer.alloc(nn * 3);
  for (let i = 0; i < nn; i++) for (let c = 0; c < 3; c++) {
    let v = d[c * nn + i] * 255;
    if (v < 0) v = 0; if (v > 255) v = 255;
    buf[i * 3 + c] = v;
  }
  return { buf, m };
}

const n = 128;
const ramp = Buffer.alloc(n * n * 3);
for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
  const i = (y * n + x) * 3;
  ramp[i] = Math.round((x / (n - 1)) * 255); ramp[i + 1] = 128; ramp[i + 2] = 128;
}
const r1 = await run(ramp, n);
await sharp(r1.buf, { raw: { width: r1.m, height: r1.m, channels: 3 } }).png().toFile('scratch/web_ramp.png');

const face = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer();
const r2 = await run(face, n);
await sharp(r2.buf, { raw: { width: r2.m, height: r2.m, channels: 3 } }).png().toFile('scratch/web_face.png');
console.log('done');
