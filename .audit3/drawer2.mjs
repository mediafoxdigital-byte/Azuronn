import { browser, ctx, open } from './lib.mjs';
const W = Number(process.env.W || 390);
const b = await browser();
const c = await ctx(b, W, 900);
const p = await open(c, '/index.php');
await p.click('[data-mobile-nav-toggle]');
await p.waitForTimeout(500);
const toggles = await p.$$('[data-mobile-submenu-toggle]');
await toggles[0].click();
await p.waitForTimeout(600);
const r = await p.evaluate(() => {
  const pick = (sel) => [...document.querySelectorAll(sel)].slice(0,4).map(el => {
    const b = el.getBoundingClientRect(); const cs = getComputedStyle(el);
    if (b.width === 0) return null;
    return { sel, txt:(el.textContent||'').trim().slice(0,18), l:+b.left.toFixed(1), r:+b.right.toFixed(1), w:+b.width.toFixed(1),
      pad: cs.padding, mar: cs.margin, ovf: cs.overflow, ox: cs.overflowX, ms: cs.maskImage.slice(0,60), clip: cs.clipPath };
  }).filter(Boolean);
  const nav = document.querySelector('nav.mnav');
  const cs = getComputedStyle(nav);
  const megaWrap = document.querySelector('.mnav-item.is-open .mega-menu-wrapper, .mnav-item.is-open > div');
  return {
    navStyle: { w: nav.getBoundingClientRect().width, ovf: cs.overflow, ox: cs.overflowX, oy: cs.overflowY, pad: cs.padding },
    openChildren: [...document.querySelector('.mnav-item.is-open').children].map(el=>{
      const b=el.getBoundingClientRect(); const c2=getComputedStyle(el);
      return { cls:(el.className||'').toString().slice(0,40), tag:el.tagName, l:+b.left.toFixed(1), r:+b.right.toFixed(1), ovf:c2.overflow, ox:c2.overflowX, pad:c2.padding, mar:c2.margin };
    }),
    metalLink: pick('.mega-metal-link'),
    disc: pick('.mega-metal-disc'),
    imgwrap: pick('.mega-link-with-image .img-wrap'),
    showall: pick('.mega-show-all-btn'),
  };
});
console.log(JSON.stringify(r, null, 1));
await b.close();
