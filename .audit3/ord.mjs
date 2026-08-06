import { browser, ctx, open } from './lib.mjs';
const SELS = ['.order-hero','.account-hero-actions','.order-detail-layout','.order-side-stack',
  '.invoice-panel','.invoice-head','.invoice-head-meta','.invoice-grid','.invoice-info-card',
  '.invoice-line-card','.invoice-line-media','.invoice-line-copy','.invoice-line-total',
  '.invoice-summary-card','.pi-line','.pi-grand','.pi-cols','.pi-col','.invoice-address-block',
  '.invoice-doc-section','.order-request-card','.account-details','.pi-items','.pi-head'];
const b = await browser();
for (const w of [390, 768]) {
  const c = await ctx(b, w, 900);
  const p = await open(c, '/account/order/?id=ord-24001');
  console.log('===== width ' + w + ' =====');
  const r = await p.evaluate((sels) => sels.map((s) => {
    const el = document.querySelector(s); if (!el) return s + '  (absent)';
    const b = el.getBoundingClientRect(), cs = getComputedStyle(el);
    return `${s} x${document.querySelectorAll(s).length}  w=${Math.round(b.width)} h=${Math.round(b.height)} | disp=${cs.display} gtc=${cs.gridTemplateColumns} dir=${cs.flexDirection} pad=${cs.padding} fs=${cs.fontSize}`;
  }), SELS);
  r.forEach((l) => console.log('  ' + l));
  await p.close(); await c.close();
}
await b.close();
