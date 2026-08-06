import { browser, ctx, open } from './lib.mjs';
import fs from 'fs';

fs.mkdirSync('shots', { recursive: true });
const targets = (process.argv[2] || '/:home,/account/:account,/account/order/:order').split(',');
const widths = (process.argv[3] || '390').split(',').map(Number);

const b = await browser();
for (const w of widths) {
  const c = await ctx(b, w);
  for (const t of targets) {
    const i = t.lastIndexOf(':');
    const path = t.slice(0, i), name = t.slice(i + 1);
    const p = await open(c, path);
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    await p.screenshot({ path: `shots/${name}-${w}.png`, clip: { x: 0, y: 0, width: w, height: h } });
    const title = await p.title();
    console.log(`shots/${name}-${w}.png  "${title}"`);
    await p.close();
  }
  await c.close();
}
await b.close();
