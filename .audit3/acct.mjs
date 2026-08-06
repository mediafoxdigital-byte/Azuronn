import { browser, ctx, open } from './lib.mjs';
const SELS = ['.premium-account-hero','.hero-text-col','.hero-heading','.hero-actions-col-row',
  '.premium-account-main','.premium-account-col','.account-overview-band','.stat-tile','.detail-row',
  '.order-card','.order-card-top','.order-card-summary','.order-line-item','.address-card-grid',
  '.form-grid-2','.form-grid-3','.panel-header','.mini-wishlist-card','.account-orders'];
const b = await browser();
for (const w of [390, 768]) {
  const c = await ctx(b, w, 900);
  const p = await open(c, '/account/');
  console.log('===== width ' + w + ' =====');
  const r = await p.evaluate((sels) => sels.map((s) => {
    const el = document.querySelector(s); if (!el) return s + '  (absent)';
    const b = el.getBoundingClientRect(), cs = getComputedStyle(el);
    const n = document.querySelectorAll(s).length;
    return `${s} x${n}  w=${Math.round(b.width)} h=${Math.round(b.height)} | disp=${cs.display} gtc=${cs.gridTemplateColumns} dir=${cs.flexDirection} pad=${cs.padding} fs=${cs.fontSize} ovf=${cs.overflow}`;
  }), SELS);
  r.forEach((l) => console.log('  ' + l));
  await p.close(); await c.close();
}
await b.close();
