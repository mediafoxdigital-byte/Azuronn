import { PNG } from 'pngjs';
import fs from 'fs';
const [d1, d2] = process.argv.slice(2);
for (const f of fs.readdirSync(d1)) {
  if (!f.endsWith('.png')) continue;
  const a = PNG.sync.read(fs.readFileSync(d1 + '/' + f));
  const b = PNG.sync.read(fs.readFileSync(d2 + '/' + f));
  if (a.width!==b.width||a.height!==b.height) { console.log(`${f} SIZE`); continue; }
  let n = 0;
  for (let i = 0; i < a.data.length; i += 4)
    if (Math.abs(a.data[i]-b.data[i])>2||Math.abs(a.data[i+1]-b.data[i+1])>2||Math.abs(a.data[i+2]-b.data[i+2])>2) n++;
  console.log(`${f}  ${n}`);
}
