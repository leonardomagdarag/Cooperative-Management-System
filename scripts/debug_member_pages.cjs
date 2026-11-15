const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::truncate();"', { stdio: 'ignore' });
  } catch {}
}

async function debugMemberPages() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });

  const pagesToInspect = [
    { name: 'loans', url: 'http://127.0.0.1:8000/dashboard/member-loans' },
    { name: 'history', url: 'http://127.0.0.1:8000/dashboard/history' },
    { name: 'announcements', url: 'http://127.0.0.1:8000/dashboard/announcements' },
    { name: 'notifications', url: 'http://127.0.0.1:8000/dashboard/notifications' },
    { name: 'profile', url: 'http://127.0.0.1:8000/dashboard/profile' },
    { name: 'tickets', url: 'http://127.0.0.1:8000/dashboard/settings?tab=help-support&subTab=tickets' }
  ];

  for (const p of pagesToInspect) {
    await page.goto(p.url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    const text = await page.locator('main').innerText();
    console.log(`\n=== PAGE ${p.name} (${p.url}) ===`);
    console.log(text.slice(0, 300).replace(/\n+/g, ' | '));
  }

  await browser.close();
  clearActiveSessions();
}

debugMemberPages().catch(console.error);
