import { chromium } from 'playwright';
export const BASE = process.env.BASE || 'http://localhost:8899';

export async function browser() { return chromium.launch(); }

// ctx2: gate-unlocked, but NOT signed in. Optionally seeds the cart.
export async function ctx2(b, width, { login = false, cart = 0, height = 900 } = {}) {
  const c = await b.newContext({ viewport: { width, height }, deviceScaleFactor: 1, hasTouch: width < 1025, isMobile: false });
  const p = await c.newPage();
  await p.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  const pw = await p.$('input[name="coming_soon_password"]');
  if (pw) {
    await pw.fill('azuronntest');
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(()=>{}),
      p.evaluate(() => document.querySelector('input[name="coming_soon_password"]').form.submit())]);
  }
  if (login) {
    await p.goto(BASE + '/account/login/', { waitUntil: 'domcontentloaded' });
    if (await p.$('#loginView input[name="password"]')) {
      await p.fill('#loginView input[name="email"]', 'test12345@gmail.com');
      await p.fill('#loginView input[name="password"]', 'test12345');
      await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(()=>{}),
        p.click('#loginView button[type="submit"]')]);
    }
  }
  if (cart > 0) {
    // grab product links from the shop and add-to-cart via the product page
    await p.goto(BASE + '/shop/', { waitUntil: 'load' });
    const urls = await p.evaluate(() => [...new Set([...document.querySelectorAll('a[href*="/product/"]')].map(a=>a.href))].slice(0, 6));
    let added = 0;
    for (const u of urls) {
      if (added >= cart) break;
      await p.goto(u, { waitUntil: 'load' }).catch(()=>{});
      const btn = await p.$('button[name="action"][value*="cart" i], [data-add-to-cart], button:has-text("ADD TO BAG"), button:has-text("ADD TO CART")');
      if (!btn) continue;
      await btn.click().catch(()=>{});
      await p.waitForTimeout(900);
      added++;
    }
    const n = await p.goto(BASE + '/cart/', { waitUntil: 'load' }).then(()=>p.$$eval('.cart-line-card', e=>e.length)).catch(()=>0);
    console.error(`  [seed] cart lines = ${n} (wanted ${cart})`);
  }
  await p.close();
  return c;
}

export async function open(c, path) {
  const p = await c.newPage();
  await p.goto(BASE + path, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts && document.fonts.ready).catch(()=>{});
  await p.evaluate(() => {
    for (const el of document.querySelectorAll('button,a')) if (/accept|agree|got it/i.test(el.textContent||'')) { try { el.click(); } catch {} }
    for (const el of document.querySelectorAll('*')) { const cs = getComputedStyle(el);
      if (cs.position === 'fixed' && /cookie|consent/i.test(el.className + ' ' + el.id)) el.style.display = 'none'; }
  });
  await p.waitForTimeout(350);
  return p;
}
