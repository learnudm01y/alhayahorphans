import * as ort from 'onnxruntime-node';
import os from 'node:os';
const path = 'storage/app/face_models/restoreformer.onnx';
console.log('free before MB:', Math.round(os.freemem() / 1048576));
const t0 = Date.now();
const s = await ort.InferenceSession.create(path, {
  executionProviders: ['cpu'], graphOptimizationLevel: 'basic',
  enableCpuMemArena: false, enableMemPattern: false, executionMode: 'sequential',
});
console.log('LOAD OK ms:', Date.now() - t0, '| free MB:', Math.round(os.freemem() / 1048576));
console.log('inputs :', JSON.stringify(s.inputMetadata ?? s.inputNames));
console.log('outputs:', JSON.stringify(s.outputMetadata ?? s.outputNames));
for (const n of [256, 512]) {
  try {
    const name = s.inputNames[0];
    const x = new ort.Tensor(new Float32Array(3 * n * n), [1, 3, n, n]);
    const t1 = Date.now();
    const out = await s.run({ [name]: x });
    const k = Object.keys(out)[0];
    console.log(`probe ${n}: OK ms=${Date.now() - t1} -> ${k} ${out[k].type} [${out[k].dims}] free=${Math.round(os.freemem() / 1048576)}MB`);
  } catch (e) { console.log(`probe ${n}: FAIL ${String(e.message).slice(0, 180)}`); }
}
await s.release();
console.log('released. free MB:', Math.round(os.freemem() / 1048576));
