import ort from 'onnxruntime-node';
import sharp from 'sharp';

const MODEL = 'storage/app/face_models/real_esrgan_x4.onnx';
const opts = { executionProviders: ['cpu'], graphOptimizationLevel: 'all', intraOpNumThreads: 1 };
const sess = await ort.InferenceSession.create(MODEL, opts);

async function run(raw, n, mode) {
  const arr = new Float32Array(n * n * 3);
  for (let i = 0; i < n * n * 3; i++) {
    const v = raw[i];
    arr[i] = mode === '01' ? v / 255 : mode === '11' ? v / 127.5 - 1 : v;
  }
  const r = await sess.run({ input: new ort.Tensor(arr, [1, 3, n, n]) });
  const d = r.output.data, m = r.output.dims[3], nn = m * m;
  const buf = Buffer.alloc(nn * 3);
  let mn = 1e9, mx = -1e9, sum = 0;
  for (let i = 0; i < nn; i++) for (let c = 0; c < 3; c++) {
    let v = d[c * nn + i];
    if (mode === '11') v = (v + 1) * 127.5;
    else if (mode === '255') v = v / 255;
    else v = v * 255;
    if (v < 0) v = 0; if (v > 255) v = 255;
    if (d[c * nn + i] < mn) mn = d[c * nn + i];
    if (d[c * nn + i] > mx) mx = d[c * nn + i];
    sum += d[c * nn + i];
    buf[i * 3 + c] = v;
  }
  return { buf, m, mn, mx, mean: sum / d.length };
}

const src = 'storage/app/public/uploads/000208/12_000208_438396335.jpg';

// 1) blurred face (low frequency), 128, mode 01
{
  const raw = await sharp('scratch/face_dbg/aligned.png').removeAlpha().blur(6).raw().toBuffer();
  const r = await run(raw, 128, '01');
  await sharp(r.buf, { raw: { width: r.m, height: r.m, channels: 3 } }).png().toFile('scratch/p_blur01.png');
  console.log('blur01', r.mn.toFixed(3), r.mx.toFixed(3), r.mean.toFixed(3));
}
// 2) blurred face, mode 11 ([-1,1])
{
  const raw = await sharp('scratch/face_dbg/aligned.png').removeAlpha().blur(6).raw().toBuffer();
  const r = await run(raw, 128, '11');
  await sharp(r.buf, { raw: { width: r.m, height: r.m, channels: 3 } }).png().toFile('scratch/p_blur11.png');
  console.log('blur11', r.mn.toFixed(3), r.mx.toFixed(3), r.mean.toFixed(3));
}
// 3) sharp face, mode 11
{
  const raw = await sharp('scratch/face_dbg/aligned.png').removeAlpha().raw().toBuffer();
  const r = await run(raw, 128, '11');
  await sharp(r.buf, { raw: { width: r.m, height: r.m, channels: 3 } }).png().toFile('scratch/p_face11.png');
  console.log('face11', r.mn.toFixed(3), r.mx.toFixed(3), r.mean.toFixed(3));
}
// 4) grayscale ramp 128 (only R varies)
{
  const n = 128, raw = Buffer.alloc(n * n * 3);
  for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
    const i = (y * n + x) * 3;
    raw[i] = Math.round((x / (n - 1)) * 255); raw[i + 1] = 128; raw[i + 2] = 128;
  }
  const r = await run(raw, 128, '01');
  await sharp(r.buf, { raw: { width: r.m, height: r.m, channels: 3 } }).png().toFile('scratch/p_ramp.png');
  console.log('ramp', r.mn.toFixed(3), r.mx.toFixed(3), r.mean.toFixed(3));
}
// 5) source photo downscaled to 128
{
  const raw = await sharp(src).resize(128, 128, { fit: 'fill' }).removeAlpha().raw().toBuffer();
  const r = await run(raw, 128, '01');
  await sharp(r.buf, { raw: { width: r.m, height: r.m, channels: 3 } }).png().toFile('scratch/p_photo.png');
  console.log('photo', r.mn.toFixed(3), r.mx.toFixed(3), r.mean.toFixed(3));
}
await sess.release?.();
