import { browser, ctx, open } from './lib.mjs';
const b = await browser();
const c = await ctx(b, 390, 900);
const p = await open(c, '/account/order/?id=ord-24001');
const el = await p.$('.invoice-line-card');
await el.scrollIntoViewIfNeeded();
await p.waitForTimeout(400);
const bb = await el.boundingBox();
await p.screenshot({ path: 'shots/line-390.png',
  clip: { x: 0, y: Math.max(0, bb.y - 40), width: 390, height: Math.min(300, bb.height + 90) } });
console.log('ok');
await b.close();
