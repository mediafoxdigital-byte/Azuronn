import { browser, ctx, open } from './lib.mjs';
const W = Number(process.env.W || 390);
const b = await browser();
const c = await ctx(b, W, 900);
const p = await open(c, '/index.php');
await p.click('[data-mobile-nav-toggle]');
await p.waitForTimeout(500);
// expand the first submenu (Engagement Rings)
const toggles = await p.$$('[data-mobile-submenu-toggle]');
await toggles[0].click();
await p.waitForTimeout(600);
const r = await p.evaluate(() => {
  const nav = document.querySelector('nav.mnav');
  const nr = nav.getBoundingClientRect();
  const pick = (sel) => [...document.querySelectorAll(sel)].slice(0,6).map(el => {
    const b = el.getBoundingClientRect(); const cs = getComputedStyle(el);
    if (b.width === 0) return null;
    return { sel, cls: (el.className||'').toString().slice(0,50), l: +b.left.toFixed(1), r: +b.right.toFixed(1), w: +b.width.toFixed(1),
      pad: cs.padding, mar: cs.margin, disp: cs.display, gtc: cs.gridTemplateColumns, ox: cs.overflowX, tx: cs.transform };
  }).filter(Boolean);
  const megaEl = document.querySelector('.mnav-item.has-mega .mega-menu, .mnav-item.has-mega .mega-menu-wrapper');
  return {
    nav: { l: +nr.left.toFixed(1), r: +nr.right.toFixed(1), w: +nr.width.toFixed(1), sw: nav.scrollWidth, cw: nav.clientWidth, pad: getComputedStyle(nav).padding },
    pill: pick('.luxury-mnav-pill'),
    item: pick('.mnav-item'),
    mega: pick('.mega-menu, .mega-menu-wrapper, .mega-menu-inner'),
    col: pick('.mega-col'),
    title: pick('.mega-col-title'),
    link: pick('.mega-link-with-image'),
  };
});
console.log(JSON.stringify(r, null, 1));
await p.screenshot({ path: `shots/drawer-${W}.png`, clip: { x:0, y:0, width: W, height: 900 } });
await b.close();
