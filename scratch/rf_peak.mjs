import ort from 'onnxruntime-node';
import os from 'node:os';
const opts = {
  executionProviders: ['cpu'],
  graphOptimizationLevel: 'basic',
  enableCpuMemArena: false,
  enableMemPattern: false,
  executionMode: 'sequential',
  intraOpNumThreads: 1,
};
let minFree = 1e9, maxRss = 0, stop = false;
const tick = setInterval(() => {
  const f = os.freemem() / 1048576, r = process.memoryUsage().rss / 1048576;
  if (f < minFree) minFree = f;
  if (r > maxRss) maxRss = r;
}, 100);
console.log('start free MB', Math.round(os.freemem() / 1048576));
const s = await ort.InferenceSession.create('storage/app/face_models/restoreformer.onnx', opts);
console.log('loaded | free MB', Math.round(os.freemem() / 1048576), '| rss MB', Math.round(process.memoryUsage().rss / 1048576));
const t = new ort.Tensor(new Float32Array(1 * 3 * 512 * 512), [1, 3, 512, 512]);
const t1 = Date.now();
try {
  const out = await s.run({ input: t });
  console.log('OK', Date.now() - t1, 'ms | out', JSON.stringify(out[Object.keys(out)[0]].dims));
} catch (e) {
  console.log('FAIL', Date.now() - t1, 'ms |', String(e.message).slice(0, 120));
}
stop = true; clearInterval(tick);
console.log('min free MB', Math.round(minFree), '| max rss MB', Math.round(maxRss));
await s.release();
