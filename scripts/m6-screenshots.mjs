import { chromium } from 'playwright';

const base = 'http://127.0.0.1:8000';
const out = '/cursor/stores/bc-a8ac39af-597f-480c-b190-18f19f817fd8/media/m6';

const pages = [
  ['m6-dashboard.png', '/'],
  ['m6-pl.png', '/reports/pl'],
  ['m6-month-locks.png', '/reports/month-locks'],
  ['m6-master-import.png', '/imports/master'],
];

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto(`${base}/login`);
await page.fill('input[name="login"]', 'owner');
await page.fill('input[name="password"]', 'correct-horse-battery');
await page.click('button[type="submit"]');
await page.waitForTimeout(2000);
if (page.url().includes('/login')) {
  console.error('Login failed', page.url());
  process.exit(1);
}

for (const [file, path] of pages) {
  await page.goto(`${base}${path}`);
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: `${out}/${file}`, fullPage: true });
}

await browser.close();
