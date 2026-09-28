import * as ort from 'onnxruntime-node';
import fs from 'node:fs';

const files = process.argv.slice(2);
if (!files.length) {
  console.error('Usage: node scripts/inspect-models.mjs <model.onnx> [...]');
  process.exit(1);
}

// Small first: this project runs on a 8 GB RAM machine.
const CANDIDATE_SHAPES = [
  [1, 3, 64, 64],
  [1, 3, 128, 128],
  [1, 3, 256, 256],
  [1, 3, 512, 512],
];

function probeShape(dimensions) {
  if (!Array.isArray(dimensions) || dimensions.length !== 4) return null;
  const fixed = dimensions.every((d) => typeof d === 'number' && d > 0);
  if (fixed) return dimensions;
  for (const c of CANDIDATE_SHAPES) {
    const ok = dimensions.every((d, i) => (typeof d === 'number' && d > 0 ? d === c[i] : true));
    if (ok) return c;
  }
  return null;
}

const LEAN = {
  executionProviders: ['cpu'],
  graphOptimizationLevel: 'basic',
  enableCpuMemArena: false,
  enableMemPattern: false,
  executionMode: 'sequential',
};

for (const file of files) {
  console.log('\n========================================');
  console.log('FILE:', file, `(${(fs.statSync(file).size / 1024 / 1024).toFixed(1)} MB)`);
  let session;
  try {
    const t0 = Date.now();
    session = await ort.InferenceSession.create(file, LEAN);
    console.log('load ms:', Date.now() - t0);
    console.log('inputNames :', JSON.stringify(session.inputNames));
    console.log('outputNames:', JSON.stringify(session.outputNames));
    console.log('inputMetadata :', JSON.stringify(session.inputMetadata ?? null));
    console.log('outputMetadata:', JSON.stringify(session.outputMetadata ?? null));

    const meta = session.inputMetadata ?? {};
    for (const name of session.inputNames) {
      const m = Array.isArray(meta) ? meta[session.inputNames.indexOf(name)] : meta[name];
      if (!m) continue;
      const dims = m.dimensions ?? m.shape;
      const shape = probeShape(dims);
      const dtype = (m.type || 'float32').replace(/^tensor\(/, '').replace(')', '');
      if (!shape) { console.log(`  probe ${name}: cannot guess dims ${JSON.stringify(dims)}`); continue; }
      for (const dt of [dtype, 'float32']) {
        try {
          const len = shape.reduce((a, b) => a * b, 1);
          const data = dt === 'uint8' ? new Uint8Array(len) : new Float32Array(len);
          const tensor = new ort.Tensor(data, shape);
          const t1 = Date.now();
          const out = await session.run({ [name]: tensor });
          const summary = Object.entries(out).map(([k, v]) => `${k}:${v.type}[${v.dims}]`).join(', ');
          console.log(`  probe OK   shape=[${shape}] dtype=${dt} ms=${Date.now() - t1} -> ${summary}`);
          break;
        } catch (e) {
          console.log(`  probe FAIL shape=[${shape}] dtype=${dt}: ${String(e.message).slice(0, 200)}`);
        }
      }
    }
  } catch (e) {
    console.error('ERROR:', e.message);
  } finally {
    if (session) {
      try { await session.release?.(); } catch { /* ignore */ }
      session = null;
    }
    global.gc?.();
    console.log('free RAM MB after release:', Math.round(process.memoryUsage ? (await import('node:os')).freemem() / 1048576 : 0));
  }
}
