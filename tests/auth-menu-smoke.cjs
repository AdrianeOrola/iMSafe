const { chromium } = require(process.env.IMSAFE_PLAYWRIGHT_PATH || 'playwright');
const { execFileSync, spawn } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const email = 'codex-auth-menu-check@example.test';
const password = 'Navigation-check-9384';
const displayName = 'Navigation Test User';
const adminEmail = 'admin@imsafe.test';
const adminPassword = crypto.randomBytes(24).toString('hex');
const mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe';
const cleanup = () => execFileSync(mysql, ['-u', 'root', '-e', `DELETE FROM imsafe_oop_local.local_users WHERE email='${email}'`]);
const rateFiles = [
  'rate-bd9c833d8266c217dfa49514bc2c45158cc9561c5851ce0a827a68917198db08.json',
  'rate-920977901ee96118f5060d9143bb4858ced232ea1a10841733d020a9c246c444.json',
  'rate-e3678795296d233d9ea5b4982760a0993f74abda00ec9d3ee7fe6e4b549400cb.json'
];
const rateSnapshots = new Map(rateFiles.map(file => {
  const target = path.resolve('storage', file);
  return [target, fs.existsSync(target) ? fs.readFileSync(target) : null];
}));
const restoreRateLimits = () => rateSnapshots.forEach((content, target) => {
  if (content === null) fs.rmSync(target, { force: true });
  else fs.writeFileSync(target, content);
});

(async () => {
  cleanup();
  const port = Number(process.env.IMSAFE_AUTH_PORT || 8018);
  const base = `http://127.0.0.1:${port}/`;
  const sessionPath = path.resolve('.impeccable/auth-test-sessions');
  const out = path.resolve('.impeccable/review');
  fs.mkdirSync(out, { recursive: true });
  fs.mkdirSync(sessionPath, { recursive: true });
  const server = spawn(process.env.IMSAFE_PHP || 'C:/xampp/php/php.exe', ['-d', `session.save_path=${sessionPath}`, '-S', `127.0.0.1:${port}`, '-t', '.'], {
    cwd: path.resolve('.'), windowsHide: true, stdio: 'ignore',
    env: { ...process.env, IMSAFE_ADMIN_EMAIL: adminEmail, IMSAFE_ADMIN_PASSWORD: adminPassword, IMSAFE_DB_USER: 'root', IMSAFE_DB_PASS: '' },
  });
  let browser;
  for (let i = 0; i < 30; i++) {
    try { const response = await fetch(base + 'index.php'); if (response.ok) break; } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(10000);
  const loginAsAdmin = async () => {
    const csrf = await page.locator('#main-content input[name=csrf_token]').inputValue();
    await page.evaluate(async ({ csrf, password, email }) => {
      const body = new URLSearchParams({ action: 'login', mode: 'login', csrf_token: csrf, email, password });
      await fetch('account.php', { method: 'POST', body, redirect: 'manual' });
    }, { csrf, password: adminPassword, email: adminEmail });
  };
  try {
    await page.goto(base + 'dashboard.php');
    assert.ok(page.url().includes('account.php?mode=login'));
    assert.equal(await page.getByLabel('Email address').count(), 1);
    assert.equal(await page.locator('.login-role-switch').count(), 0);
    await loginAsAdmin();
    await page.goto(base + 'index.php');
    assert.equal(await page.locator('.account-name').innerText(), 'Administrator');
    assert.ok(!/Logout|Admin sign out|Operations/.test(await page.locator('#primary-navigation').innerText()));
    assert.equal(await page.locator('#primary-navigation a[href="announcements.php"]').count(), 1);
    assert.equal(await page.locator('#primary-navigation a[href="dashboard.php"]').count(), 1);

    // A community sign-in replaces the active administrator identity.
    await page.goto(base + 'account.php?mode=signup');
    await page.fill('#name', displayName);
    await page.fill('#accountEmail', email);
    await page.fill('#accountPassword', password);
    await page.fill('#confirmPassword', password);
    await page.getByRole('button', { name: 'Create account' }).click();
    await page.waitForURL(/index\.php\?account=created/);
    assert.equal(await page.locator('.account-name').innerText(), displayName);
    assert.ok(!/Logout|Admin sign out|Operations/.test(await page.locator('#primary-navigation').innerText()));
    assert.equal(await page.locator('#primary-navigation a[href="announcements.php"]').count(), 1);
    assert.equal(await page.locator('#primary-navigation a[href="dashboard.php"]').count(), 0);
    await page.locator('.account-menu summary').click();
    assert.ok(await page.getByRole('button', { name: 'Sign out' }).isVisible());
    await page.keyboard.press('Escape');
    assert.ok(!await page.locator('.account-menu').evaluate(el => el.open));
    await page.screenshot({ path: path.join(out, 'community-account-menu-desktop.png') });

    await page.goto(base + 'dashboard.php');
    assert.equal(await page.locator('h1').innerText(), 'Sign in to iMSafe v2.0');

    // An administrator sign-in replaces the active community identity.
    await loginAsAdmin();
    await page.goto(base + 'index.php');
    assert.equal(await page.locator('.account-name').innerText(), 'Administrator');
    assert.equal(await page.getByRole('link', { name: 'My reports' }).count(), 0);
    assert.ok(!/Logout|Admin sign out|Operations/.test(await page.locator('#primary-navigation').innerText()));

    // Sign out through the identity menu, never through primary navigation.
    await page.locator('.account-menu summary').click();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL(/index\.php$/);
    assert.equal(await page.getByRole('link', { name: 'Login', exact: true }).count(), 1);

    // Recreate the community view to verify the narrow header arrangement.
    await page.goto(base + 'account.php?mode=login');
    await page.fill('#accountEmail', email);
    await page.fill('#accountPassword', password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    const narrowLayout = await page.evaluate(() => ({
      viewport: innerWidth,
      scrollWidth: document.documentElement.scrollWidth,
      offenders: [...document.querySelectorAll('body *')].map(element => ({
        selector: `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${element.className && typeof element.className === 'string' ? `.${element.className.trim().replace(/\s+/g, '.')}` : ''}`,
        right: Math.round(element.getBoundingClientRect().right),
        width: Math.round(element.getBoundingClientRect().width),
      })).filter(item => item.right > innerWidth + 1).slice(0, 8),
    }));
    assert.ok(narrowLayout.scrollWidth <= narrowLayout.viewport + 1, `Signed-in mobile navigation overflow: ${JSON.stringify(narrowLayout)}`);
    assert.equal(await page.locator('.account-name').innerText(), displayName);
    await page.locator('.account-menu summary').click();
    await page.screenshot({ path: path.join(out, 'community-account-menu-mobile.png') });
    console.log('PASS: one email/password login for community and admin, mutually exclusive roles, chosen-name menu, menu-only sign out, desktop/mobile navigation.');
  } finally {
    if (browser) await browser.close();
    server.kill();
    cleanup();
    restoreRateLimits();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
