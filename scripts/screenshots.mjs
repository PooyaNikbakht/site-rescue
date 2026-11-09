// Desktop and mobile screenshots of both sites, plus the top of the
// Lighthouse reports. Output: docs/screenshots/
//
//   node scripts/screenshots.mjs

import { mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium, devices } from 'playwright-core';

const ROOT = new URL('..', import.meta.url).pathname;
const OUT = join(ROOT, 'docs/screenshots');
const CHROME_PATH =
  process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
mkdirSync(OUT, { recursive: true });

const SITES = { before: 'http://localhost:8881', after: 'http://localhost:8882' };
const PAGES = { home: '/', menu: '/menu/', post: '/how-to-feed-a-sourdough-starter/', cakes: '/custom-cakes/' };
const VIEWPORTS = {
  desktop: { viewport: { width: 1366, height: 900 }, deviceScaleFactor: 1 },
  mobile: { ...devices['Pixel 7'], deviceScaleFactor: 2 },
};

const browser = await chromium.launch({ executablePath: CHROME_PATH, headless: true });
try {
  for (const [vpName, vp] of Object.entries(VIEWPORTS)) {
    for (const [site, base] of Object.entries(SITES)) {
      const context = await browser.newContext(vp);
      const page = await context.newPage();
      for (const [pageName, path] of Object.entries(PAGES)) {
        await page.goto(base + path, { waitUntil: 'networkidle', timeout: 120000 });
        await page.waitForTimeout(1500);
        // The BEFORE newsletter popup covers every page with an opaque
        // overlay. Hide it for the page shots; it gets its own shot below.
        await page.addStyleTag({ content: '.pum-overlay{display:none!important} html.pum-open,body{overflow:auto!important}' });
        await page.screenshot({ path: join(OUT, `${site}-${pageName}-${vpName}.png`), fullPage: false });
        if (pageName === 'home') {
          await page.screenshot({ path: join(OUT, `${site}-${pageName}-${vpName}-full.png`), fullPage: true });
        }
        console.log(`${site} ${pageName} ${vpName}`);
      }
      if (site === 'before') {
        // What a visitor actually sees a few seconds in.
        await page.goto(base + '/', { waitUntil: 'networkidle', timeout: 120000 });
        await page.waitForTimeout(4000);
        await page.screenshot({ path: join(OUT, `${site}-home-${vpName}-popup.png`), fullPage: false });
      }
      await context.close();
    }
  }

  // Lighthouse report headers (scores and metrics) for the home page.
  const context = await browser.newContext({ viewport: { width: 1100, height: 1150 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  for (const site of Object.keys(SITES)) {
    for (const ff of ['mobile', 'desktop']) {
      const file = join(ROOT, 'reports/lighthouse/html', `${site}-home-${ff}.html`);
      if (!existsSync(file)) continue;
      await page.goto(pathToFileURL(file).href, { waitUntil: 'load' });
      await page.waitForTimeout(1500);
      // Top of the report: the four scores and the performance metrics.
      await page.screenshot({ path: join(OUT, `lighthouse-${site}-home-${ff}.png`), clip: { x: 0, y: 0, width: 1100, height: 1150 } });
      console.log(`lighthouse ${site} home ${ff}`);
    }
  }
  await context.close();
} finally {
  await browser.close();
}
