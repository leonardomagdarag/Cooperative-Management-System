const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

function clearActiveSessions() {
  try {
    execSync(`php "${path.resolve(__dirname, 'clear_sessions.php')}"`, { stdio: 'ignore' });
  } catch {}
}

async function debugDashboardState() {
  clearActiveSessions();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  page.on('response', resp => {
    if (resp.url().includes('/api/')) console.log('API:', resp.status(), resp.url());
  });

  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('input#password', 'password');
  await page.locator('button:has-text("Sign in")').click();
  console.log('Clicked Sign in, waiting for /dashboard (up to 30s)...');
  await page.waitForURL(/\/dashboard/, { timeout: 30000 });
  console.log('Reached /dashboard! URL:', page.url());
  console.log('Waiting for "Welcome back" header...');
  await page.locator('h1:has-text("Welcome back")').waitFor({ state: 'visible', timeout: 20000 });
  console.log('Dashboard fully loaded!');
  const card1 = page.locator('.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(1)');
  console.log('Card 1 count:', await card1.count(), 'box:', await card1.boundingBox());

  await browser.close();
  clearActiveSessions();
}

debugDashboardState().catch(console.error);
