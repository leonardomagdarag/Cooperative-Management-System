import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('2. Authentication, Roles & Dashboards', () => {
  test('2.1 Unauthenticated visit to /dashboard redirects to /login', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/, { timeout: 10000 });
  });

  test('2.2 Admin logs in and views Admin Overview dashboard', async ({ page }) => {
    await loginViaUI(page, 'admin');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Admin Overview elements
    await expect(page.locator('text=/System Administrator|Cooperative-wide overview/i').first()).toBeVisible({ timeout: 10000 });
    
    // Sidebar contains Admin specific sections
    await expect(page.locator('nav a[href="/dashboard/officers"], nav a[href="/dashboard/members"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/ledger"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/settings"]').first()).toBeVisible();

    await logoutViaUI(page);
  });

  test('2.3 Manager logs in and views Manager Overview dashboard', async ({ page }) => {
    await loginViaUI(page, 'manager');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Manager Overview elements
    await expect(page.locator('text=/Operations overview|Manager/i').first()).toBeVisible({ timeout: 10000 });
    
    // Manager has access to members, loans, reports
    await expect(page.locator('nav a[href="/dashboard/members"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/loans"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/reports"]').first()).toBeVisible();

    await logoutViaUI(page);
  });

  test('2.4 Accountant logs in and views Treasurer/Accountant Overview dashboard', async ({ page }) => {
    await loginViaUI(page, 'accountant');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Accountant / Treasurer elements
    await expect(page.locator('text=/Treasurer Overview|Financial Summary|Cash Flow/i').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('nav a[href="/dashboard/ledger"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/expenses"]').first()).toBeVisible();

    await logoutViaUI(page);
  });

  test('2.5 Loan Officer logs in and views Loan Overview dashboard', async ({ page }) => {
    await loginViaUI(page, 'loan');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Loan Overview elements
    await expect(page.locator('text=/Loan Portfolio|Active Loans|Loan Status/i').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('nav a[href="/dashboard/loans"]').first()).toBeVisible();

    await logoutViaUI(page);
  });

  test('2.6 Accountant logs in and views Accountant Overview dashboard', async ({ page }) => {
    await loginViaUI(page, 'Accountant');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Accountant Overview elements
    await expect(page.locator('text=/Accountant Overview|Membership Applications|Announcements/i').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('nav a[href="/dashboard/members"]').first()).toBeVisible();

    await logoutViaUI(page);
  });

  test('2.7 Member logs in (completes OTP challenge if prompted) and views Member Overview', async ({ page }) => {
    await loginViaUI(page, 'member');
    await expect(page).toHaveURL(/\/dashboard/);

    // Verify Member Overview elements
    await expect(page.locator('text=/Savings|Share Capital|Good /i').first()).toBeVisible({ timeout: 10000 });

    // Member sidebar navigation options
    await expect(page.locator('nav a[href="/dashboard/services"]').first()).toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/history"]').first()).toBeVisible();

    // Member should NOT have admin/officer links in nav
    await expect(page.locator('nav a[href="/dashboard/officers"]')).toHaveCount(0);
    await expect(page.locator('nav a[href="/dashboard/branches"]')).toHaveCount(0);
    await expect(page.locator('nav a[href="/dashboard/logs"]')).toHaveCount(0);

    await logoutViaUI(page);
  });

  test('2.8 Member navigating directly to restricted URL shows Access Restricted', async ({ page }) => {
    await loginViaUI(page, 'member');
    await expect(page).toHaveURL(/\/dashboard/);

    // Directly navigate to restricted sections
    await page.goto('/dashboard/officers');
    // Backend DashboardController protects route and redirects back to /dashboard
    await expect(page).toHaveURL(/\/dashboard(?!\/officers)/, { timeout: 10000 });

    await page.goto('/dashboard/branches');
    await expect(page).toHaveURL(/\/dashboard(?!\/branches)/, { timeout: 10000 });

    await logoutViaUI(page);
  });
});
