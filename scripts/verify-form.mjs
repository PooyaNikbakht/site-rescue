// Submits the cake order form on both sites like a customer would, and
// checks what happened.
//
// BEFORE: the form says it failed (PHP mail() is not available on the host
//         and the From address is a spoofed gmail.com address).
// AFTER:  the form succeeds and the message arrives in Mailpit, the local
//         mail catcher that stands in for the bakery's inbox.
//
//   node scripts/verify-form.mjs

import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const CHROME_PATH =
  process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

async function submit(base, label) {
  const browser = await chromium.launch({ executablePath: CHROME_PATH, headless: true });
  const page = await browser.newPage();
  await page.goto(`${base}/custom-cakes/`, { waitUntil: 'networkidle' });
  const stamp = `verify-${label}-${Date.now()}`;
  await page.fill('[name="your-name"]', 'Test Customer');
  await page.fill('[name="your-email"]', 'customer@example.com');
  await page.fill('[name="your-phone"]', '416-555-0199');
  await page.fill('[name="event-date"]', '2026-12-12');
  await page.selectOption('[name="cake-size"]', { index: 1 });
  await page.fill('[name="your-message"]', `Chocolate cake for 12, no nuts. (${stamp})`);
  await page.click('.wpcf7-submit');
  const output = page.locator('.wpcf7-response-output');
  await page.waitForFunction(
    () => {
      const el = document.querySelector('.wpcf7-response-output');
      return el && el.textContent.trim().length > 0;
    },
    null,
    { timeout: 20000 },
  );
  const text = (await output.textContent()).trim();
  await browser.close();
  return { stamp, text };
}

function mailpitHas(stamp) {
  const json = execFileSync('docker', [
    'exec',
    'site-rescue-wp-after',
    'curl',
    '-s',
    `http://mailpit:8025/api/v1/search?query=${encodeURIComponent(stamp)}`,
  ]);
  const data = JSON.parse(String(json));
  return data.messages_count ?? data.total ?? 0;
}

const before = await submit('http://localhost:8881', 'before');
console.log(`BEFORE form says: "${before.text}"`);

const after = await submit('http://localhost:8882', 'after');
console.log(`AFTER form says:  "${after.text}"`);
const found = mailpitHas(after.stamp);
console.log(`AFTER message delivered to the inbox (Mailpit): ${found > 0 ? 'yes' : 'NO'}`);
process.exit(found > 0 ? 0 : 1);
