import { browser, ctx, open } from './lib.mjs';
import fs from 'fs';
const dir = process.argv[2] || 'base';
fs.mkdirSync(dir, { recursive: true });
const PAGES = [['/index.php','home'],['/account/','account'],['/account/order/?id=ord-24001','order']];
const b = await browser();
for (const w of [1025, 1280, 1440, 1920]) {
  const c = await ctx(b, w, 1000);
  for (const [path, name] of PAGES) {
    const p = await open(c, path);
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    await p.screenshot({ path: `${dir}/${name}-${w}.png`, clip: { x:0, y:0, width:w, height:h } });
    console.log(`${dir}/${name}-${w}.png h=${h}`);
    await p.close();
  }
  await c.close();
}
await b.close();
