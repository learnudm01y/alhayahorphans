import sharp from 'sharp';
const W = 100, H = 100;
const buf = Buffer.alloc(W * H * 3, 255);
const out = await sharp(buf, { raw: { width: W, height: H, channels: 3 } })
  .affine([[1, 0.5], [0, 1]], { interpolator: 'nearest', background: { r: 0, g: 255, b: 0 } })
  .raw().toBuffer({ resolveWithObject: true });
const px = (x, y) => { const i = (y * out.info.width + x) * out.info.channels; return `${out.data[i]},${out.data[i + 1]},${out.data[i + 2]}`; };
console.log('size', out.info.width + 'x' + out.info.height);
console.log('(75,50) =>', px(75, 50), ' forward=white(255,255,255) | inverse=green bg');
console.log('(10,50) =>', px(10, 50), ' forward=green bg | inverse=white');
