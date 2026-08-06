import { browser, ctx, open } from './lib.mjs';
const b = await browser();
const c = await ctx(b, 390, 900);
const p = await open(c, '/index.php');
const shell = await p.$('.category-showcase-shell');
await shell.scrollIntoViewIfNeeded();
await p.waitForTimeout(600);
for (const [i, f] of [0, 0.5, 1].entries()) {
  await p.evaluate((frac) => {
    const t = document.querySelector('.category-showcase-track');
    t.scrollLeft = (t.scrollWidth - t.clientWidth) * frac;
  }, f);
  await p.waitForTimeout(500);
  const bb = await shell.boundingBox();
  await p.screenshot({ path: `shots/rail-${i}.png`,
    clip: { x: 0, y: Math.max(0, bb.y + bb.height - 150), width: 390, height: 160 } });
  console.log(`shots/rail-${i}.png at ${f}`);
}
await b.close();
