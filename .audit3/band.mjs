import { browser, ctx2, open } from './lib2.mjs';
const W = Number(process.env.W||390), P = process.env.P, N = process.env.N;
const LOGIN = process.env.LOGIN === '1', CART = Number(process.env.CART||0);
const b = await browser();
const c = await ctx2(b, W, { login: LOGIN, cart: CART });
const p = await open(c, P);
const h = await p.evaluate(()=>document.documentElement.scrollHeight);
const BAND = Number(process.env.BAND||900);
for (let y = 0, i = 0; y < Math.min(h, Number(process.env.MAXH||6000)); y += BAND, i++) {
  await p.screenshot({ fullPage: true, path: `shots/${N}-b${i}.png`, clip: { x:0, y, width: W, height: Math.min(BAND, h-y) } });
  console.log(`shots/${N}-b${i}.png y=${y}`);
}
await b.close();
