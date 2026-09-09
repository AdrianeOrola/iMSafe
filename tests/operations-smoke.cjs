const { chromium } = require(process.env.IMSAFE_PLAYWRIGHT_PATH || 'playwright');
const { spawn } = require('node:child_process');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
(async () => {
  const port = Number(process.env.IMSAFE_OPERATIONS_PORT || 8017);
  const testBase = `http://127.0.0.1:${port}/`;
  const password = crypto.randomBytes(24).toString('hex');
  const sessionPath = path.resolve('.impeccable/test-sessions');
  fs.mkdirSync(sessionPath, { recursive: true });
  const rateFile = path.resolve('storage/rate-bd9c833d8266c217dfa49514bc2c45158cc9561c5851ce0a827a68917198db08.json');
  const rateSnapshot = fs.existsSync(rateFile) ? fs.readFileSync(rateFile) : null;
  const evidenceFile = path.resolve('storage/evidence/' + 'a'.repeat(40) + '.jpg');
  const evidenceSnapshot = fs.existsSync(evidenceFile) ? fs.readFileSync(evidenceFile) : null;
  fs.mkdirSync(path.dirname(evidenceFile), { recursive: true });
  fs.copyFileSync(path.resolve('assets/images/calamity-flood.jpg'), evidenceFile);
  const server = spawn(process.env.IMSAFE_PHP || 'C:/xampp/php/php.exe', ['-d', `session.save_path=${sessionPath}`, '-S', `127.0.0.1:${port}`, '-t', '.', 'tests/operations-router.php'], {
    cwd: path.resolve('.'), windowsHide: true, stdio: 'ignore',
    env: { ...process.env, IMSAFE_ADMIN_EMAIL: 'admin@imsafe.test', IMSAFE_ADMIN_PASSWORD: password, IMSAFE_DB_USER: 'root', IMSAFE_DB_PASS: '' }
  });
  let browser;
  try {
    for (let i = 0; i < 30; i++) {
      try { const response = await fetch(testBase + 'dashboard.php'); if (response.ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await page.goto(testBase + 'dashboard.php');
    await page.getByLabel('Email address').fill('admin@imsafe.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.getByRole('heading', { name: 'Incident monitor', exact: true }).waitFor({ timeout: 60000 });
    assert.ok(await page.getByRole('heading', { name: 'Response overview', exact: true }).isVisible());
    assert.ok(await page.getByRole('heading', { name: 'Seven-day activity', exact: true }).isVisible());
    assert.equal(await page.getByRole('heading', { name: 'DOST-PAGASA outlook', exact: true }).count(), 0, 'External feeds are removed from the incident dashboard');
    const emptyQueue = page.getByText('No incident reports yet', { exact: true });
    assert.ok(await emptyQueue.isVisible().catch(() => false) || await page.locator('.incident-table').isVisible(), 'Dashboard shows either its empty state or incident queue');
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.screenshot({ path: '.impeccable/review/operations-live-' + width + '.png', fullPage: true });
    }
    await page.goto(testBase + 'operations-preview');
    await page.getByRole('heading', { name: 'Incident monitor', exact: true }).waitFor();
    assert.ok(await page.getByText('IMS-SYNTHETIC-TESTONLY', { exact: true }).isVisible());
    await page.getByText('Export reports', { exact: true }).click();
    assert.ok(await page.getByRole('link', { name: /Excel workbook/ }).isVisible());
    assert.ok(await page.getByRole('link', { name: /PDF report/ }).isVisible());
    await page.getByText('View assessment details', { exact: true }).click();
    assert.ok(await page.getByText('Waist-deep — 19–36 in (1.6–3.0 ft)', { exact: true }).isVisible());
    const evidenceImage = page.locator('.admin-evidence img');
    await evidenceImage.waitFor();
    await page.waitForFunction(image => image.complete && image.naturalWidth > 0, await evidenceImage.elementHandle());
    assert.equal(await page.getByRole('link', { name: 'Open uploaded photo', exact: true }).count(), 0);
    await page.getByText('Review and update', { exact: true }).click();
    assert.ok(await page.getByRole('button', { name: 'Save update' }).isVisible());
    for (const width of [1440, 1280, 390, 320]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.evaluate(async () => { await document.fonts.ready; window.scrollTo(0, 0); document.activeElement?.blur(); });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'operations overflow at ' + width);
      await page.screenshot({ path: '.impeccable/review/operations-fixture-' + width + '.png', fullPage: true });
    }
    await page.goto(testBase + 'announcements-preview');
    await page.getByRole('heading', { name: 'Official-source announcements', exact: true }).waitFor();
    assert.equal(await page.locator('#primary-navigation a[href="announcements.php"]').count(), 1);
    assert.equal(await page.locator('#primary-navigation a[href="dashboard.php"]').count(), 0, 'Public announcements must not expose the admin dashboard');
    assert.equal(await page.locator('#primary-navigation a[href="account.php?mode=signup"]').count(), 1, 'Guests keep public account actions');
    assert.equal(await page.locator('#primary-navigation a[href="account.php?mode=login"]').count(), 1, 'Guests can sign in from announcements');
    assert.ok(await page.getByRole('heading', { name: 'Philippine weather outlook', exact: true }).isVisible());
    assert.ok(await page.getByRole('heading', { name: 'Global hazard signals', exact: true }).isVisible());
    assert.ok(await page.getByRole('heading', { name: 'Official emergency information directory', exact: true }).isVisible());
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.evaluate(async () => { await document.fonts.ready; window.scrollTo(0, 0); });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'announcements overflow at ' + width);
      await page.screenshot({ path: '.impeccable/review/announcements-fixture-' + width + '.png', fullPage: true });
    }
    console.log('PASS: readable admin dashboard, separated announcements workspace, synthetic queue and signed-in navigation at desktop/mobile widths.');
  } finally {
    if (browser) await browser.close();
    server.kill();
    if (rateSnapshot === null) fs.rmSync(rateFile, { force: true });
    else fs.writeFileSync(rateFile, rateSnapshot);
    if (evidenceSnapshot === null) fs.rmSync(evidenceFile, { force: true });
    else fs.writeFileSync(evidenceFile, evidenceSnapshot);
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
