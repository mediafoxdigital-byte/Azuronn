import { browser, ctx, open } from './lib.mjs';
const PAGES = [['/index.php','home'],['/account/','account'],['/account/order/?id=ord-24001','order']];
const WIDTHS = [320, 360, 390, 414, 430, 480, 600, 768, 820, 834, 1024];
const b = await browser();
let bad = 0;
for (const w of WIDTHS) {
  const c = await ctx(b, w, 900);
  const row = [];
  for (const [path, name] of PAGES) {
    const p = await open(c, path);
    const r = await p.evaluate((vw) => {
      const de = document.documentElement;
      const out = [];
      if (de.scrollWidth > de.clientWidth) {
        for (const el of document.querySelectorAll('body *')) {
          const b = el.getBoundingClientRect();
          if (b.width === 0 || b.height === 0) continue;
          if (b.right <= vw + 1.5) continue;
          // skip anything living inside a horizontal scroll container
          let anc = el.parentElement, inScroller = false;
          while (anc && anc !== document.body) {
            const cs = getComputedStyle(anc);
            if (/auto|scroll/.test(cs.overflowX) && anc.scrollWidth > anc.clientWidth) { inScroller = true; break; }
            if (cs.overflowX === 'hidden') { inScroller = true; break; }
            anc = anc.parentElement;
          }
          if (inScroller) continue;
          out.push(`${el.tagName.toLowerCase()}.${(el.className||'').toString().slice(0,40)} ${Math.round(b.left)}..${Math.round(b.right)}`);
        }
      }
      return { sw: de.scrollWidth, cw: de.clientWidth, out: out.slice(0,5) };
    }, w);
    const fail = r.sw > r.cw && r.out.length;
    if (fail) { bad++; r.out.forEach(x => console.log(`    ${w} ${name} OVERFLOW ${x}`)); }
    row.push(`${name}:${fail ? 'FAIL' : 'ok'}(${r.sw}/${r.cw})`);
    await p.close();
  }
  console.log(`${w}\t${row.join('  ')}`);
  await c.close();
}
console.log(bad ? `\n${bad} FAILURES` : '\nNO PAGE-LEVEL OVERFLOW AT ANY WIDTH');
await b.close();
