import { browser, ctx2, open } from './lib2.mjs';
const b = await browser();
const c = await ctx2(b, Number(process.env.W||390), { login: true, cart: 2 });
const p = await open(c, '/cart/');
const r = await p.evaluate(() => {
  const mc = (el) => { const pw = el.style.width, pm = el.style.maxWidth;
    el.style.width='min-content'; el.style.maxWidth='min-content';
    const w = el.getBoundingClientRect().width; el.style.width=pw; el.style.maxWidth=pm; return +w.toFixed(1); };
  const card = document.querySelector('.cart-line-card');
  const out = [];
  const walk = (el, d) => { if (d>7) return; const v = mc(el);
    out.push(`${'  '.repeat(d)}${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,40)} minc=${v}`);
    for (const ch of el.children) walk(ch, d+1); };
  walk(card, 0);
  return out.filter(l => Number(l.split('minc=')[1]) > 240).slice(0, 30);
});
console.log(r.join('\n'));
await b.close();
