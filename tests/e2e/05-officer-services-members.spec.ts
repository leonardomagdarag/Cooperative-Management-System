import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('5. Officer Services Mode & Member Directory Management', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'manager');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('5.1 Should operate Officer Services Mode with member selector', async ({ page }) => {
    await page.goto('/dashboard/services');
    await expect(page.getByRole('heading', { level: 1, name: /Services/i })).toBeVisible({ timeout: 10000 });

    // Verify Officer Member Selector section
    await expect(page.locator('text=/Select Member/i').first()).toBeVisible({ timeout: 10000 });

    // Search for member
    const searchInput = page.locator('input[placeholder*="Search member" i]').first();
    await expect(searchInput).toBeVisible();
    await searchInput.fill('Arjay');

    // Wait for search dropdown list
    const memberOption = page.locator('text=/Arjay Ramos/i').first();
    await expect(memberOption).toBeVisible({ timeout: 10000 });
    await memberOption.click();

    // Verify member selected banner appears
    await expect(page.locator('button:has-text("Change Member")').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Arjay Ramos').first()).toBeVisible();

    // Verify service cards are unlocked
    await expect(page.locator('button').filter({ hasText: 'Deposit Money' }).first()).toBeVisible();
    await expect(page.locator('button').filter({ hasText: 'Withdraw Money' }).first()).toBeVisible();

    // Change member clears selection
    await page.locator('button:has-text("Change Member")').first().click();
    await expect(page.locator('input[placeholder*="Search member" i]').first()).toBeVisible({ timeout: 5000 });
  });

  test('5.2 Should list members and perform search filtering', async ({ page }) => {
    await page.goto('/dashboard/members');
    await expect(page.getByRole('heading', { level: 1, name: /Members/i })).toBeVisible({ timeout: 10000 });

    // Verify member table has rows
    await expect(page.locator('table tbody tr').first()).toBeVisible({ timeout: 10000 });

    // Perform member search
    const searchInput = page.locator('input[placeholder*="Search" i]').first();
    await searchInput.fill('Arjay');

    // Verify search filter displays matching row
    await expect(page.locator('table tbody').locator('text=/Arjay/i').first()).toBeVisible({ timeout: 10000 });
  });

  test('5.3 Should open Member Profile Modal and inspect tabs', async ({ page }) => {
    await page.goto('/dashboard/members');
    await expect(page.getByRole('heading', { level: 1, name: /Members/i })).toBeVisible({ timeout: 10000 });

    // Click the view (eye) icon for the first member
    const viewBtn = page.locator('table tbody tr button[title="View"]').first();
    await expect(viewBtn).toBeVisible({ timeout: 10000 });
    await viewBtn.click();

    // Verify Member Profile modal
    const modalHeading = page.getByRole('heading', { name: 'Member Profile', exact: true });
    await expect(modalHeading).toBeVisible({ timeout: 10000 });

    // Navigate through modal tabs
    const contactTab = page.locator('button:has-text("Contact & IDs")').first();
    if (await contactTab.isVisible({ timeout: 2000 }).catch(() => false)) {
      await contactTab.click();
      await expect(page.locator('text=/Contact Information|National ID|Address/i').first()).toBeVisible({ timeout: 5000 });
    }

    const financialTab = page.locator('button:has-text("Financial")').first();
    if (await financialTab.isVisible({ timeout: 2000 }).catch(() => false)) {
      await financialTab.click();
      await expect(page.locator('text=/Savings|Share Capital|Account Number/i').first()).toBeVisible({ timeout: 5000 });
    }

    // Close modal
    const closeBtn = page.locator('.fixed button:has(.fa-times), .fixed button:has-text("Close")').first();
    await closeBtn.click();
    await expect(modalHeading).toBeHidden({ timeout: 5000 });
  });

  test('5.4 Should open Member Export Modal and verify options', async ({ page }) => {
    await page.goto('/dashboard/members');
    await expect(page.getByRole('heading', { level: 1, name: /Members/i })).toBeVisible({ timeout: 10000 });

    // Click Export button
    const exportBtn = page.locator('button:has-text("Export")').first();
    await expect(exportBtn).toBeVisible({ timeout: 10000 });
    await exportBtn.click();

    // Verify Export modal
    await expect(page.locator('text=/Export Members/i').first()).toBeVisible({ timeout: 10000 });

    // Close modal
    const closeBtn = page.locator('.fixed button:has(.fa-times), button:has-text("Cancel")').first();
    await closeBtn.click();
  });
});
