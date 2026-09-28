import ort from 'onnxruntime-node';
import sharp from 'sharp';
const S = 'storage/app/face_models/real_esrgan_x4.onnx';
const s = await ort.InferenceSession.create(S, { executionProviders: ['cpu'], graphOptimizationLevel: 'basic', enableCpuMemArena: false, enableMemPattern: false, executionMode: 'sequential' });
const { data: rgb, info } = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer({ resolveWithObject: true });
const N = info.width, count = N * N;
const inp = new Float32Array(count * 3);
for (let i = 0; i < count; i++) { inp[i] = rgb[i * 3] / 255; inp[count + i] = rgb[i * 3 + 1] / 255; inp[2 * count + i] = rgb[i * 3 + 2] / 255; }
const res = await s.run({ input: new ort.Tensor(inp, [1, 3, N, N]) });
const o = res.output.data, W = N * 4, H = N * 4, total = W * H;
console.log('out dims', JSON.stringify(res.output.dims), 'len', o.length, 'expect', total * 3);
// (1) as-is interleaved assumption
const a = Buffer.alloc(total * 3);
for (let i = 0; i < total * 3; i++) a[i] = Math.max(0, Math.min(255, Math.round(o[i] * 255)));
await sharp(a, { raw: { width: W, height: H, channels: 3 } }).png().toFile('scratch/face_dbg/esr_asis.png');
// (2) planar NCHW -> HWC
const b = Buffer.alloc(total * 3);
for (let i = 0; i < total; i++) { b[i * 3] = Math.max(0, Math.min(255, Math.round(o[i] * 255))); b[i * 3 + 1] = Math.max(0, Math.min(255, Math.round(o[total + i] * 255))); b[i * 3 + 2] = Math.max(0, Math.min(255, Math.round(o[2 * total + i] * 255))); }
await sharp(b, { raw: { width: W, height: H, channels: 3 } }).png().toFile('scratch/face_dbg/esr_planar.png');
console.log('min/max', Math.min(...o.subarray(0, 1000)).toFixed(3), Math.max(...o.subarray(0, 1000)).toFixed(3));
await s.release();
