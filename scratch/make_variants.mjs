import sharp from 'sharp';
const src = 'storage/app/public/uploads/000208/12_000208_438396335.jpg';
const variants = [
  { n: 'v1', w: 120, blur: 3.0, q: 28 },
  { n: 'v2', w: 84, blur: 4.0, q: 22 },
  { n: 'v3', w: 150, blur: 2.2, q: 30 },
  { n: 'v4', w: 64, blur: 5.0, q: 20 },
];
for (const v of variants) {
  await sharp(src).rotate().resize(v.w, Math.round(v.w * 1.5), { fit: 'inside' }).blur(v.blur).jpeg({ quality: v.q }).toFile(`scratch/t_${v.n}.jpg`);
  console.log('made', v.n, v.w + 'px');
}
