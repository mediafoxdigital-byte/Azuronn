import { browser, ctx2, open } from './lib2.mjs';
const W = Number(process.env.W||390), P = process.env.P, N = process.env.N;
const LOGIN = process.env.LOGIN==='1', CART = Number(process.env.CART||0);
const b = await browser();
const c = await ctx2(b, W, { login: LOGIN, cart: CART });
const p = await open(c, P);
// force scroll-reveal animations complete
await p.evaluate(async () => {
  for (const el of document.querySelectorAll('.reveal-in, [class*=reveal]')) el.classList.add('is-visible','revealed','in-view','active');
  window.scrollTo(0, document.body.scrollHeight);
  await new Promise(r => setTimeout(r, 900));
  window.scrollTo(0, 0);
  await new Promise(r => setTimeout(r, 500));
});
await p.waitForTimeout(700);
const h = await p.evaluate(()=>document.documentElement.scrollHeight);
const BAND = Number(process.env.BAND||880);
console.log('docH', h);
for (let y=0,i=0; y < Math.min(h, Number(process.env.MAXH||7000)); y+=BAND, i++) {
  await p.screenshot({ fullPage: true, path: `shots/${N}-b${i}.png`, clip: { x:0, y, width: W, height: Math.min(BAND, h-y) } });
  console.log(`shots/${N}-b${i}.png y=${y}`);
}
await b.close();
