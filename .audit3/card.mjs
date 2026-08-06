import { browser, ctx2, open } from './lib2.mjs';
const b = await browser();
const c = await ctx2(b, Number(process.env.W||390), { login: true, cart: 2 });
const p = await open(c, '/cart/');
const r = await p.evaluate(() => {
  const chain = [];
  let el = document.querySelector('.cart-line-card');
  while (el && el !== document.documentElement) {
    const bb = el.getBoundingClientRect(); const cs = getComputedStyle(el);
    chain.push({ t: el.tagName.toLowerCase()+'.'+(el.className||'').toString().slice(0,36),
      w:+bb.width.toFixed(1), l:+bb.left.toFixed(1), bs:cs.boxSizing, minw:cs.minWidth, width:cs.width, pad:cs.padding, disp:cs.display, ovf:cs.overflow });
    el = el.parentElement;
  }
  // what inside the card can't shrink?
  const card = document.querySelector('.cart-line-card');
  const wide = [];
  for (const ch of card.querySelectorAll('*')) {
    const bb = ch.getBoundingClientRect();
    if (bb.width > 300) wide.push(`${ch.tagName.toLowerCase()}.${(ch.className||'').toString().slice(0,36)} w=${bb.width.toFixed(1)} l=${bb.left.toFixed(1)} minw=${getComputedStyle(ch).minWidth} ws=${getComputedStyle(ch).whiteSpace}`);
  }
  return { chain, wide: wide.slice(0,14) };
});
console.log(JSON.stringify(r, null, 1));
await b.close();
