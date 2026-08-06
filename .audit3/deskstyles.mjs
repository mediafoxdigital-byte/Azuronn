import { browser, ctx2, open } from './lib2.mjs';
import fs from 'fs';
const OUT = process.argv[2];
const PROPS = ['display','position','width','height','margin','padding','fontSize','lineHeight','color',
  'backgroundColor','borderWidth','borderColor','borderRadius','flexDirection','flexWrap','flex','gap',
  'gridTemplateColumns','justifyContent','alignItems','minWidth','maxWidth','minHeight','textAlign','overflow'];
const b = await browser();
const snap = {};
for (const W of [1025, 1280, 1440, 1920]) {
  for (const [path, name, login, cart] of [['/account/login/','login',false,0], ['/cart/','cart',true,2]]) {
    const c = await ctx2(b, W, { login, cart });
    const p = await open(c, path);
    snap[`${name}@${W}`] = await p.evaluate((props) => {
      const rows = [];
      const els = [...document.querySelectorAll('body *')];
      els.forEach((el, i) => {
        const cs = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        const key = `${i}:${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,44)}`;
        const vals = props.map(pr => cs[pr]).join('|');
        rows.push(`${key}##${vals}##${r.width.toFixed(1)}x${r.height.toFixed(1)}@${r.left.toFixed(1)},${r.top.toFixed(1)}`);
      });
      return rows;
    }, PROPS);
    await p.close(); await c.close();
  }
}
fs.writeFileSync(OUT, JSON.stringify(snap));
console.log('wrote', OUT, Object.entries(snap).map(([k,v])=>`${k}=${v.length}`).join(' '));
await b.close();
