import { Page, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

export const TEST_USERS = {
  admin: {
    email: 'leo.baluyot039+admin@gmail.com',
    password: 'password',
    name: 'Quennie Joy Almazan',
    role: 'admin',
  },
  manager: {
    email: 'leo.baluyot039+manager@gmail.com',
    password: 'password',
    name: 'Carl Michael Bigal',
    role: 'manager',
  },
  accountant: {
    email: 'leo.baluyot039+accountant@gmail.com',
    password: 'password',
    name: 'Jasmin Latayan',
    role: 'accountant',
  },
  loan: {
    email: 'leo.baluyot039+loan@gmail.com',
    password: 'password',
    name: 'Leanard Guiriba',
    role: 'loan',
  },
  chairman: {
    email: 'leo.baluyot039+chairman@gmail.com',
    password: 'password',
    name: 'Eduardo Reyes',
    role: 'chairman',
  },
  member: {
    email: 'leo.baluyot039+arjay.ramos@gmail.com',
    password: 'password',
    name: 'Arjay Ramos',
    role: 'member',
  },
  member2: {
    email: 'leo.baluyot039+crisdavid.jarabejo@gmail.com',
    password: 'password',
    name: 'Cris David Jarabejo',
    role: 'member',
  },
};

export function getLatestOtpForEmail(email: string): string | null {
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

export async function loginViaUI(page: Page, userKey: keyof typeof TEST_USERS = 'admin') {
  const user = TEST_USERS[userKey];
  await page.goto('/login');
  await expect(page.locator('input#email')).toBeVisible({ timeout: 10000 });

  await page.locator('input#email').fill(user.email);
  await page.locator('input#password').fill(user.password);
  await page.locator('button:has-text("Sign in")').click();

  // If redirected to OTP verification challenge (e.g. member on untrusted device)
  try {
    await page.waitForURL(/\/security\/verify-otp|\/dashboard/, { timeout: 15000 });
  } catch {}

  if (page.url().includes('/security/verify-otp')) {
    const otpInput = page.locator('input#otp');
    await expect(otpInput).toBeVisible({ timeout: 10000 });

    let otp: string | null = null;
    const codeEl = page.locator('.font-mono');
    if (await codeEl.isVisible({ timeout: 2000 }).catch(() => false)) {
      otp = (await codeEl.innerText()).trim();
    }
    if (!otp) {
      for (let i = 0; i < 10; i++) {
        otp = getLatestOtpForEmail(user.email);
        if (otp) break;
        await page.waitForTimeout(500);
      }
    }
    if (otp) {
      await otpInput.fill(otp);
      await page.locator('button:has-text("Verify and continue")').click();
    }
  }

  // Expect redirection to dashboard
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
}

export async function logoutViaUI(page: Page) {
  try {
    // Dismiss any modal dialog if open
    const modalClose = page.locator('button[aria-label="Close dialog"], button[aria-label="Close"], button:has-text("Close"), button:has-text("Cancel")').first();
    if (await modalClose.isVisible({ timeout: 1000 }).catch(() => false)) {
      await modalClose.click().catch(() => {});
    }
  } catch {}

  try {
    await page.evaluate(async () => {
      const xsrfCookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
      const token = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : '';
      await fetch('/api/logout', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': token,
        },
      });
      window.location.href = '/login';
    });
    await page.waitForURL(/\/login|\/$/, { timeout: 5000 });
  } catch {
    await page.goto('/login');
  }
}
