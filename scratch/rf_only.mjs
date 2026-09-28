import ort from 'onnxruntime-node';
import fs from 'node:fs';
const t0 = Date.now();
const s = await ort.InferenceSession.create('storage/app/face_models/restoreformer.onnx', {
  executionProviders: ['cpu'], graphOptimizationLevel: 'basic', enableCpuMemArena: false, enableMemPattern: false, executionMode: 'sequential',
});
console.log('loaded', Date.now() - t0, 'ms');
const a = new Float32Array(1 * 3 * 512 * 512);
const t = new ort.Tensor(a, [1, 3, 512, 512]);
const t1 = Date.now();
const out = await s.run({ input: t });
const k = Object.keys(out)[0];
console.log('run', Date.now() - t1, 'ms | out', JSON.stringify(out[k].dims), '| free MB', Math.round(process.memoryUsage().rss / 1048576));
await s.release();
console.log('released; total', Date.now() - t0, 'ms');
