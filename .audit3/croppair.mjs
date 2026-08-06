import { PNG } from 'pngjs';
import fs from 'fs';
const [f, y0, hh] = process.argv.slice(2);
for (const d of ['base','after']) {
  const src = PNG.sync.read(fs.readFileSync(`${d}/${f}`));
  const out = new PNG({ width: src.width, height: Number(hh) });
  PNG.bitblt(src, out, 0, Number(y0), src.width, Number(hh), 0, 0);
  fs.writeFileSync(`shots/${d}-${f}`, PNG.sync.write(out));
  console.log(`shots/${d}-${f}`);
}
