import { browser, ctx, open } from './lib.mjs';
import fs from 'fs';
const out = process.argv[2];
const PAGES = [['/index.php','home'],['/account/','account'],['/account/order/?id=ord-24001','order']];
const PROPS = ['display','position','width','height','padding','margin','flexDirection','flexWrap',
  'gridTemplateColumns','fontSize','lineHeight','opacity','background-image','aspectRatio','minHeight',
  'overflow','transform','maxWidth','justifyContent','alignItems','gap','color','backgroundColor'];
const b = await browser();
const res = {};
for (const w of [1025, 1280, 1440, 1920]) {
  const c = await ctx(b, w, 1000);
  for (const [path, name] of PAGES) {
    const p = await open(c, path);
    res[`${name}@${w}`] = await p.evaluate((props) => {
      const map = {};
      document.querySelectorAll('body *').forEach((el, i) => {
        const cs = getComputedStyle(el);
        const key = i + ':' + el.tagName + '.' + (el.className||'').toString().slice(0,50);
        map[key] = props.map((pr) => cs[pr] ?? cs.getPropertyValue(pr)).join('|');
        for (const pe of ['::before','::after']) {
          const q = getComputedStyle(el, pe);
          if (q.content && q.content !== 'none')
            map[key + pe] = props.map((pr) => q[pr] ?? q.getPropertyValue(pr)).join('|') + '|' + q.content;
        }
      });
      return map;
    }, PROPS);
    await p.close();
  }
  await c.close();
}
fs.writeFileSync(out, JSON.stringify(res));
console.log('wrote ' + out);
await b.close();
