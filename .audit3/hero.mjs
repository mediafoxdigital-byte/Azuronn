import { browser, ctx, open, box } from './lib.mjs';
const b = await browser();
for (const w of [390, 768, 1024, 1440]) {
  const c = await ctx(b, w, 900);
  const p = await open(c, '/index.php');
  const r = await p.evaluate(() => {
    const pick = (s) => { const el = document.querySelector(s); if (!el) return null;
      const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
      return { w: Math.round(r.width), h: Math.round(r.height), y: Math.round(r.y),
        minH: cs.minHeight, pad: cs.padding, bgSize: cs.backgroundSize, bgPos: cs.backgroundPosition,
        op: cs.opacity, disp: cs.display, fs: cs.fontSize, txt: (el.textContent||'').trim().slice(0,40) }; };
    return { hero: pick('.hero'), bg: pick('.hero-bg'), ct: pick('.hero-content'),
      title: pick('.hero-title'), offer: pick('.hero-offer'), price: pick('.hero-price'),
      img: document.querySelector('.hero-bg') ? getComputedStyle(document.querySelector('.hero-bg')).backgroundImage.slice(0,90) : null };
  });
  console.log('=== w=' + w + ' ===');
  console.log(JSON.stringify(r, null, 1));
  await p.close(); await c.close();
}
await b.close();
