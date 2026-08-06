import { browser, ctx, open } from './lib.mjs';
const b = await browser();
const c = await ctx(b, 390);
const p = await open(c, '/account/order/?id=ord-24001');
const r = await p.evaluate(() => {
  const de = document.documentElement, vw = de.clientWidth;
  const out = [];
  for (const el of document.querySelectorAll('body *')) {
    const b = el.getBoundingClientRect();
    if (b.width === 0 || b.height === 0) continue;
    if (b.right > vw + 1 || b.left < -1) {
      const cs = getComputedStyle(el);
      out.push({ t: el.tagName.toLowerCase(), c: (el.className||'').toString().slice(0,55),
        L: Math.round(b.left), R: Math.round(b.right), W: Math.round(b.width),
        sw: el.scrollWidth, cw: el.clientWidth, ovx: cs.overflowX, pos: cs.position, ws: cs.whiteSpace });
    }
  }
  return { vw, bodySW: document.body.scrollWidth, deSW: de.scrollWidth,
    htmlOvx: getComputedStyle(de).overflowX, bodyOvx: getComputedStyle(document.body).overflowX,
    out: out.slice(0, 25) };
});
console.log(JSON.stringify(r, null, 1));
await b.close();
