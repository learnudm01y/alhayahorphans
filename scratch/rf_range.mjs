import ort from 'onnxruntime-node';
import sharp from 'sharp';
import fs from 'node:fs';
import os from 'node:os';

const RF = 'storage/app/face_models/restoreformer.onnx';
console.log('free MB', Math.round(os.freemem() / 1048576));
const sess = await ort.InferenceSession.create(RF, { executionProviders: ['cpu'], graphOptimizationLevel: 'all', intraOpNumThreads: 1 });

const N = 512, count = N * N;
const raw128 = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer();
const r = new Float32Array(count), g = new Float32Array(count), b = new Float32Array(count);
for (let y = 0; y < N; y++) {
  const sy = Math.min(127, Math.round((y * 128) / N));
  for (let x = 0; x < N; x++) {
    const sx = Math.min(127, Math.round((x * 128) / N));
    const si = (sy * 128 + sx) * 3, di = y * N + x;
    r[di] = raw128[si]; g[di] = raw128[si + 1]; b[di] = raw128[si + 2];
  }
}
const inp = new Float32Array(count * 3);
for (let i = 0; i < count; i++) { inp[i] = r[i] / 255; inp[count + i] = g[i] / 255; inp[2 * count + i] = b[i] / 255; }

const t = Date.now();
const res = await sess.run({ input: new ort.Tensor(inp, [1, 3, N, N]) });
console.log('rf ms', Date.now() - t, 'out', res.output.dims, res.output.type);

const o = res.output.data;
const s = Float32Array.from(o).sort();
const q = (p) => s[Math.min(s.length - 1, Math.floor(p * s.length))];
console.log('min', s[0].toFixed(4), 'p0.1%', q(0.001).toFixed(4), 'p1%', q(0.01).toFixed(4), 'p50%', q(0.5).toFixed(4), 'p99%', q(0.99).toFixed(4), 'max', s[s.length - 1].toFixed(4));
await sess.release?.();
