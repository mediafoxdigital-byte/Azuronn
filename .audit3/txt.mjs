import { browser, ctx, open } from './lib.mjs';
const b = await browser();
const c = await ctx(b, 390, 900);
const p = await open(c, '/account/');
console.log(JSON.stringify(await p.evaluate(() => {
  const el = document.querySelector('.hero-text-col p');
  return { rendered: el.innerText, h: Math.round(el.getBoundingClientRect().height) };
})));
await b.close();
