import { browser, ctx, open } from './lib.mjs';
const b = await browser();
for (const W of [320, 360, 390, 430, 768, 820, 1024]) {
  const c = await ctx(b, W, 900);
  const p = await open(c, '/index.php');
  await p.click('[data-mobile-nav-toggle]');
  await p.waitForTimeout(400);
  const n = await p.$$eval('[data-mobile-submenu-toggle]', e => e.length);
  const bad = [];
  for (let i = 0; i < n; i++) {
    const t = await p.$$('[data-mobile-submenu-toggle]');
    await t[i].click();
    await p.waitForTimeout(450);
    const res = await p.evaluate(() => {
      const item = document.querySelector('.mnav-item.is-open');
      if (!item) return [];
      const drop = item.querySelector('.mega-drop');
      if (!drop) return [];
      const d = drop.getBoundingClientRect();
      const out = [];
      for (const el of drop.querySelectorAll('*')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        if (r.left < d.left - 0.5 || r.right > d.right + 0.5)
          out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,34)} ${r.left.toFixed(1)}..${r.right.toFixed(1)} vs ${d.left.toFixed(1)}..${d.right.toFixed(1)}`);
      }
      return out.slice(0, 4);
    });
    if (res.length) bad.push(`  [${i}] ` + res.join('\n       '));
    await t[i].click();
    await p.waitForTimeout(250);
  }
  console.log(`${W}\t${bad.length ? 'CLIPPED\n' + bad.join('\n') : `ok (${n} submenus)`}`);
  await p.close(); await c.close();
}
await b.close();
