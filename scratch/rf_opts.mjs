import ort from 'onnxruntime-node';
import os from 'node:os';
const cfg = process.argv[2] || 'plain';
const opts = {
  executionProviders: ['cpu'],
  graphOptimizationLevel: cfg.includes('optall') ? 'all' : 'basic',
  enableCpuMemArena: cfg.includes('arena'),
  enableMemPattern: !cfg.includes('nopattern'),
  executionMode: 'sequential',
};
if (cfg.includes('threads1')) opts.intraOpNumThreads = 1;
const freeMB = () => Math.round(os.freemem() / 1048576);
console.log(cfg, '| free MB', freeMB());
const t0 = Date.now();
const s = await ort.InferenceSession.create('storage/app/face_models/restoreformer.onnx', opts);
console.log('loaded', Date.now() - t0, 'ms | free MB', freeMB());
const t = new ort.Tensor(new Float32Array(1 * 3 * 512 * 512), [1, 3, 512, 512]);
const t1 = Date.now();
try {
  const out = await s.run({ input: t });
  console.log('OK run', Date.now() - t1, 'ms | out', JSON.stringify(out[Object.keys(out)[0]].dims), '| free MB', freeMB());
} catch (e) {
  console.log('FAIL', Date.now() - t1, 'ms |', String(e.message).replace(/\u001b\[[0-9;]*m/g, '').slice(0, 150), '| free MB', freeMB());
}
await s.release();
