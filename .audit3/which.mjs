import { browser, ctx, open } from './lib.mjs';
const b = await browser();
const c = await ctx(b, 390, 900);
const p = await open(c, '/index.php');
await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await p.waitForTimeout(1200);
await p.evaluate(() => window.scrollTo(0, 0));
await p.waitForTimeout(300);
console.log(JSON.stringify(await p.evaluate(() => {
  const probe = (sel) => {
    const el = document.querySelector(sel); if (!el) return sel + ': absent';
    const cs = getComputedStyle(el);
    return { sel, sw: el.scrollWidth, cw: el.clientWidth, ovx: cs.overflowX, disp: cs.display,
      gtc: (cs.gridTemplateColumns||'').slice(0,60), kids: el.children.length,
      fw: cs.flexWrap };
  };
  return ['.product-rail-shell','.product-rail-viewport','.best-grid',
          '.shop-style-shell','.shop-style-viewport','.shop-style-track'].map(probe);
}), null, 1));
await b.close();
