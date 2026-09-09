const { chromium } = require(process.env.IMSAFE_PLAYWRIGHT_PATH || 'playwright');
const assert = require('node:assert/strict');

(async () => {
  const base = process.env.IMSAFE_TEST_URL || 'http://localhost/imsafe-php-localhost/';
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    const started = Date.now();
    const response = await page.goto(base + 'announcements.php', { waitUntil: 'load' });
    const elapsed = Date.now() - started;

    assert.equal(response.status(), 200);
    assert.ok(elapsed < 1500, `Announcements must render from saved data in under 1.5 seconds; measured ${elapsed} ms.`);
    assert.equal(await page.getByRole('heading', { name: 'Official-source announcements' }).isVisible(), true);
    assert.match(await page.locator('#sourceRefreshStatus').innerText(), /background|complete|could not/i);
    await page.waitForFunction(() => /complete|could not/i.test(document.getElementById('sourceRefreshStatus')?.textContent || ''), null, { timeout: 7000 });
    assert.deepEqual(errors, []);

    console.log(`PASS: Announcements rendered in ${elapsed} ms while provider refresh continued asynchronously.`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
