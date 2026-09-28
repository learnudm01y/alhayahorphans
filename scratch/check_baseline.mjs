import * as faceapi from '@vladmandic/face-api/dist/face-api.node-wasm.js';
import sharp from 'sharp';
const file = process.argv[2];
const { data, info } = await sharp(file).rotate().removeAlpha().raw().toBuffer({ resolveWithObject: true });
try { await faceapi.tf.setBackend('wasm'); } catch (e) { console.log('wasm backend:', e.message); }
await faceapi.tf.ready();
console.log('tf backend:', faceapi.tf.getBackend(), '| image:', info.width, 'x', info.height);
await faceapi.nets.ssdMobilenetv1.loadFromDisk('public/scripts/models');
await faceapi.nets.faceLandmark68Net.loadFromDisk('public/scripts/models');
const t = faceapi.tf.tensor3d(new Uint8Array(data), [info.height, info.width, 3]);
for (const c of [0.5, 0.3, 0.1]) {
  const d = await faceapi.detectAllFaces(t, new faceapi.SsdMobilenetv1Options({ minConfidence: c })).withFaceLandmarks();
  console.log(`minConf=${c}: ${d.length} face(s)` + (d.length ? ` scores=[${d.map(x => x.detection.score.toFixed(3)).join(',')}] faceW=${((d[0].detection.box.width / info.width) * 100).toFixed(1)}%` : ''));
}
t.dispose();
