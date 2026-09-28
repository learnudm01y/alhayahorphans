import ort from 'onnxruntime-node';
import sharp from 'sharp';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const opts = { executionProviders: ['cpu'], graphOptimizationLevel: 'all', intraOpNumThreads: 1 };
const sess = await ort.InferenceSession.create(MODEL, opts);

async function run(raw, n, tag) {
  const arr = new Float32Array(n * n * 3);
  for (let i = 0; i < n * n * 3; i++) arr[i] = raw[i] / 255;
  const t = Date.now();
  const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, n, n]) });
  const d = r.output.data, m = r.output.dims[3], nn = m * m;
  const buf = Buffer.alloc(nn * 3);
  for (let i = 0; i < nn; i++) for (let c = 0; c < 3; c++) {
    let v = d[c * nn + i] * 255;
    if (v < 0) v = 0; if (v > 255) v = 255;
    buf[i * 3 + c] = v;
  }
  await sharp(buf, { raw: { width: m, height: m, channels: 3 } }).png().toFile(`scratch/node_${tag}.png`);
  console.log(tag, n, '->', m, Date.now() - t, 'ms mean', (Array.from(d).reduce((a, b) => a + b, 0) / d.length).toFixed(4));
}

const src = 'storage/app/public/uploads/000208/12_000208_438396335.jpg';
for (const n of [32, 64, 128, 256]) {
  const raw = await sharp(src).resize(n, n, { fit: 'fill', kernel: 'lanczos3' }).removeAlpha().raw().toBuffer();
  await run(raw, n, `p${n}`);
}
const face = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer();
await run(face, 128, 'aligned128');
await sess.release?.();
