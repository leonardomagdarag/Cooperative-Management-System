const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::query()->delete();"', { stdio: 'ignore' });
  } catch {}
}

function getLatestOtpForEmail(email) {
  const logPath = path.resolve('storage/logs/laravel.log');
  if (!fs.existsSync(logPath)) return null;
  const content = fs.readFileSync(logPath, 'utf8');
  const escaped = email.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = [...content.matchAll(new RegExp(`for ${escaped}:\\s*(\\d{6})`, 'g'))];
  if (matches.length > 0) {
    return matches[matches.length - 1][1];
  }
  return null;
}

async function loginAsMemberRobust(page) {
  clearActiveSessions();
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');

  try {
    await page.waitForURL(/\/dashboard|\/login\/approval|\/security\/verify-otp/, { timeout: 15000 });
  } catch {}

  if (page.url().includes('/login/approval')) {
    console.log('On login/approval, clicking verify by email...');
    const emailBtn = page.locator('button:has-text("Verify by email")');
    if (await emailBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await emailBtn.click();
      await page.waitForURL(/\/security\/verify-otp/, { timeout: 10000 });
    }
  }

  if (page.url().includes('/security/verify-otp')) {
    console.log('On verify-otp challenge...');
    const otpInput = page.locator('input#otp');
    await otpInput.waitFor({ state: 'visible', timeout: 10000 });
    let otp = null;
    const codeEl = page.locator('.font-mono');
    if (await codeEl.isVisible({ timeout: 2000 }).catch(() => false)) {
      otp = (await codeEl.innerText()).trim();
    }
    if (!otp) {
      for (let i = 0; i < 10; i++) {
        otp = getLatestOtpForEmail('leo.baluyot039+arjay.ramos@gmail.com');
        if (otp) break;
        await page.waitForTimeout(500);
      }
    }
    if (otp) {
      await otpInput.fill(otp);
      await page.locator('button:has-text("Verify and continue")').click();
    }
  }

  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  console.log('Successfully reached /dashboard!');
}

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await loginAsMemberRobust(page);
  await browser.close();
}

run().catch(console.error);
