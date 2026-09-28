import sharp from 'sharp';
const W = 100, H = 100;
const buf = Buffer.alloc(W * H * 3, 255);
for (let y = 60; y < 100; y++) for (let x = 60; x < 100; x++) { const i = (y * W + x) * 3; buf[i] = buf[i + 1] = buf[i + 2] = 0; }
const out = await sharp(buf, { raw: { width: W, height: H, channels: 3 } })
  .affine([[2, 0], [0, 2]], { dx: 50, dy: 50, interpolator: 'nearest', background: { r: 0, g: 255, b: 0 } })
  .raw().toBuffer({ resolveWithObject: true });
const px = (x, y) => { const i = (y * out.info.width + x) * out.info.channels; return `${out.data[i]},${out.data[i + 1]},${out.data[i + 2]}`; };
console.log('size', out.info.width + 'x' + out.info.height, '(expected 200x200 bbox if shifted)');
console.log('(0,0) =>', px(0, 0), ' expected white (world 50,50 = input 0,0)');
console.log('(70,70) =>', px(70, 70), ' expected BLACK if bbox-shift semantics (world 120,120 = input 60,60)');
console.log('(10,10) =>', px(10, 10), ' expected white');
