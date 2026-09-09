/* UI regression checks. No reports or accounts are created.
 * Set IMSAFE_PLAYWRIGHT_PATH to an installed Playwright module directory.
 * Set IMSAFE_TEST_URL to an isolated local server when exercising forms.
 */
const { chromium } = require(process.env.IMSAFE_PLAYWRIGHT_PATH || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
  const base = process.env.IMSAFE_TEST_URL || 'http://localhost/imsafe-php-localhost/';
  const out = path.resolve('.impeccable/review');
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const results = [];
  const routes = ['index.php', 'report.php', 'track.php', 'announcements.php', 'account.php?mode=login', 'account.php?mode=signup', 'dashboard.php'];
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: width === 1440 ? 1000 : 844 });
    for (const route of routes) {
      await page.goto(base + route);
      await page.evaluate(() => document.fonts.ready);
      if (route === 'report.php') await page.waitForFunction(() => !document.getElementById('region').disabled);
      const layout = await page.evaluate(() => ({
        width: innerWidth, scroll: document.documentElement.scrollWidth,
        header: document.querySelectorAll('.app-header').length,
        footer: document.querySelectorAll('.site-footer').length,
        bodyFont: getComputedStyle(document.body).fontSize,
        inputFonts: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].map(el => parseFloat(getComputedStyle(el).fontSize))
      }));
      assert.ok(layout.scroll <= width + 1, route + ' overflows at ' + width + ': ' + layout.scroll);
      assert.equal(layout.header, 1); assert.equal(layout.footer, 1);
      assert.ok(layout.inputFonts.every(size => size >= 16));
      const heading = await page.locator('h1').innerText();
      assert.ok(!/minutesmatter|reportwith|incidentwith|assessment,made/.test(heading), 'Responsive heading lost word spacing');
      const name = route.replace(/[^a-z0-9]+/gi, '-') + '-' + width;
      await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true });
      results.push({ route, width, ...layout });
    }
  }
  for (const width of [320, 720]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      await page.goto(base + route);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), route + ' narrow/zoom overflow: ' + width);
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base + 'index.php');
  const menu = page.getByRole('button', { name: 'Menu', exact: true });
  await menu.click();
  assert.equal(await menu.getAttribute('aria-expanded'), 'true');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: 'Track report', exact: true }).click();
  assert.ok(page.url().includes('track.php'));
  await menu.click(); await page.keyboard.press('Escape');
  assert.equal(await menu.getAttribute('aria-expanded'), 'false');

  await page.goto(base + 'index.php');
  assert.equal(await page.locator('.motion-toggle, .scene-image, .response-scene').count(), 0);
  assert.ok(!await page.locator('body').innerText().then(text => /Community response|DISASTER RESPONSE.*ILLUSTRATION|Pause motion/i.test(text)));
  const gallery = page.locator('.response-gallery');
  const stage = page.locator('.gallery-stage');
  const photos = page.locator('.gallery-photo');
  const hazards = ['Fire', 'Earthquake', 'Flood', 'Typhoon', 'Volcanic eruption', 'Landslide', 'Tsunami'];
  assert.equal(await photos.count(), hazards.length);
  assert.deepEqual(await photos.evaluateAll(images => images.map(img => img.dataset.hazard)), hazards);
  await page.waitForFunction(() => [...document.querySelectorAll('.gallery-photo')].every(img => img.complete && img.naturalWidth > 0));
  assert.ok(!(await photos.evaluateAll(images => images.map(img => img.src))).some(src => src.includes('disaster-response.png')));
  await gallery.scrollIntoViewIfNeeded();
  await page.mouse.move(0, 0);
  await page.waitForFunction(() => document.querySelector('.response-gallery').classList.contains('gallery-running'));
  await page.waitForFunction(() => document.querySelectorAll('.gallery-photo')[1].classList.contains('is-active'), null, { timeout: 10000 });
  await stage.hover();
  for (const index of [...hazards.keys()].slice(2).concat(0)) {
    await page.waitForFunction(index => document.querySelectorAll('.gallery-photo')[index].classList.contains('is-active'), index, { timeout: 7000 });
    assert.equal(await page.locator('.gallery-hazard').innerText(), hazards[index]);
  }
  assert.equal(await page.locator('#photo-credits, .photo-credits, .gallery-meta, .gallery-selectors').count(), 0);
  await stage.click();
  assert.equal(await stage.getAttribute('aria-pressed'), 'true');
  assert.ok(!await gallery.evaluate(el => el.classList.contains('gallery-running')));
  await stage.click();
  assert.ok(await gallery.evaluate(el => el.classList.contains('gallery-running')));
  await page.emulateMedia({ reducedMotion: 'reduce' });
  assert.equal(await photos.nth(0).evaluate(el => getComputedStyle(el).animationName), 'none');
  assert.ok(!await gallery.evaluate(el => el.classList.contains('gallery-running')));
  const currentPhoto = await photos.nth(0).evaluate(el => el.classList.contains('is-active'));
  await stage.click();
  assert.notEqual(await photos.nth(0).evaluate(el => el.classList.contains('is-active')), currentPhoto);
  // Review each actual photo crop at mobile and desktop widths, without motion.
  for (const width of [390, 1440]) {
    await page.setViewportSize({ width, height: width === 1440 ? 1000 : 844 });
    for (let slide = 0; slide < hazards.length; slide++) {
      const name = await page.locator('.gallery-hazard').innerText();
      await stage.screenshot({ path: path.join(out, 'calamity-' + name.toLowerCase().replaceAll(' ', '-') + '-' + width + '.png') });
      await stage.click();
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await stage.evaluate(el => el.blur());

  const strip = page.locator('.hero-route');
  const track = page.locator('.route-track');
  await strip.scrollIntoViewIfNeeded();
  await page.mouse.move(0, 0);
  await page.waitForFunction(() => document.querySelector('.hero-route').classList.contains('route-running'));
  const position = await track.evaluate(el => getComputedStyle(el).transform);
  await page.waitForTimeout(250);
  assert.notEqual(await track.evaluate(el => getComputedStyle(el).transform), position, 'strip moves horizontally');
  await strip.focus();
  assert.equal(await track.evaluate(el => getComputedStyle(el).animationPlayState), 'paused');
  await page.keyboard.press('Space');
  assert.equal(await strip.getAttribute('aria-pressed'), 'true');
  await page.keyboard.press('Space');
  assert.equal(await strip.getAttribute('aria-pressed'), 'false');
  await strip.evaluate(el => el.blur());
  await page.locator('.site-footer').scrollIntoViewIfNeeded();
  await page.waitForFunction(() => !document.querySelector('.hero-route').classList.contains('route-running'));
  await page.emulateMedia({ reducedMotion: 'reduce' });
  assert.equal(await track.evaluate(el => getComputedStyle(el).animationName), 'none');
  assert.ok(await page.locator('.route-sequence[aria-hidden]').isHidden());
  assert.equal(await strip.getAttribute('tabindex'), null);
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.goto(base + 'track.php');
  assert.equal(await page.locator('main img').count(), 0);
  assert.equal(await page.locator('.track-copy').evaluate(el => getComputedStyle(el, '::after').backgroundImage), 'none');

  await page.goto(base + 'report.php');
  await page.locator('[name="legend"][value="Orange"]').check();
  await page.setInputFiles('#evidence', path.resolve('assets/images/calamity-fire.jpg'));
  assert.ok(await page.locator('.evidence-drop').evaluate(element => element.classList.contains('has-file')));
  assert.match(await page.locator('#evidenceStatus').innerText(), /^Selected image: calamity-fire\.jpg/);
  assert.ok(await page.locator('#evidencePreview').isVisible());
  await page.waitForFunction(() => { const image = document.getElementById('evidencePreviewImage'); return image.complete && image.naturalWidth > 0; });
  assert.ok((await page.locator('#evidencePreviewImage').getAttribute('src')).startsWith('blob:'));
  await page.setInputFiles('#evidence', { name: 'not-an-image.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
  assert.equal(await page.locator('#evidence').inputValue(), '');
  assert.equal(await page.locator('#evidence').getAttribute('aria-invalid'), 'true');
  assert.match(await page.locator('#evidenceHelp').innerText(), /up to 5 MB · stored privately/);
  assert.ok(await page.locator('#evidencePreview').isHidden());
  const loaded = id => page.waitForFunction(id => !document.getElementById(id).disabled, id);
  await loaded('region');
  await page.selectOption('#region', '0400000000'); await loaded('province');
  await page.selectOption('#province', '0402100000'); await loaded('municipality');
  await page.selectOption('#municipality', { label: 'City of Bacoor' }); await loaded('barangay');
  await page.selectOption('#barangay', { index: 1 });
  assert.ok(await page.locator('#barangay').inputValue());
  // NCR has no province; it must still lead to city and barangay choices.
  await page.selectOption('#region', '1300000000'); await loaded('municipality');
  assert.equal(await page.locator('#province').inputValue(), 'none');
  await page.selectOption('#municipality', { label: 'City of Manila' }); await loaded('barangay');
  await page.selectOption('#barangay', { index: 1 });
  assert.ok(await page.locator('#barangay').inputValue());
  // Reset descendants immediately, even when an earlier response is still in flight.
  await page.selectOption('#region', '0400000000');
  await page.selectOption('#region', '1300000000');
  await loaded('municipality');
  assert.equal(await page.locator('#province').inputValue(), 'none');
  assert.equal(await page.locator('#barangayName').inputValue(), '');
  await page.selectOption('#municipality', { label: 'City of Manila' }); await loaded('barangay');
  await page.selectOption('#barangay', { index: 1 });
  await page.check('[name=legend][value=Red]');
  await page.selectOption('#specificType', 'Earthquake');
  await page.selectOption('#particularType', 'Ground shaking');
  await page.selectOption('#impactDetail', 'Evacuation required');
  await page.selectOption('#currentSituation', 'People are in immediate danger');
  await page.getByLabel('Rescue team', { exact: true }).check();
  const description = 'UI regression check: preserve this long emergency description after a validation error. '.repeat(4);
  await page.fill('#reporterDescription', description);
  await page.fill('#reporterName', 'UI validation test');
  // Deliberate invalid hidden name ensures the server rejects, never creating a report.
  await page.evaluate(() => { document.getElementById('barangayName').value = 'INVALID TEST LOCATION'; });
  await page.getByRole('button', { name: 'Submit rapid assessment' }).click();
  await page.waitForURL(url => url.pathname.endsWith('/report.php'));
  await page.getByRole('alert').filter({ hasText: 'does not match' }).waitFor();
  await loaded('barangay');
  assert.equal(await page.locator('#reporterDescription').inputValue(), description);
  assert.equal(await page.locator('#reporterName').inputValue(), 'UI validation test');
  assert.equal(await page.locator('#impactDetail').inputValue(), 'Evacuation required');
  assert.equal(await page.locator('#specificType').inputValue(), 'Earthquake');
  assert.equal(await page.locator('#particularType').inputValue(), 'Ground shaking');
  assert.ok(await page.getByLabel('Rescue team', { exact: true }).isChecked());
  assert.equal(await page.locator('#province').inputValue(), 'none');
  await page.screenshot({ path: path.join(out, 'report-restored-390.png'), fullPage: true });

  // Provider error and retry are simulated at the local API boundary, not in production data.
  await page.route('**/api.php?action=regions*', route => route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ error: 'Unavailable' }) }));
  await page.goto(base + 'report.php');
  await page.locator('#retryLocations').waitFor();
  assert.ok(await page.locator('#rapidForm button[type=submit]').isDisabled());
  await page.unroute('**/api.php?action=regions*');
  await page.locator('#retryLocations').click(); await loaded('region');
  assert.ok(await page.locator('#locationError').isHidden());
  assert.deepEqual(errors, []);
  fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify({ checks: results, javascriptErrors: errors, cascade: 'Cavite and NCR passed', restoration: 'passed', retry: 'passed' }, null, 2));
  console.log('PASS: seven-calamity autoplay loop, photo crops, reduced motion, routes, desktop/mobile, 320px/200%-equivalent layout, menu, Cavite/NCR cascade, stale-response protection, form restoration, provider retry.');
  await browser.close();
})().catch(error => { console.error(error); process.exitCode = 1; });
