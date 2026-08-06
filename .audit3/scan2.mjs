import { browser, ctx, open, overflow } from './lib.mjs';
const PAGES = ['/account/order/?id=ord-24001', '/account/'];
const WIDTHS = [360, 390, 430, 768, 820, 1024];
const b = await browser();
for (const w of WIDTHS) {
  const c = await ctx(b, w);
  for (const path of PAGES) {
    const p = await open(c, path);
    const o = await overflow(p);
    console.log(`${w}\t${path}\t${o.sw > o.cw ? 'OVERFLOW' : 'ok'}\tsw=${o.sw} cw=${o.cw}`);
    for (const off of o.offenders) console.log(`    ${off.tag}.${off.cls} x=${off.left}..${off.right} w=${off.w}`);
    await p.close();
  }
  await c.close();
}
await b.close();
