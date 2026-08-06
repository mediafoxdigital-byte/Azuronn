import { browser, ctx2, open } from './lib2.mjs';
const b = await browser();
for (const [W, path, login, cart] of [[390,'/account/login/',false,0],[390,'/cart/',true,2],[1024,'/cart/',true,2]]) {
  const c = await ctx2(b, W, { login, cart });
  const p = await open(c, path);
  const r = await p.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('a,button')) {
      const b = el.getBoundingClientRect();
      if (b.width===0||b.height===0) continue;
      if (getComputedStyle(el).visibility==='hidden') continue;
      if (b.height >= 28) continue;
      out.push({ txt:(el.textContent||'').trim().slice(0,28), cls:(el.className||'').toString().slice(0,34),
        parent:(el.parentElement.className||'').toString().slice(0,34), h:+b.height.toFixed(0), inHeader: !!el.closest('header,.mnav,footer') });
    }
    return out.filter(x=>!x.inHeader).slice(0,8);
  });
  console.log(W, path, JSON.stringify(r, null, 1));
  await p.close(); await c.close();
}
await b.close();
