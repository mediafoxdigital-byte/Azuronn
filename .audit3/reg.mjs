import { browser, ctx2, open } from './lib2.mjs';
const b = await browser();
for (const W of (process.env.W||'390').split(',').map(Number)) {
  const c = await ctx2(b, W);
  const p = await open(c, '/account/login/');
  await p.click('.premium-auth-tab:nth-of-type(2)');
  await p.waitForTimeout(700);
  const r = await p.evaluate((vw) => {
    const out = [];
    for (const el of document.querySelectorAll('#registerView *')) {
      const b = el.getBoundingClientRect();
      if (b.width===0||b.height===0) continue;
      if (b.right > vw+1 || b.left < -1) out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,30)} ${b.left.toFixed(1)}..${b.right.toFixed(1)}`);
    }
    const row = document.querySelector('#registerView .premium-field-row');
    return { over: out.slice(0,5), rowGtc: row ? getComputedStyle(row).gridTemplateColumns : null,
      inputW: [...document.querySelectorAll('#registerView input')].slice(0,3).map(i=>+i.getBoundingClientRect().width.toFixed(1)) };
  }, W);
  const h = await p.evaluate(()=>document.documentElement.scrollHeight);
  await p.screenshot({ fullPage: true, path: `shots/reg-${W}.png`, clip: { x:0,y:0,width:W,height:Math.min(h,900) } });
  console.log(W, JSON.stringify(r));
  await p.close(); await c.close();
}
await b.close();
