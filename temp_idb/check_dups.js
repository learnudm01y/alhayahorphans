const fs = require('fs');
const p1 = JSON.parse(fs.readFileSync('../p1_dump.json', 'utf8')).data.map(i => i.id);
const p2 = JSON.parse(fs.readFileSync('../p2_dump.json', 'utf8')).data.map(i => i.id);
let p3 = fs.readFileSync('../p3_dump.json', 'utf16le');
if (p3.charCodeAt(0) === 0xFEFF) p3 = p3.slice(1);
p3 = JSON.parse(p3).map(i => i.id);

const uniqueIds = new Set([...p1, ...p2, ...p3]);
console.log('Total items fetched:', p1.length + p2.length + p3.length);
console.log('Unique items:', uniqueIds.size);
