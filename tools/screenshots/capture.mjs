import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';

// Start app.php first. Override CHROMIUM_PATH when using a system browser.
const browser = await chromium.launch({
  ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
});
const panels = {
  panel_selector: 'PanelSelector', twig: 'TwigPanel', eloquent: 'EloquentORMPanel',
  container: 'SlimContainerPanel', request: 'SlimRequestPanel', response: 'SlimResponsePanel',
  router: 'SlimRouterPanel', vendor_versions_panel: 'VendorVersionsPanel',
  included_files: 'IncludedFiles', phpinfo: 'PhpInfoPanel', console_panel: 'ConsolePanel',
  profiler_panel: 'ProfilerPanel',
};
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  await page.addInitScript(() => localStorage.clear());
  if (process.env.SCREENSHOT_JQUERY) {
    await page.route('**/jquery.js', route => route.fulfill({ path: process.env.SCREENSHOT_JQUERY, contentType: 'text/javascript' }));
  }
  page.on('pageerror', error => { throw error; });
  await page.goto('http://127.0.0.1:8790/');
  await page.locator('a[rel="tracy-debug-panel-SlimTracy-Helpers-PanelSelector"]').waitFor();
  await page.locator('#tracy-debug-bar').hover();
  await page.locator('#tracy-debug-bar').screenshot({ animations: 'disabled', path: fileURLToPath(new URL('../../ss/tracy_panel.png', import.meta.url)) });
  for (const [file, name] of Object.entries(panels)) {
    await page.goto('http://127.0.0.1:8790/');
    const id = `tracy-debug-panel-SlimTracy-Helpers-${name}`;
    await page.locator(`a[rel="${id}"]`).click();
    const panel = page.locator(`#${id}`);
    await panel.waitFor({ state: 'visible' });
    if (name === 'ConsolePanel') await panel.locator('.terminal .cmd').waitFor();
    await panel.locator('h1').first().hover();
    await panel.screenshot({ animations: 'disabled', path: fileURLToPath(new URL(`../../ss/${file}.png`, import.meta.url)) });
    console.log(`Captured ${file}.png`);
  }
} finally {
  await browser.close();
}
