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
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, geolocation: { latitude: 14.5995124, longitude: 120.9842195 }, permissions: ['geolocation'] });
    const page = await context.newPage();
    await page.goto(base + 'report.php');
    await page.locator('[name="legend"][value="Orange"]').check();
    await page.locator('#specificType').selectOption('Flood');
    assert.ok(await page.locator('#particularType option', { hasText: 'Flash flood' }).count());
    await page.locator('#particularType').selectOption('Flash flood');
    assert.ok(await page.locator('#floodSection').isVisible());
    const upload = page.locator('#evidence');
    const camera = page.locator('#evidenceCamera');
    const drop = page.locator('.evidence-drop');
    assert.equal(await camera.getAttribute('capture'), 'environment');
    await upload.setInputFiles(path.resolve('assets/images/calamity-fire.jpg'));
    assert.ok(await drop.evaluate(element => element.classList.contains('has-file')));
    assert.match(await page.locator('#evidenceStatus').innerText(), /^Selected image: calamity-fire\.jpg/);
    await page.waitForFunction(() => document.getElementById('photoLatitude').value !== '');
    assert.equal(await page.locator('#photoLatitude').inputValue(), '14.5995124');
    assert.equal(await page.locator('#photoLongitude').inputValue(), '120.9842195');
    assert.match(await page.locator('#coordinateStatus').innerText(), /Coordinates attached/);
    assert.match(await page.locator('#evidenceHelp').innerText(), /up to 5 MB.*stored privately/);
    assert.ok(await page.locator('#evidencePreview').isVisible());
    await page.waitForFunction(() => { const image = document.getElementById('evidencePreviewImage'); return image.complete && image.naturalWidth > 0; });
    const previewImage = page.locator('#evidencePreviewImage');
    assert.ok((await previewImage.getAttribute('src')).startsWith('blob:'));
    let previewBox = await previewImage.boundingBox();
    assert.ok(previewBox.width <= 302 && previewBox.height <= 182, `Mobile preview is too large: ${previewBox.width}x${previewBox.height}`);
    await drop.screenshot({ path: path.join(output, 'evidence-selected-mobile.png') });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await drop.scrollIntoViewIfNeeded();
    previewBox = await previewImage.boundingBox();
    assert.ok(previewBox.width <= 302 && previewBox.height <= 202, `Desktop preview is too large: ${previewBox.width}x${previewBox.height}`);
    await drop.screenshot({ path: path.join(output, 'evidence-selected-desktop.png') });

    await upload.setInputFiles({ name: 'not-an-image.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
    assert.equal(await upload.inputValue(), '');
    assert.equal(await upload.getAttribute('aria-invalid'), 'true');
    assert.ok(await drop.evaluate(element => element.classList.contains('has-error')));
    assert.match(await page.locator('#evidenceStatus').innerText(), /valid JPG, PNG, or WEBP/);
    assert.match(await page.locator('#evidenceHelp').innerText(), /up to 5 MB.*stored privately/);
    assert.ok(await page.locator('#evidencePreview').isHidden());
    console.log('PASS: upload selection and invalid-file feedback at desktop/mobile widths.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
