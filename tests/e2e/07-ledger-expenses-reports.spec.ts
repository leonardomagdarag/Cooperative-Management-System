import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('7. General Ledger, Expenses & Financial Reporting', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'accountant');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('7.1 Should view General Ledger with summary, filter controls and entries table', async ({ page }) => {
    await page.goto('/dashboard/ledger');
    await expect(page.locator('text=/Ledger/i').first()).toBeVisible({ timeout: 10000 });

    // Table or list container
    const ledgerContent = page.locator('table').or(page.getByText(/No entries found/i));
    await expect(ledgerContent.first()).toBeVisible({ timeout: 10000 });

    // Filter controls: category select or search input
    const filterElement = page.locator('select, input[placeholder*="Search" i]').first();
    await expect(filterElement).toBeVisible({ timeout: 10000 });

    // Record / Add Entry button (if available for role)
    const addBtn = page.locator('button:has-text("Record"), button:has-text("Add Entry"), button:has-text("New Entry")').first();
    if (await addBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await addBtn.click();
      await expect(page.locator('text=/Record Transaction|New Entry|Add Entry/i').first()).toBeVisible({ timeout: 5000 });
      await page.keyboard.press('Escape');
    }
  });

  test('7.2 Should view Expenses page, filters and record expense modal', async ({ page }) => {
    await page.goto('/dashboard/expenses');
    await expect(page.getByRole('heading', { level: 1, name: /Expenses/i })).toBeVisible({ timeout: 10000 });

    // Expenses table or empty state
    const expenseContent = page.locator('table').or(page.getByText(/No expenses found/i));
    await expect(expenseContent.first()).toBeVisible({ timeout: 10000 });

    // Add Expense button
    const addExpenseBtn = page.locator('button:has-text("Record Expense"), button:has-text("Add Expense")').first();
    if (await addExpenseBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await addExpenseBtn.click();
      await expect(page.locator('text=/Record Expense|Add Expense/i').first()).toBeVisible({ timeout: 5000 });
      await page.keyboard.press('Escape');
    }
  });

  test('7.3 Should view Reports page and Financial Summary report', async ({ page }) => {
    await page.goto('/dashboard/reports');
    await expect(page.getByRole('heading', { level: 1, name: /Reports/i })).toBeVisible({ timeout: 10000 });

    // Report type buttons exist
    await expect(page.locator('button:has-text("Financial Summary")').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('button:has-text("Member Savings")').first()).toBeVisible();
    await expect(page.locator('button:has-text("Loan Report")').first()).toBeVisible();
  });

  test('7.4 Should switch between report tabs including Balance Sheet and Expense Report', async ({ page }) => {
    await page.goto('/dashboard/reports');
    await expect(page.getByRole('heading', { level: 1, name: /Reports/i })).toBeVisible({ timeout: 10000 });

    // Switch to Expense Report
    const expenseReportBtn = page.locator('button:has-text("Expense Report")').first();
    if (await expenseReportBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await expenseReportBtn.click();
      await expect(page.locator('text=/Expenses|Category|Amount/i').first()).toBeVisible({ timeout: 5000 });
    }

    // Switch to Balance Sheet
    const balanceSheetBtn = page.locator('button:has-text("Balance Sheet")').first();
    if (await balanceSheetBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await balanceSheetBtn.click();
      await expect(page.locator('text=/Assets|Liabilities|Equity|Balance Sheet/i').first()).toBeVisible({ timeout: 5000 });
    }
  });
});
