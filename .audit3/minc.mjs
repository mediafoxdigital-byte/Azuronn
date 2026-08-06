import { browser, ctx2, open } from './lib2.mjs';
const W = Number(process.env.W||390);
const b = await browser();
const c = await ctx2(b, W, { login: true, cart: 2 });
const p = await open(c, '/cart/');
const r = await p.evaluate(() => {
  const measure = (el) => {
    const prev = el.style.width, prevF = el.style.flex, prevMw = el.style.maxWidth;
    el.style.width = 'min-content'; el.style.maxWidth = 'min-content';
    const w = el.getBoundingClientRect().width;
    el.style.width = prev; el.style.maxWidth = prevMw; el.style.flex = prevF;
    return +w.toFixed(1);
  };
  const layout = document.querySelector('.cart-layout');
  const out = [];
  const walk = (el, depth) => {
    if (depth > 6) return;
    const mc = measure(el);
    if (mc > 320) out.push(`${'  '.repeat(depth)}${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,44)} minc=${mc}`);
    for (const ch of el.children) walk(ch, depth+1);
  };
  for (const ch of layout.children) walk(ch, 0);
  // also: copy height cause
  const copy = document.querySelector('.cart-line-copy');
  const cs = getComputedStyle(copy);
  return { over: out.slice(0, 25), copyFlex: { basis: cs.flexBasis, grow: cs.flexGrow, h: cs.height, fd: getComputedStyle(copy.parentElement).flexDirection } };
});
console.log(JSON.stringify(r, null, 1));
await b.close();
