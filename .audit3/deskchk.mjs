import { browser, ctx, open } from './lib.mjs';
const b = await browser();
for (const w of [1025, 1280, 1440, 1920]) {
  const c = await ctx(b, w, 900);
  const p = await open(c, '/index.php');
  const r = await p.evaluate(() => {
    const pick = (sel) => [...document.querySelectorAll(sel)].slice(0,3).map(el=>{
      const b=el.getBoundingClientRect(); const cs=getComputedStyle(el);
      return { w:+b.width.toFixed(1), pad:cs.padding, mar:cs.margin };
    });
    return { metal: pick('.mega-metal-link'), img: pick('.mega-link-with-image') };
  });
  console.log(w, JSON.stringify(r));
  await p.close(); await c.close();
}
await b.close();
