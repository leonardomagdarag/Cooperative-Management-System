const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::truncate();"', { stdio: 'ignore' });
  } catch {}
}

async function testMemberPages() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });

  const tests = [
    {
      id: 'fig15',
      url: 'http://127.0.0.1:8000/dashboard/member-loans',
      selectors: ['table tbody tr:first-child td:first-child', 'table tbody tr:first-child td:nth-child(3)', 'table tbody tr:first-child td:nth-child(5)']
    },
    {
      id: 'fig16',
      url: 'http://127.0.0.1:8000/dashboard/history',
      selectors: ['select', 'table tbody tr:first-child']
    },
    {
      id: 'fig17',
      url: 'http://127.0.0.1:8000/dashboard/announcements',
      selectors: ['.space-y-4 > div:first-child, .grid > div:first-child, .bg-white.rounded-xl:first-of-type, article:first-child']
    },
    {
      id: 'fig18',
      url: 'http://127.0.0.1:8000/dashboard/notifications',
      selectors: ['.divide-y > div:first-child, article:first-child, .bg-white:first-of-type', 'button:has-text("Mark all as read")']
    },
    {
      id: 'fig19',
      url: 'http://127.0.0.1:8000/dashboard/profile',
      selectors: ['.space-y-6 > div:first-child, .bg-white.rounded-xl:first-child', '.space-y-6 > div:nth-child(2), .bg-white.rounded-xl:nth-of-type(2)']
    },
    {
      id: 'fig20',
      url: 'http://127.0.0.1:8000/dashboard/settings?tab=account',
      action: async (p) => {
        const btn = p.locator('button:has-text("Change Password")').first();
        if (await btn.isVisible()) await btn.click();
        await p.waitForTimeout(400);
      },
      selectors: ['input[placeholder*="6-digit"]', 'button:has-text("Send code")', 'button:has-text("Update Password")']
    },
    {
      id: 'fig21',
      url: 'http://127.0.0.1:8000/dashboard/settings?tab=help-support&subTab=tickets',
      selectors: ['button:has-text("New Ticket"), button:has-text("Create Ticket")', 'table, .divide-y, .space-y-4']
    }
  ];

  for (const t of tests) {
    console.log(`\nTesting ${t.id} at ${t.url}`);
    await page.goto(t.url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    if (t.action) await t.action(page);

    for (const s of t.selectors) {
      const loc = page.locator(s).first();
      const count = await loc.count();
      if (count > 0 && await loc.isVisible()) {
        const txt = (await loc.innerText()).slice(0, 30).replace(/\n/g, ' ');
        console.log(`  [PASS] ${s} -> found: "${txt}"`);
      } else {
        console.log(`  [FAIL] ${s} -> count: ${count}`);
      }
    }
  }

  await browser.close();
  clearActiveSessions();
}

testMemberPages().catch(console.error);
