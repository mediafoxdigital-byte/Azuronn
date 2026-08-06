import { browser, ctx, open } from './lib.mjs';
const RAILS = [
  ['.category-showcase-shell', '.category-showcase-track', 'category'],
  ['.product-rail-shell', '.best-grid', 'products'],
  ['.shop-style-shell', '.shop-style-track', 'style'],
  ['.diamond-shape-layout.minimal-layout', '.diamond-shape-controls.minimal-controls', 'diamond'],
];
const b = await browser();
for (const w of [390, 768, 1024]) {
  const c = await ctx(b, w, 900);
  const p = await open(c, '/index.php');
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(900);
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(400);
  console.log(`===== viewport ${w} =====`);
  for (const [shellSel, trackSel, name] of RAILS) {
    const out = await p.evaluate(async ([sh, tr]) => {
      const shell = document.querySelector(sh), track = document.querySelector(tr);
      if (!shell || !track) return null;
      const max = track.scrollWidth - track.clientWidth;
      const read = () => {
        const cs = getComputedStyle(shell, '::after');
        return { w: cs.width, tf: cs.transform, fill: shell.style.getPropertyValue('--rail-fill'),
                 pos: shell.style.getPropertyValue('--rail-pos'),
                 stat: shell.hasAttribute('data-rail-static') };
      };
      const samples = [];
      for (const f of [0, 0.5, 1]) {
        track.scrollLeft = max * f;
        await new Promise((r) => setTimeout(r, 260));
        samples.push({ at: f, ...read() });
      }
      track.scrollLeft = 0;
      await new Promise((r) => setTimeout(r, 260));
      samples.push({ at: 'back0', ...read() });
      return { max: Math.round(max), samples };
    }, [shellSel, trackSel]);
    if (!out) { console.log(`  ${name}: absent`); continue; }
    console.log(`  ${name}  scrollable=${out.max}px`);
    for (const s of out.samples) {
      const x = (s.tf.match(/matrix\([^)]*?,\s*([-\d.]+),\s*[-\d.]+\)$/) || [])[1];
      console.log(`    at=${s.at}  thumbW=${s.w} xOffset=${x} fill=${s.fill} pos=${s.pos} static=${s.stat}`);
    }
  }
  await p.close(); await c.close();
}
await b.close();
