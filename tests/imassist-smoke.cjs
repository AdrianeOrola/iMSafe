const { chromium } = require(process.env.IMSAFE_PLAYWRIGHT_PATH || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
  const base = process.env.IMSAFE_TEST_URL || 'http://localhost/imsafe-php-localhost/';
  const output = path.resolve('.impeccable/review');
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    await page.goto(base + 'index.php');
    assert.equal(await page.locator('#imassistLauncher').isVisible(), true);
    const launcherShape = await page.locator('#imassistLauncher').evaluate(element => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      const tail = getComputedStyle(element, '::after');
      const inner = getComputedStyle(element.querySelector('.imassist-bot-bubble'));
      return {
        width: rect.width,
        height: rect.height,
        radius: parseFloat(style.borderTopLeftRadius),
        borderWidth: parseFloat(style.borderTopWidth),
        tailContent: tail.content,
        innerBorderWidth: parseFloat(inner.borderTopWidth),
      };
    });
    assert.ok(launcherShape.width > launcherShape.height && launcherShape.width <= 84, 'iMAssist launcher must use a compact oval chat-bubble shape.');
    assert.ok(launcherShape.radius >= launcherShape.height / 2 - 2 && launcherShape.borderWidth >= 5, 'The launcher itself must be the rounded blue speech-bubble ring.');
    assert.notEqual(launcherShape.tailContent, 'none', 'The launcher must include a speech-bubble tail.');
    assert.equal(launcherShape.innerBorderWidth, 0, 'The robot must not sit inside another box or bubble.');
    assert.equal(await page.locator('#imassistLauncher .imassist-bot-bubble').count(), 1, 'iMAssist launcher must include the message bubble.');
    assert.equal(await page.locator('#imassistLauncher .imassist-bot-head').count(), 1, 'iMAssist launcher must include the robot face.');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'iMAssist launcher causes horizontal overflow.');
    await page.locator('#imassistLauncher').screenshot({ path: path.join(output, 'imassist-chat-head.png') });

    await page.locator('#imassistLauncher').click();
    assert.equal(await page.locator('#imassistDialog').isVisible(), true);
    await page.waitForFunction(() => document.activeElement?.id === 'imassistInput');
    assert.equal(await page.locator('#imassistInput').evaluate(element => element === document.activeElement), true);
    await page.locator('#imassistDialog').screenshot({ path: path.join(output, 'imassist-mobile.png') });

    await page.locator('[data-prompt="What should I do during a flood?"]').click();
    await page.locator('.imassist-message.assistant').nth(1).waitFor();
    const floodReply = (await page.locator('.imassist-message.assistant').nth(1).innerText()).toLowerCase();
    assert.match(floodReply, /higher ground/);
    assert.match(floodReply, /floodwater/);
    assert.match(await page.locator('.imassist-message.assistant').nth(1).innerText(), /General safety guidance/i);

    await page.locator('#imassistInput').fill('Our house is on fire and someone is trapped');
    await page.locator('#imassistForm').evaluate(form => form.requestSubmit());
    await page.locator('.imassist-message.assistant').nth(2).waitFor();
    const urgent = page.locator('.imassist-message.assistant').nth(2);
    assert.equal(await urgent.evaluate(element => element.classList.contains('urgent')), true);
    assert.match(await urgent.innerText(), /Call 911/i);
    assert.equal(await urgent.locator('a[href="tel:911"]').count(), 1);

    await page.locator('#imassistInput').fill('Write a poem about my favorite shoes');
    await page.locator('#imassistForm').evaluate(form => form.requestSubmit());
    await page.locator('.imassist-message.assistant').nth(3).waitFor();
    assert.match(await page.locator('.imassist-message.assistant').nth(3).innerText(), /Disaster questions only/i);

    const csrf = await page.evaluate(() => window.imSafeAssistConfig.csrf);
    const rejected = await context.request.post(base + 'imassist-api.php', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': 'invalid' },
      data: { message: 'flood safety', history: [] },
    });
    assert.equal(rejected.status(), 403);
    const invalid = await context.request.post(base + 'imassist-api.php', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      data: { message: 'x'.repeat(801), history: [] },
    });
    assert.equal(invalid.status(), 422);
    const incidentLookup = await context.request.post(base + 'imassist-api.php', {
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      data: { message: 'Current incident reports in my area.', history: [] },
    });
    assert.equal(incidentLookup.status(), 200);
    const incidentData = await incidentLookup.json();
    assert.ok(['community_report', 'information_unavailable'].includes(incidentData.informationStatus));
    assert.doesNotMatch(JSON.stringify(incidentData), /reporter_name|contact_number|account_email|evidence_path|password/i);

    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#imassistDialog').isHidden(), true);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('#imassistLauncher').hover();
    await page.screenshot({ path: path.join(output, 'imassist-chat-head-tooltip.png') });
    await page.locator('#imassistLauncher').click();
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'iMAssist panel causes horizontal overflow.');
    await page.locator('#imassistDialog').screenshot({ path: path.join(output, 'imassist-desktop.png') });

    for (const route of ['report.php', 'track.php', 'announcements.php', 'account.php?mode=login']) {
      await page.goto(base + route);
      assert.equal(await page.locator('#imassistLauncher').count(), 1, `iMAssist is missing on ${route}`);
    }
    console.log('PASS: iMAssist safety responses, scope refusal, CSRF, global routes, keyboard, and responsive layout.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
