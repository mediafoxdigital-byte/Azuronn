import { browser, ctx2, open } from './lib2.mjs';
const W = Number(process.env.W||390), P = process.env.P;
const LOGIN = process.env.LOGIN==='1', CART = Number(process.env.CART||0);
const SELS = (process.env.S||'').split('|').filter(Boolean);
const b = await browser();
const c = await ctx2(b, W, { login: LOGIN, cart: CART });
const p = await open(c, P);
const r = await p.evaluate((sels) => {
  const out = {};
  for (const s of sels) {
    out[s] = [...document.querySelectorAll(s)].slice(0,3).map(el=>{
      const b=el.getBoundingClientRect(); const cs=getComputedStyle(el);
      if (b.width===0 && b.height===0) return { hidden:true, disp:cs.display };
      return { l:+b.left.toFixed(1), r:+b.right.toFixed(1), w:+b.width.toFixed(1), h:+b.height.toFixed(1),
        disp:cs.display, pos:cs.position, gtc:cs.gridTemplateColumns, fd:cs.flexDirection, fw:cs.flexWrap,
        gap:cs.gap, pad:cs.padding, mar:cs.margin, mw:cs.maxWidth, minw:cs.minWidth, fs:cs.fontSize, ovf:cs.overflow };
    });
  }
  return out;
}, SELS);
for (const [k,v] of Object.entries(r)) console.log(k, JSON.stringify(v));
await b.close();
