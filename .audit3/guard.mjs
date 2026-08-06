import fs from 'fs';
const css = fs.readFileSync('../assets/css/responsive.css', 'utf8');
let depth = 0, i = 0, line = 1;
const stack = [];
const violations = [];
const topLevelRules = [];
while (i < css.length) {
  const ch = css[i];
  if (ch === '\n') line++;
  if (css.startsWith('/*', i)) { const e = css.indexOf('*/', i); for (let k=i;k<e;k++) if(css[k]==='\n') line++; i = e + 2; continue; }
  if (ch === '@' && css.startsWith('@media', i)) {
    const brace = css.indexOf('{', i);
    stack.push({ q: css.slice(i, brace).trim(), line });
    i = brace + 1; depth++; continue;
  }
  if (ch === '{') { depth++; stack.push(null); i++; continue; }
  if (ch === '}') { depth--; stack.pop(); i++; continue; }
  // a selector starting at depth 0
  if (depth === 0 && /[.#a-zA-Z\[]/.test(ch)) {
    const brace = css.indexOf('{', i);
    if (brace === -1) break;
    topLevelRules.push({ sel: css.slice(i, brace).trim().replace(/\s+/g,' '), line });
    i = brace; continue;
  }
  i++;
}
// check every media query caps at <=1024
const queries = [...css.matchAll(/@media([^{]+)\{/g)].map(m => m[1].trim());
for (const q of queries) {
  const maxes = [...q.matchAll(/max-width:\s*(\d+)px/g)].map(m => Number(m[1]));
  if (!maxes.length || Math.max(...maxes) > 1024) {
    if (!/prefers-reduced-motion|prefers-contrast|hover|pointer/.test(q)) violations.push(q);
  }
}
console.log('TOP-LEVEL (unguarded) RULES:');
topLevelRules.forEach(r => console.log(`  line ${r.line}: ${r.sel}`));
console.log('\nMEDIA QUERIES:');
queries.forEach(q => console.log('  ' + q));
console.log('\nQUERIES NOT CAPPED AT <=1024px:', violations.length ? violations : 'none');
