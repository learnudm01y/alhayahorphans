import sharp from 'sharp';
import * as faceapi from '@vladmandic/face-api/dist/face-api.node-wasm.js';
import path from 'node:path';

const MODELS = path.resolve('public/scripts/models');
const FILE = process.argv[2] || 'scratch/rej_a8.jpeg';

await faceapi.tf.setBackend('wasm').catch(() => {});
await faceapi.tf.ready();
await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODELS);
await faceapi.nets.faceLandmark68Net.loadFromDisk(MODELS);

async function probe(label, buf, w, h) {
  const tensor = faceapi.tf.tensor3d(new Uint8Array(buf), [h, w, 3]);
  const dets = await faceapi
    .detectAllFaces(tensor, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.10 }))
    .withFaceLandmarks();
  console.log(`--- ${label} ${w}x${h} ---`);
  if (!dets || !dets.length) { console.log('  no faces'); tensor.dispose(); return; }
  dets.sort((a, b) => b.detection.box.area - a.detection.box.area);
  for (const d of dets) {
    const b = d.detection.box;
    const rel = (b.width / w) * 100;
    const lm = d.landmarks && d.landmarks.positions ? d.landmarks.positions.length : 0;
    console.log(`  score=${d.detection.score.toFixed(3)} box=${b.x.toFixed(0)},${b.y.toFixed(0)},${b.width.toFixed(0)}x${b.height.toFixed(0)} relW=${rel.toFixed(1)}% landmarks=${lm} pass0.5=${d.detection.score >= 0.5 ? 'YES' : 'NO (<0.50)'} passLandmarks=${lm >= 68 ? 'YES' : 'NO'}`);
  }
  tensor.dispose();
}

// 1) الدقة الكاملة (كما يفحصها المتصفح على الـcanvas)
{
  const { data, info } = await sharp(FILE).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  await probe('full-res', data, info.width, info.height);
}

// 2) بعد التصغير لأقصى 1024 (كما يفكّها السكربت)
{
  const m = await sharp(FILE).metadata();
  const scale = Math.min(1, 1024 / Math.max(m.width, m.height));
  const w = Math.round(m.width * scale), h = Math.round(m.height * scale);
  const { data, info } = await sharp(FILE).resize(w, h).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  await probe('max1024', data, info.width, info.height);
}

console.log('tf memory:', JSON.stringify(faceapi.tf.memory()));
