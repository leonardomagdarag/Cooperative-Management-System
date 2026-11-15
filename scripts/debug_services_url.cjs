const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::truncate();"', { stdio: 'ignore' });
  } catch {}
}

async function debugServicesUrl() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });

  await page.goto('http://127.0.0.1:8000/dashboard/services?service=payment');
  await page.waitForTimeout(3000);

  const hasDialog = await page.locator('div[role="dialog"]').count();
  console.log('Dialog count:', hasDialog);
  if (hasDialog > 0) {
    console.log('Dialog text:', await page.locator('div[role="dialog"]').innerText());
  } else {
    console.log('Main text:', await page.locator('main').innerText());
  }

  await browser.close();
  clearActiveSessions();
}

debugServicesUrl().catch(console.error);
