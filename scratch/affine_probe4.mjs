import sharp from 'sharp';
const W = 100, H = 100;
const buf = Buffer.alloc(W * H * 3, 255);
const out = await sharp(buf, { raw: { width: W, height: H, channels: 3 } })
  .affine([[1, 0.5], [0, 1]], { odx: 100, ody: 0, interpolator: 'nearest', background: { r: 0, g: 255, b: 0 } })
  .raw().toBuffer({ resolveWithObject: true });
const px = (x, y) => { const i = (y * out.info.width + x) * out.info.channels; return `${out.data[i]},${out.data[i + 1]},${out.data[i + 2]}`; };
console.log('size', out.info.width + 'x' + out.info.height);
console.log('(0,50) =>', px(0, 50), ' bbox-min-origin=white(75,50) | odx-ignored=green');
console.log('(149,99) =>', px(149, 99), ' bbox-min-origin: world(249,99)->input(199,99)?? = green');
