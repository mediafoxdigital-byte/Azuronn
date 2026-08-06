import { browser, ctx, open, overflow } from './lib.mjs';
const PAGES = [['/index.php','home'],['/account/','account'],['/account/order/?id=ord-24001','order']];
const WIDTHS = [320, 360, 390, 414, 430, 480, 600, 768, 820, 834, 1024];
const b = await browser();
let bad = 0;
for (const w of WIDTHS) {
  const c = await ctx(b, w, 900);
  const row = [];
  for (const [path, name] of PAGES) {
    const p = await open(c, path);
    const o = await overflow(p);
    // real offenders only: the promo marquee is intentionally clipped by body overflow-x
    const off = await p.evaluate((vw) => {
      const out = [];
      for (const el of document.querySelectorAll('body *')) {
        if (el.closest('.promo-strip, .reviews-marquee-container, .social-gallery-marquee, .marquee')) continue;
        // the off-canvas drawer is parked off-screen-left by design until opened
        if (el.closest('nav.mnav, .mnav-scrim')) continue;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        if (r.right > vw + 1.5)
          out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,40)} ${Math.round(r.left)}..${Math.round(r.right)}`);
      }
      return out.slice(0, 5);
    }, w);
    // tap targets
    const small = await p.evaluate(() => {
      const out = [];
      for (const el of document.querySelectorAll('a,button,input[type=submit]')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        if (getComputedStyle(el).visibility === 'hidden') continue;
        if (r.height < 30) out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,32)} h=${Math.round(r.height)}`);
      }
      return out.slice(0, 4);
    });
    const flag = (o.sw > o.cw || off.length) ? 'FAIL' : 'ok';
    if (flag === 'FAIL') bad++;
    row.push(`${name}:${flag}`);
    if (off.length) off.forEach((x) => console.log(`    ${w} ${name} OVERFLOW ${x}`));
    if (small.length) small.forEach((x) => console.log(`    ${w} ${name} small-tap ${x}`));
    await p.close();
  }
  console.log(`${w}\t${row.join('  ')}`);
  await c.close();
}
console.log(bad ? `\n${bad} FAILURES` : '\nNO OVERFLOW AT ANY WIDTH');
await b.close();
