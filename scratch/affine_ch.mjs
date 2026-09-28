import sharp from 'sharp';
const { data, info } = await sharp('storage/app/public/uploads/000208/12_000208_438396335.jpg').removeAlpha().raw().toBuffer({ resolveWithObject: true });
console.log('input ch', info.channels);
const png = await sharp(data, { raw: { width: info.width, height: info.height, channels: 3 } })
  .affine([[1, 0], [0, 1]], { interpolator: 'nohalo', background: { r: 128, g: 128, b: 128 } }).png().toBuffer();
const m = await sharp(png).metadata();
console.log('affine png:', m.width + 'x' + m.height, 'ch', m.channels, 'hasAlpha', m.hasAlpha);
