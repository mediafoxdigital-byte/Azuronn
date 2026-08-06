import fs from 'fs';
const a = JSON.parse(fs.readFileSync('before-lc.json', 'utf8'));
const b = JSON.parse(fs.readFileSync('after-lc.json', 'utf8'));
let total = 0;
for (const key of Object.keys(a)) {
  const A = a[key], B = b[key];
  if (!B) { console.log(`${key}: MISSING in after`); continue; }
  if (A.length !== B.length) console.log(`${key}: element count ${A.length} -> ${B.length}`);
  const diffs = [];
  for (let i = 0; i < Math.min(A.length, B.length); i++) {
    if (A[i] !== B[i]) diffs.push(`  ${A[i].split('##')[0]}\n    before ${A[i].split('##').slice(1).join(' ')}\n    after  ${B[i].split('##').slice(1).join(' ')}`);
  }
  total += diffs.length;
  console.log(`${key}: ${diffs.length} diffs`);
  diffs.slice(0, 8).forEach(d => console.log(d));
}
console.log(total === 0 ? '\nDESKTOP IDENTICAL — 0 differences' : `\n${total} total differences`);
