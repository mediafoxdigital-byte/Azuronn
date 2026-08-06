import fs from 'fs';
const raw = fs.readFileSync('../assets/css/responsive.css', 'utf8');
const css = raw.replace(/\/\*[\s\S]*?\*\//g, '');
const queries = [...css.matchAll(/@media([^{]+)\{/g)].map(m => m[1].trim().replace(/\s+/g,' '));
console.log('MEDIA QUERIES (comments stripped):');
queries.forEach(q => {
  const maxes = [...q.matchAll(/max-width:\s*(\d+)px/g)].map(m => Number(m[1]));
  const ok = maxes.length && Math.max(...maxes) <= 1024;
  const feature = /prefers-reduced-motion|prefers-contrast/.test(q);
  console.log(`  ${ok ? 'OK  ' : feature ? 'NEST' : 'BAD '} ${q}`);
});
console.log('\nAny min-width query? ', /min-width/.test(css) ? 'YES' : 'no');
