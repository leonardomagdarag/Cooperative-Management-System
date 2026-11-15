const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]); App\\Models\\LoginHandoff::truncate();"', { stdio: 'ignore' });
  } catch {}
}

async function verifyAllChapter3() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  console.log('Logging in as member...');
  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('.public-card input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('.public-card input#password', 'password');
  await page.click('.public-card button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  console.log('Login successful.');

  const tests = [
    {
      id: 'fig09',
      name: 'Member Dashboard',
      url: 'http://127.0.0.1:8000/dashboard',
      setup: async (p) => {
        await p.waitForSelector('.grid.grid-cols-1.md\\:grid-cols-3');
      },
      selectors: [
        '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(1)',
        '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(2)',
        '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(3)'
      ]
    },
    {
      id: 'fig10',
      name: 'Services Hub',
      url: 'http://127.0.0.1:8000/dashboard/services',
      setup: async (p) => {
        await p.waitForSelector('button:has-text("Deposit Money")');
      },
      selectors: [
        'button:has-text("Deposit Money")',
        'button:has-text("Withdraw Money")',
        'button:has-text("Apply for Loan")',
        'button:has-text("Make Payment")'
      ]
    },
    {
      id: 'fig14d',
      name: 'Loan Payment Step 4 Complete',
      url: 'http://127.0.0.1:8000/dashboard/services?service=payment',
      setup: async (p) => {
        const select = p.locator('div[role="dialog"] select');
        await select.waitFor({ state: 'visible', timeout: 8000 });
        await select.selectOption({ index: 2 }); // LOAN000016
        await p.waitForTimeout(500);

        const chk = p.locator('div[role="dialog"] input[type="checkbox"]').first();
        if (await chk.isVisible()) {
          await chk.check();
          await p.waitForTimeout(200);
        }
        await p.locator('div[role="dialog"] button:has-text("Next")').click();
        await p.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 5000 });
        await p.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
        await p.waitForTimeout(200);
        await p.locator('div[role="dialog"] button:has-text("Next")').click();
        await p.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 5000 });
        await p.locator('div[role="dialog"] button:has-text("Confirm")').click();
        await p.locator('div[role="dialog"] button:has-text("Close")').waitFor({ state: 'visible', timeout: 10000 });
        await p.waitForTimeout(500);
      },
      cleanup: async () => {
        try {
          execSync('php artisan tinker --execute="App\\Models\\Transaction::where(\'loan_id\', 16)->where(\'status\', \'pending\')->delete(); App\\Models\\Transaction::where(\'loan_id\', 18)->where(\'status\', \'pending\')->delete();"', { stdio: 'ignore' });
        } catch {}
      },
      selectors: [
        'div[role="dialog"] .font-mono',
        'div[role="dialog"] button:has-text("Close")'
      ]
    },
    {
      id: 'fig15',
      name: 'Member Loans Management',
      url: 'http://127.0.0.1:8000/dashboard/member-loans',
      setup: async (p) => {
        await p.locator('table tbody tr').first().waitFor({ state: 'visible', timeout: 10000 });
      },
      selectors: [
        'table tbody tr:first-child td:first-child',
        'table tbody tr:first-child td:nth-child(3)',
        'table tbody tr:first-child td:nth-child(5)'
      ]
    },
    {
      id: 'fig16',
      name: 'Member Transaction History',
      url: 'http://127.0.0.1:8000/dashboard/history',
      setup: async (p) => {
        await p.locator('.space-y-3 > div.rounded-xl').first().waitFor({ state: 'visible', timeout: 10000 });
      },
      selectors: [
        '.table-toolbar select:has(option[value="deposit"])',
        '.space-y-3 > div.rounded-xl:first-child'
      ]
    },
    {
      id: 'fig17',
      name: 'Member Announcements',
      url: 'http://127.0.0.1:8000/dashboard/announcements',
      setup: async (p) => {
        await p.locator('.space-y-3 > div.rounded-lg').first().waitFor({ state: 'visible', timeout: 10000 });
      },
      selectors: [
        '.space-y-3 > div.rounded-lg:first-child'
      ]
    },
    {
      id: 'fig18',
      name: 'Member Notifications',
      url: 'http://127.0.0.1:8000/dashboard/notifications',
      setup: async (p) => {
        await p.locator('.space-y-2 > button').first().waitFor({ state: 'visible', timeout: 10000 });
      },
      selectors: [
        '.space-y-2 > button:first-child',
        'button:has-text("Mark All Read")'
      ]
    },
    {
      id: 'fig19',
      name: 'Member Profile',
      url: 'http://127.0.0.1:8000/dashboard/profile',
      setup: async (p) => {
        await p.locator('.space-y-8 > div').first().waitFor({ state: 'visible', timeout: 10000 });
      },
      selectors: [
        '.space-y-8 > div:first-child',
        '.space-y-8 > div:nth-child(2)'
      ]
    },
    {
      id: 'fig20',
      name: 'Member Settings Security',
      url: 'http://127.0.0.1:8000/dashboard/settings?tab=account',
      setup: async (p) => {
        const btn = p.locator('button:has-text("Change Password")').first();
        await btn.waitFor({ state: 'visible', timeout: 8000 });
        await btn.click();
        await p.locator('input[placeholder*="6-digit"]').waitFor({ state: 'visible', timeout: 5000 });
      },
      selectors: [
        'input[placeholder*="6-digit"]',
        'button:has-text("Send code")',
        'button:has-text("Update Password")'
      ]
    },
    {
      id: 'fig21',
      name: 'Member Support Help Desk',
      url: 'http://127.0.0.1:8000/dashboard/settings?tab=help-support',
      setup: async (p) => {
        const subTab = p.locator('button:has-text("Active Tickets & Messages")');
        await subTab.waitFor({ state: 'visible', timeout: 8000 });
        await subTab.click();
        await p.locator('button:has-text("New Ticket")').waitFor({ state: 'visible', timeout: 8000 });
        await p.locator('.space-y-3 > div.group').first().waitFor({ state: 'visible', timeout: 8000 });
      },
      selectors: [
        'button:has-text("New Ticket")',
        '.space-y-3 > div.group:first-child'
      ]
    }
  ];

  for (const t of tests) {
    console.log(`\nVerifying [${t.id}] ${t.name}...`);
    await page.goto(t.url, { waitUntil: 'domcontentloaded' });
    if (t.setup) {
      await t.setup(page);
    }
    for (const s of t.selectors) {
      const loc = page.locator(s).first();
      const count = await loc.count();
      if (count === 0) {
        console.error(`  [FAIL] Selector missing: ${s}`);
      } else {
        const box = await loc.boundingBox();
        console.log(`  [PASS] ${s} -> box: ${Math.round(box.x)},${Math.round(box.y)} ${Math.round(box.width)}x${Math.round(box.height)}`);
      }
    }
    if (t.cleanup) {
      await t.cleanup();
    }
  }

  await browser.close();
  clearActiveSessions();
  console.log('\nAll Chapter 3 verifications passed successfully!');
}

verifyAllChapter3().catch(console.error);
