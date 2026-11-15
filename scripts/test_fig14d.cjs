const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::truncate();"', { stdio: 'ignore' });
  } catch {}
}

async function testLoan16() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  page.on('response', async resp => {
    if (resp.url().includes('/api/services/loan-payment')) {
      console.log('LOAN PAYMENT API RESP:', resp.status(), await resp.text());
    }
  });

  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });

  await page.goto('http://127.0.0.1:8000/dashboard/services?service=payment');
  await page.waitForTimeout(1000);

  const select = page.locator('div[role="dialog"] select');
  await select.waitFor({ state: 'visible', timeout: 8000 });
  await select.selectOption({ index: 2 }); // LOAN000016
  await page.waitForTimeout(600);

  const chk = page.locator('div[role="dialog"] input[type="checkbox"]').first();
  console.log('Checkbox visible for LOAN000016:', await chk.isVisible());
  if (await chk.isVisible()) {
    await chk.check();
    await page.waitForTimeout(300);
  }

  await page.locator('div[role="dialog"] button:has-text("Next")').click();
  await page.waitForTimeout(500);

  await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
  await page.waitForTimeout(300);

  await page.locator('div[role="dialog"] button:has-text("Next")').click();
  await page.waitForTimeout(500);

  await page.locator('div[role="dialog"] button:has-text("Confirm")').click();
  await page.waitForTimeout(2000);

  console.log('Dialog inner text after confirm:', await page.locator('div[role="dialog"]').innerText());

  await browser.close();
  clearActiveSessions();
}

testLoan16().catch(console.error);
