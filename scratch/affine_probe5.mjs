import sharp from 'sharp';
const W = 100, H = 100;
const buf = Buffer.alloc(W * H * 3, 255);
for (let y = 60; y < 100; y++) for (let x = 60; x < 100; x++) { const i = (y * W + x) * 3; buf[i] = buf[i + 1] = buf[i + 2] = 0; }
const out = await sharp(buf, { raw: { width: W, height: H, channels: 3 } })
  .affine([[-1, 0], [0, 1]], { interpolator: 'nearest', background: { r: 0, g: 255, b: 0 } })
  .raw().toBuffer({ resolveWithObject: true });
const px = (x, y) => { const i = (y * out.info.width + x) * out.info.channels; return `${out.data[i]},${out.data[i + 1]},${out.data[i + 2]}`; };
console.log('size', out.info.width + 'x' + out.info.height);
console.log('(10,80) =>', px(10, 80), ' bbox-min=black | M-origin=green');
console.log('(90,80) =>', px(90, 80), ' bbox-min=white | M-origin=green');
