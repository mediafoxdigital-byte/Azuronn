import { browser, ctx2, open } from './lib2.mjs';
const WIDTHS = [320, 360, 390, 414, 430, 480, 600, 768, 820, 834, 1024];
const b = await browser();
let bad = 0;
const scan = (p, vw) => p.evaluate((vw) => {
  const de = document.documentElement;
  const out = [], tiny = [];
  for (const el of document.querySelectorAll('body *')) {
    if (el.closest('.promo-strip, .marquee, [class*=marquee], nav.mnav, .mnav-scrim')) continue;
    const b = el.getBoundingClientRect();
    if (b.width === 0 || b.height === 0) continue;
    if (b.right > vw + 1.5) {
      let a = el.parentElement, inScroller = false;
      while (a && a !== document.body) { const cs = getComputedStyle(a);
        if (/auto|scroll|hidden/.test(cs.overflowX)) { inScroller = true; break; } a = a.parentElement; }
      if (!inScroller) out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,38)} ${b.left.toFixed(1)}..${b.right.toFixed(1)}`);
    }
  }
  for (const el of document.querySelectorAll('a,button,input[type=submit]')) {
    const b = el.getBoundingClientRect();
    if (b.width===0||b.height===0) continue;
    if (getComputedStyle(el).visibility==='hidden') continue;
    if (b.height < 28) tiny.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,30)} h=${b.height.toFixed(0)}`);
  }
  return { sw: de.scrollWidth, cw: de.clientWidth, over: out.slice(0,4), tiny: tiny.slice(0,3), h: de.scrollHeight };
}, vw);
for (const W of WIDTHS) {
  // login (logged out)
  let c = await ctx2(b, W);
  let p = await open(c, '/account/login/');
  let r = await scan(p, W);
  let f = r.sw > r.cw && r.over.length;
  if (f) { bad++; r.over.forEach(x=>console.log(`  ${W} login OVER ${x}`)); }
  r.tiny.forEach(x=>console.log(`  ${W} login tiny ${x}`));
  const loginRow = `login:${f?'FAIL':'ok'}(${r.sw}/${r.cw} h=${r.h})`;
  await p.close(); await c.close();
  // cart with items
  c = await ctx2(b, W, { login: true, cart: 2 });
  p = await open(c, '/cart/');
  r = await scan(p, W);
  f = r.sw > r.cw && r.over.length;
  if (f) { bad++; r.over.forEach(x=>console.log(`  ${W} cart OVER ${x}`)); }
  r.tiny.forEach(x=>console.log(`  ${W} cart tiny ${x}`));
  console.log(`${W}\t${loginRow}  cart:${f?'FAIL':'ok'}(${r.sw}/${r.cw} h=${r.h})`);
  await p.close(); await c.close();
}
console.log(bad ? `\n${bad} FAILURES` : '\nNO OVERFLOW ON LOGIN OR CART AT ANY WIDTH');
await b.close();
