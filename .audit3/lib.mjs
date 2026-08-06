import { chromium } from 'playwright';

export const BASE = process.env.BASE || 'http://localhost:8899';

export async function browser() {
  return chromium.launch();
}

export async function ctx(b, width, height = 900) {
  const c = await b.newContext({
    viewport: { width, height },
    deviceScaleFactor: 1,
    hasTouch: width < 1025,
    isMobile: false,
  });
  // unlock coming-soon gate
  const p = await c.newPage();
  await p.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  const pw = await p.$('input[name="coming_soon_password"]');
  if (pw) {
    await pw.fill('azuronntest');
    await Promise.all([
      p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
      p.evaluate(() => document.querySelector('input[name="coming_soon_password"]').form.submit()),
    ]);
  }
  // sign in as the test customer
  await p.goto(BASE + '/account/login/', { waitUntil: 'domcontentloaded' });
  if (await p.$('input[name="password"]')) {
    await p.fill('#loginView input[name="email"]', 'test12345@gmail.com');
    await p.fill('#loginView input[name="password"]', 'test12345');
    await Promise.all([
      p.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
      p.click('#loginView button[type="submit"]'),
    ]);
  }
  await p.close();
  return c;
}

export async function open(c, path) {
  const p = await c.newPage();
  await p.goto(BASE + path, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
  // dismiss cookie banner
  await p.evaluate(() => {
    for (const el of document.querySelectorAll('button,a')) {
      if (/accept|agree|got it/i.test(el.textContent || '')) { try { el.click(); } catch {} }
    }
    for (const el of document.querySelectorAll('*')) {
      const cs = getComputedStyle(el);
      if (cs.position === 'fixed' && /cookie|consent/i.test(el.className + ' ' + el.id)) el.style.display = 'none';
    }
  });
  await p.waitForTimeout(350);
  return p;
}

export async function overflow(p) {
  return p.evaluate(() => {
    const de = document.documentElement;
    const offenders = [];
    if (de.scrollWidth > de.clientWidth) {
      for (const el of document.querySelectorAll('body *')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0) continue;
        if (r.right > de.clientWidth + 1 || r.left < -1) {
          offenders.push({
            tag: el.tagName.toLowerCase(),
            cls: (el.className || '').toString().slice(0, 70),
            left: Math.round(r.left), right: Math.round(r.right), w: Math.round(r.width),
          });
        }
      }
    }
    return {
      sw: de.scrollWidth, cw: de.clientWidth, docH: Math.round(de.scrollHeight),
      offenders: offenders.slice(0, 12),
    };
  });
}

export async function box(p, sel) {
  return p.evaluate((s) => {
    const els = [...document.querySelectorAll(s)];
    return els.slice(0, 6).map((el) => {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        sel: s, cls: (el.className || '').toString().slice(0, 60),
        x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height),
        sw: el.scrollWidth, cw: el.clientWidth,
        disp: cs.display, pos: cs.position, fs: cs.fontSize, pad: cs.padding,
        gtc: cs.gridTemplateColumns, ws: cs.whiteSpace, of: cs.overflow,
      };
    });
  }, sel);
}
