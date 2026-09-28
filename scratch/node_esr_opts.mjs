import ort from 'onnxruntime-node';
import sharp from 'sharp';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const src = 'storage/app/public/uploads/000208/12_000208_438396335.jpg';
const n = 64;
const raw = await sharp(src).resize(n, n, { fit: 'fill', kernel: 'lanczos3' }).removeAlpha().raw().toBuffer();
const arr = new Float32Array(n * n * 3);
for (let i = 0; i < n * n * 3; i++) arr[i] = raw[i] / 255;

const cases = [
  ['default', undefined],
  ['disasm', { graphOptimizationLevel: 'disable' }],
  ['basic', { graphOptimizationLevel: 'basic' }],
  ['all_t1', { graphOptimizationLevel: 'all', intraOpNumThreads: 1 }],
  ['all_seq', { graphOptimizationLevel: 'all', executionMode: 'sequential' }],
  ['all_t2', { graphOptimizationLevel: 'all', intraOpNumThreads: 2 }],
  ['default_t1', { intraOpNumThreads: 1 }],
];

for (const [tag, opts] of cases) {
  try {
    const sess = await ort.InferenceSession.create(MODEL, opts);
    const t = Date.now();
    const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, n, n]) });
    const d = r.output.data, m = r.output.dims[3], nn = m * m;
    const buf = Buffer.alloc(nn * 3);
    let sum = 0;
    for (let i = 0; i < nn; i++) for (let c = 0; c < 3; c++) {
      const v0 = d[c * nn + i];
      sum += v0;
      let v = v0 * 255;
      if (v < 0) v = 0; if (v > 255) v = 255;
      buf[i * 3 + c] = v;
    }
    await sharp(buf, { raw: { width: m, height: m, channels: 3 } }).png().toFile(`scratch/opt_${tag}.png`);
    console.log(tag.padEnd(10), Date.now() - t, 'ms mean', (sum / d.length).toFixed(4));
    await sess.release?.();
  } catch (e) {
    console.log(tag.padEnd(10), 'ERR', e.message.slice(0, 120));
  }
}
