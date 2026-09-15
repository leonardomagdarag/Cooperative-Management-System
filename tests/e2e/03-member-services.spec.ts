import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('3. Member Services & Financial Operations', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'member');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('3.1 Should view Services Page and core service cards', async ({ page }) => {
    await page.goto('/dashboard/services');
    await expect(page.getByRole('heading', { level: 1, name: /Services/i })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('heading', { level: 2, name: /Available Services/i })).toBeVisible();

    // Verify service cards exist
    await expect(page.locator('button').filter({ hasText: 'Deposit Money' }).first()).toBeVisible();
    await expect(page.locator('button').filter({ hasText: 'Withdraw Money' }).first()).toBeVisible();
    await expect(page.locator('button').filter({ hasText: 'Apply for Loan' }).first()).toBeVisible();
    await expect(page.locator('button').filter({ hasText: 'Make Payment' }).first()).toBeVisible();
  });

  test('3.2 Should complete Deposit Money workflow (Counter / OTC)', async ({ page }) => {
    await page.goto('/dashboard/services');

    // Click Deposit Money card button
    await page.locator('button').filter({ hasText: 'Deposit Money' }).first().click();

    // Modal title & Details step
    await expect(page.locator('text=/Enter Deposit Details/i').first()).toBeVisible({ timeout: 10000 });

    // Step 1: Details
    await page.locator('input[placeholder="0.00"]').first().fill('500');
    // Proceed to payment method step
    await page.locator('button:has-text("Next")').click();

    // Step 2: Payment Method - Select Counter (Over the Counter)
    const counterOption = page.locator('button:has-text("Over the Counter")').first();
    await expect(counterOption).toBeVisible({ timeout: 10000 });
    await counterOption.click();
    await page.locator('button:has-text("Next")').click();

    // Step 3: Review & Submit
    await expect(page.locator('text=/Confirm Your Deposit/i').first()).toBeVisible({ timeout: 10000 });
    await page.locator('button:has-text("Confirm")').click();

    // Verify Success Receipt or Modal message
    await expect(page.locator('text=/Deposit Successful|Deposit submitted successfully|Transaction Reference/i').first()).toBeVisible({ timeout: 15000 });
    const closeBtn = page.locator('button:has-text("Close")').first();
    if (await closeBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await closeBtn.click();
    }
  });

  test('3.3 Should complete Withdraw Money workflow (Counter / OTC)', async ({ page }) => {
    await page.goto('/dashboard/services');

    // Click Withdraw Money card button
    await page.locator('button').filter({ hasText: 'Withdraw Money' }).first().click();

    // Modal title and wait for balance to load so availableBalance > 0
    await expect(page.locator('text=/Enter Withdrawal Details|Available for Withdrawal/i').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=/Current Balance:.*₱[1-9]/i').first()).toBeVisible({ timeout: 10000 });

    // Step 1: Details (amount and reason)
    await page.locator('input[placeholder="0.00"]').first().fill('100');
    const reasonInput = page.locator('textarea[placeholder*="Purpose" i]');
    await expect(reasonInput).toBeVisible({ timeout: 5000 });
    await reasonInput.fill('Medical emergency assistance');
    await page.locator('button:has-text("Next")').click();

    // Step 2: Method - Select Counter
    const counterOption = page.locator('button:has-text("Over the Counter")').first();
    await expect(counterOption).toBeVisible({ timeout: 10000 });
    await counterOption.click();
    await page.locator('button:has-text("Next")').click();

    // Step 3: Review & Submit
    await expect(page.locator('text=/Confirm Your Withdrawal/i').first()).toBeVisible({ timeout: 10000 });
    await page.locator('button:has-text("Confirm")').click();

    // Verify Success Receipt or Pending notification
    await expect(page.locator('text=/Withdrawal submitted for approval|Transaction Receipt|Success|Close/i').first()).toBeVisible({ timeout: 15000 });
    const closeWithdrawBtn = page.locator('button:has-text("Close")').first();
    if (await closeWithdrawBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await closeWithdrawBtn.click();
    }
  });

  test('3.4 Should view Member Loans page and loan records', async ({ page }) => {
    await page.goto('/dashboard/member-loans');
    await expect(page.getByRole('heading', { level: 1, name: /My Loans/i })).toBeVisible({ timeout: 10000 });

    // Verify table or empty state
    const content = page.locator('table').or(page.getByText('No loans found'));
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('3.5 Should view Transaction History page with filters', async ({ page }) => {
    await page.goto('/dashboard/history');
    await expect(page.locator('text=/History/i').first()).toBeVisible({ timeout: 10000 });

    // Check filter selects
    await expect(page.locator('select').first()).toBeVisible();

    // Verify history container is present
    await expect(page.locator('.space-y-6, main').first()).toBeVisible();
  });
});
