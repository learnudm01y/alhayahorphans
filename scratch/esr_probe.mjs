import ort from 'onnxruntime-node';
import sharp from 'sharp';
import fs from 'node:fs';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const opts = { executionProviders: ['cpu'], graphOptimizationLevel: 'all', intraOpNumThreads: 1 };

const sess = await ort.InferenceSession.create(MODEL, opts);
console.log('in', sess.inputNames, 'out', sess.outputNames);

function dump(t, file, size, layout = 'chw') {
  const d = t.data, n = size * size;
  const buf = Buffer.alloc(n * 3);
  for (let i = 0; i < n; i++) for (let c = 0; c < 3; c++) {
    const v0 = layout === 'chw' ? d[c * n + i] : d[i * 3 + c];
    let v = v0;
    if (v < 0) v = 0; if (v > 1) v = 1;
    buf[i * 3 + c] = Math.round(v * 255);
  }
  return sharp(buf, { raw: { width: size, height: size, channels: 3 } }).png().toFile(file);
}

// A) constant input
{
  const N = 64, arr = new Float32Array(N * N * 3).fill(0.5);
  const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, N, N]) });
  await dump(r.output, 'scratch/probe_const.png', N * 4);
  console.log('const ok', r.output.dims, r.output.type);
}

// B) aligned face 128 -> 512 in ONE run (no tiling)
{
  const raw = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer();
  const N = 128, arr = new Float32Array(N * N * 3);
  for (let i = 0; i < N * N * 3; i++) arr[i] = raw[i] / 255;
  const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, N, N]) });
  await dump(r.output, 'scratch/probe_full.png', N * 4);
  console.log('full ok', r.output.dims);
  await dump(r.output, 'scratch/probe_full_nhwc.png', N * 4, 'nhwc');
  const d = r.output.data;
  let mn = 1e9, mx = -1e9, sum = 0;
  for (let i = 0; i < d.length; i++) { if (d[i] < mn) mn = d[i]; if (d[i] > mx) mx = d[i]; sum += d[i]; }
  console.log('full stats min', mn.toFixed(3), 'max', mx.toFixed(3), 'mean', (sum / d.length).toFixed(3));
  const inm = await sharp('scratch/face_dbg/aligned.png').stats();
  console.log('input stats', inm.channels.map((c) => c.mean.toFixed(1)).join('/'));
}

// C) one 64x64 tile from the aligned face
{
  const raw = await sharp('scratch/face_dbg/aligned.png').removeAlpha().extract({ left: 0, top: 0, width: 64, height: 64 }).raw().toBuffer();
  const N = 64, arr = new Float32Array(N * N * 3);
  for (let i = 0; i < N * N * 3; i++) arr[i] = raw[i] / 255;
  const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, N, N]) });
  await dump(r.output, 'scratch/probe_tile.png', N * 4);
  console.log('tile ok', r.output.dims);
}

await sess.release?.();
