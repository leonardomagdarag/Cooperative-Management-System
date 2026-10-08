import { Page, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

export const TEST_USERS = {
  admin: {
    email: 'admin@example.com',
    password: 'password',
    name: 'Quennie Joy Almazan',
    role: 'admin',
  },
  manager: {
    email: 'manager@example.com',
    password: 'password',
    name: 'Carl Michael Bigal',
    role: 'manager',
  },
  accountant: {
    email: 'accountant@example.com',
    password: 'password',
    name: 'Jasmin Latayan',
    role: 'accountant',
  },
  loan: {
    email: 'loan@example.com',
    password: 'password',
    name: 'Leanard Guiriba',
    role: 'loan',
  },
  Accountant: {
    email: 'accountant@example.com',
    password: 'password',
    name: 'Jenica Malveda',
    role: 'Accountant',
  },
  member: {
    email: 'arjay.ramos@example.com',
    password: 'password',
    name: 'Arjay Ramos',
    role: 'member',
  },
  member2: {
    email: 'crisdavid.jarabejo@example.com',
    password: 'password',
    name: 'Cris David Jarabejo',
    role: 'member',
  },
};

export function getLatestOtpForEmail(email: string): string | null {
  const logPath = path.resolve('storage/logs/laravel.log');
  if (!fs.existsSync(logPath)) return null;
  const content = fs.readFileSync(logPath, 'utf8');
  const matches = [...content.matchAll(new RegExp(`for ${email}: (\\d{6})`, 'g'))];
  if (matches.length > 0) {
    return matches[matches.length - 1][1];
  }
  return null;
}

export async function loginViaUI(page: Page, userKey: keyof typeof TEST_USERS = 'admin') {
  const user = TEST_USERS[userKey];
  await page.goto('/login');
  await expect(page.locator('input#email')).toBeVisible();

  await page.waitForTimeout(500);

  await page.locator('input#email').fill(user.email);
  await page.locator('input#password').fill(user.password);
  await page.locator('button:has-text("Sign in")').click();

  // If redirected to OTP verification challenge (e.g. member new device)
  try {
    await page.waitForURL(/\/security\/verify-otp|\/dashboard/, { timeout: 15000 });
  } catch {}

  if (page.url().includes('/security/verify-otp')) {
    await page.waitForTimeout(2000); // Wait 2s for email log to be written
    const codeEl = page.locator('.font-mono');
    let otp: string | null = null;
    if (await codeEl.isVisible()) {
      otp = (await codeEl.innerText()).trim();
    } else {
      otp = getLatestOtpForEmail(user.email);
      console.log('Extracted OTP from log:', otp);
    }
    if (otp) {
      await page.locator('input#otp').fill(otp);
      await page.locator('button:has-text("Verify and continue")').click();
      console.log('Filled OTP and clicked continue');
    } else {
      console.log('NO OTP FOUND FOR', user.email);
    }
  }

  // Expect redirection to dashboard
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
}

export async function logoutViaUI(page: Page) {
  try {
    // If a modal close button is present, click it
    const modalClose = page.locator('button[aria-label="Close dialog"], button[aria-label="Close"], button:has-text("Close")').first();
    if (await modalClose.isVisible({ timeout: 1000 }).catch(() => false)) {
      await modalClose.click().catch(() => {});
    }
  } catch {}

  try {
    await page.evaluate(async () => {
      await fetch('/api/logout', { 
        method: 'POST', 
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        } 
      });
      window.location.href = '/login';
    });
    await page.waitForURL(/\/login|\/$/, { timeout: 5000 });
  } catch {
    await page.goto('/login');
  }
}

