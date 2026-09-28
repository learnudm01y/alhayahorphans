import * as ort from 'onnxruntime-node';
import os from 'node:os';

const t0 = Date.now();
const s = await ort.InferenceSession.create('storage/app/face_models/real_esrgan_x4.onnx', {
  executionProviders: ['cpu'], graphOptimizationLevel: 'basic',
  enableCpuMemArena: false, enableMemPattern: false, executionMode: 'sequential',
});
console.log('load ms:', Date.now() - t0, '| free MB:', Math.round(os.freemem() / 1048576));
for (const n of [64, 128, 256, 512]) {
  try {
    const x = new ort.Tensor(new Float32Array(1 * 3 * n * n), [1, 3, n, n]);
    const t1 = Date.now();
    const out = await s.run({ input: x });
    const ms = Date.now() - t1;
    console.log(`tile ${n}x${n}: ${ms} ms -> [${out.output.dims}] | free MB: ${Math.round(os.freemem() / 1048576)}`);
  } catch (e) {
    console.log(`tile ${n}x${n}: FAIL ${String(e.message).slice(0, 140)}`);
  }
}
await s.release();
