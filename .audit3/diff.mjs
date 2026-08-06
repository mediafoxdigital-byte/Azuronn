import { PNG } from 'pngjs';
import fs from 'fs';
let worst = 0;
for (const f of fs.readdirSync('base')) {
  if (!f.endsWith('.png')) continue;
  const a = PNG.sync.read(fs.readFileSync('base/' + f));
  const b = PNG.sync.read(fs.readFileSync('after/' + f));
  if (a.width !== b.width || a.height !== b.height) {
    console.log(`${f}  SIZE DIFF ${a.width}x${a.height} vs ${b.width}x${b.height}`); worst = 1e9; continue;
  }
  let n = 0;
  for (let i = 0; i < a.data.length; i += 4) {
    if (Math.abs(a.data[i]-b.data[i]) > 2 || Math.abs(a.data[i+1]-b.data[i+1]) > 2 || Math.abs(a.data[i+2]-b.data[i+2]) > 2) n++;
  }
  const pct = (n / (a.width*a.height) * 100).toFixed(4);
  worst = Math.max(worst, n);
  console.log(`${f}  diffPx=${n}  (${pct}%)`);
}
console.log(worst === 0 ? '\nDESKTOP PIXEL-IDENTICAL' : `\nworst=${worst}`);
