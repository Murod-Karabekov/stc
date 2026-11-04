const { chromium } = require('/home/murod/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');

async function run() {
  console.log('Launching browser with Chrome...');
  const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });

  console.log('Navigating to http://127.0.0.1:8000/ ...');
  await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle' });

  // 1. Verify Header & Typography
  const title = await page.locator('h1').textContent();
  console.log('Header text:', title.trim());
  if (!title.includes('JDPU STC')) throw new Error('Header title mismatch');

  // 2. Check Portrait image
  const portrait = page.locator('img[data-homepage-target="portrait"]');
  const isPortraitVisible = await portrait.isVisible();
  console.log('Portrait visible:', isPortraitVisible);
  if (!isPortraitVisible) throw new Error('Portrait not visible');

  // 3. Check Canvas elements
  const pCanvas = page.locator('canvas[data-homepage-target="particleCanvas"]');
  const wCanvas = page.locator('canvas[data-homepage-target="waveformCanvas"]');
  console.log('Canvases visible:', (await pCanvas.isVisible()) && (await wCanvas.isVisible()));
  if (!(await pCanvas.isVisible()) || !(await wCanvas.isVisible())) {
    throw new Error('Canvases missing');
  }

  // 4. Click "Boshlash"
  const startBtn = page.locator('button:has-text("Boshlash")');
  console.log('Clicking Boshlash button...');
  await startBtn.click();
  await page.waitForTimeout(1000);

  // 5. Check Entry overlay faded
  const entryOverlay = page.locator('[data-homepage-target="entryOverlay"]');
  const isOverlayHidden = !(await entryOverlay.isVisible());
  console.log('Entry overlay hidden after click:', isOverlayHidden);
  if (!isOverlayHidden) throw new Error('Entry overlay still visible');

  // 6. Check audio controls & toggle mute
  const muteBtn = page.locator('[data-homepage-target="muteButton"]');
  await muteBtn.click();
  const ariaLabel = await muteBtn.getAttribute('aria-label');
  console.log('Mute button aria-label after toggle:', ariaLabel);
  if (ariaLabel !== 'Ovozni yoqish') throw new Error('Mute toggle failed');

  // 7. Check Mobile responsiveness
  await page.setViewportSize({ width: 375, height: 667 });
  await page.waitForTimeout(300);
  const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  console.log(`Viewport check: scrollWidth=${scrollWidth}, clientWidth=${clientWidth}`);
  if (scrollWidth > clientWidth) throw new Error('Horizontal overflow on mobile');

  console.log('Console errors recorded (excluding autoplay/fullscreen restrictions):', 
    consoleErrors.filter(e => !e.includes('play()') && !e.includes('fullscreen'))
  );

  await browser.close();
  console.log('✅ ALL HOMEPAGE VERIFICATIONS PASSED SUCCESSFULLY!');
}

run().catch(err => {
  console.error('❌ Verification failed:', err);
  process.exit(1);
});
