const { chromium } = require('playwright');
(async () => {
  const D = process.argv[2]; const keys = process.argv.slice(3);
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 600, height: 1100 }, deviceScaleFactor: 2 });
  for (const k of keys) {
    await p.goto('file://' + D + '/screens.html?k=' + k, { waitUntil: 'networkidle' });
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(400);
    const el = await p.$('.stage');
    await el.screenshot({ path: D + '/out-' + k + '.png', omitBackground: true });
  }
  await b.close();
})();
