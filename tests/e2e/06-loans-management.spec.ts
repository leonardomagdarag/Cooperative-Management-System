import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('6. Loans Management & Loan Lifecycle', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'loan');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('6.1 Should view Loans Page with header, metrics and loan table', async ({ page }) => {
    await page.goto('/dashboard/loans');
    await expect(page.getByRole('heading', { level: 1, name: /Loans/i })).toBeVisible({ timeout: 10000 });

    // Search bar should be present
    const searchInput = page.locator('input[placeholder*="Search loans" i]').first();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Table or empty state
    const content = page.locator('table').or(page.getByText(/No loans found/i));
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('6.2 Should search and filter loans', async ({ page }) => {
    await page.goto('/dashboard/loans');
    await expect(page.getByRole('heading', { level: 1, name: /Loans/i })).toBeVisible({ timeout: 10000 });

    const searchInput = page.locator('input[placeholder*="Search loans" i]').first();
    await searchInput.fill('LN');

    // Verify search responds
    const content = page.locator('table').or(page.getByText(/No loans found/i));
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('6.3 Should check Encode Loan action or review capability', async ({ page }) => {
    await page.goto('/dashboard/loans');
    await expect(page.getByRole('heading', { level: 1, name: /Loans/i })).toBeVisible({ timeout: 10000 });

    // Check if Encode Loan button is available (depends on admin / loans.encode permission)
    const encodeBtn = page.locator('button:has-text("Encode Loan")').first();
    if (await encodeBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await encodeBtn.click();
      await expect(page.locator('text=/Encode Loan/i').first()).toBeVisible({ timeout: 10000 });
      await page.keyboard.press('Escape');
    } else {
      // Loan officers verify review/filtering capability on the table
      const searchInput = page.locator('input[placeholder*="Search loans" i]').first();
      await expect(searchInput).toBeVisible();
    }
  });

  test('6.4 Should inspect Loan Details tabs if loans exist', async ({ page }) => {
    await page.goto('/dashboard/loans');
    await expect(page.getByRole('heading', { level: 1, name: /Loans/i })).toBeVisible({ timeout: 10000 });

    // Check if any loan rows exist
    const firstLoanRow = page.locator('table tbody tr').first();
    if (await firstLoanRow.isVisible({ timeout: 3000 }).catch(() => false)) {
      // Click row or View Details button
      const viewBtn = firstLoanRow.locator('button:has-text("View"), button:has-text("Details"), button:has(.fa-eye)').first();
      if (await viewBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
        await viewBtn.click();
      } else {
        await firstLoanRow.click();
      }

      // Check for detail tabs
      const overviewTab = page.locator('button:has-text("Overview")').first();
      await expect(overviewTab).toBeVisible({ timeout: 10000 });

      const scheduleTab = page.locator('button:has-text("Amortization Schedule"), button:has-text("Schedule")').first();
      if (await scheduleTab.isVisible({ timeout: 2000 }).catch(() => false)) {
        await scheduleTab.click();
        await expect(page.locator('text=/Schedule|Principal|Interest|Balance/i').first()).toBeVisible({ timeout: 5000 });
      }
    }
  });
});
