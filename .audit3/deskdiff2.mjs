import fs from 'fs';
const a = JSON.parse(fs.readFileSync('before-lc.json', 'utf8'));
const b = JSON.parse(fs.readFileSync('after-lc.json', 'utf8'));
const parse = (s) => {
  const [key, vals, box] = s.split('##');
  const m = box.match(/^([\d.]+)x([\d.]+)@([-\d.]+),([-\d.]+)$/);
  return { key, vals, w: +m[1], h: +m[2], x: +m[3], y: +m[4] };
};
let styleDiffs = 0, sizeDiffs = 0, posDiffs = 0, jitter = 0, maxJitter = 0;
for (const k of Object.keys(a)) {
  const A = a[k], B = b[k];
  if (!B || A.length !== B.length) { console.log(`${k}: LENGTH MISMATCH`); continue; }
  const bad = [];
  for (let i = 0; i < A.length; i++) {
    const p = parse(A[i]), q = parse(B[i]);
    if (p.vals !== q.vals) { styleDiffs++; bad.push(`STYLE ${p.key}\n  - ${p.vals}\n  + ${q.vals}`); }
    if (p.w !== q.w || p.h !== q.h) { sizeDiffs++; bad.push(`SIZE ${p.key}: ${p.w}x${p.h} -> ${q.w}x${q.h}`); }
    if (p.x !== q.x) { posDiffs++; bad.push(`X ${p.key}: ${p.x} -> ${q.x}`); }
    if (p.y !== q.y) {
      const d = Math.abs(p.y - q.y);
      maxJitter = Math.max(maxJitter, d);
      if (d > 1) { posDiffs++; bad.push(`Y ${p.key}: ${p.y} -> ${q.y} (${d.toFixed(2)}px)`); }
      else jitter++;
    }
  }
  console.log(`${k}: ${bad.length} real diffs (${A.length} els)`);
  bad.slice(0, 10).forEach(d => console.log('  ' + d));
}
console.log(`\nstyle=${styleDiffs} size=${sizeDiffs} pos=${posDiffs}  |  sub-1px-y-jitter=${jitter} (max ${maxJitter.toFixed(2)}px)`);
console.log(styleDiffs + sizeDiffs + posDiffs === 0 ? 'DESKTOP IDENTICAL' : 'REAL DIFFERENCES PRESENT');
