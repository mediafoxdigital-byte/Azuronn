import { browser, ctx2, open } from './lib2.mjs';
import fs from 'fs';
fs.mkdirSync('shots', { recursive: true });
const b = await browser();
for (const W of (process.env.W || '390').split(',').map(Number)) {
  // login page: logged OUT
  let c = await ctx2(b, W);
  let p = await open(c, '/account/login/');
  let h = await p.evaluate(()=>document.documentElement.scrollHeight);
  await p.screenshot({ path: `shots/login-${W}.png`, clip: {x:0,y:0,width:W,height:Math.min(h,3000)} });
  console.log(`login-${W} docH=${h} title="${await p.title()}"`);
  await p.close(); await c.close();
  // cart page: logged in with items
  c = await ctx2(b, W, { login: true, cart: 2 });
  p = await open(c, '/cart/');
  h = await p.evaluate(()=>document.documentElement.scrollHeight);
  const lines = await p.$$eval('.cart-line-card', e=>e.length);
  await p.screenshot({ path: `shots/cart-${W}.png`, clip: {x:0,y:0,width:W,height:Math.min(h,4200)} });
  console.log(`cart-${W} docH=${h} lines=${lines}`);
  await p.close(); await c.close();
}
await b.close();
