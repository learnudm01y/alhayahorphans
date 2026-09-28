import sharp from 'sharp';
import fs from 'node:fs';
const src = process.argv[2];
const meta = await sharp(src).rotate().metadata();
console.log('source:', meta.width, 'x', meta.height);
await sharp(src).rotate().resize(300, 300, { fit: 'inside' }).blur(2.4).jpeg({ quality: 45 }).toFile('scratch/test_blurry.jpg');
const m2 = await sharp('scratch/test_blurry.jpg').metadata();
console.log('blurry test:', m2.width, 'x', m2.height, fs.statSync('scratch/test_blurry.jpg').size, 'bytes');
