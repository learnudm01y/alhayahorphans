import sharp from 'sharp';
const W = 100, H = 100;
const buf = Buffer.alloc(W * H * 3, 255);
for (let y = 60; y < 100; y++) for (let x = 60; x < 100; x++) { const i = (y * W + x) * 3; buf[i] = buf[i + 1] = buf[i + 2] = 0; }
const out = await sharp(buf, { raw: { width: W, height: H, channels: 3 } })
  .affine([[2, 0], [0, 2]], { dx: 0, dy: 0, interpolator: 'nearest', background: { r: 0, g: 255, b: 0 } })
  .raw().toBuffer({ resolveWithObject: true });
const px = (x, y) => { const i = (y * out.info.width + x) * out.info.channels; return `${out.data[i]},${out.data[i + 1]},${out.data[i + 2]}`; };
console.log('size', out.info.width + 'x' + out.info.height);
console.log('(40,40) =>', px(40, 40), ' [forward=255,255,255 | inverse=0,0,0]');
console.log('(90,90) =>', px(90, 90), ' [forward=255,255,255 | inverse=0,255,0 (bg)]');
console.log('(10,10) =>', px(10, 10), ' [both=255,255,255]');
