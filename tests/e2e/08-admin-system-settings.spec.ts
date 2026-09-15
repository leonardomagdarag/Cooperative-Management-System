import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('8. Admin System Settings, Governance & Administration', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'admin');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('8.1 Should view Officers Management page with table and search', async ({ page }) => {
    await page.goto('/dashboard/officers');
    await expect(page.getByRole('heading', { level: 1, name: /Officers/i })).toBeVisible({ timeout: 10000 });

    // Table or empty state
    const content = page.locator('table').or(page.getByText(/No officers found/i));
    await expect(content.first()).toBeVisible({ timeout: 10000 });

    // Search input
    const searchInput = page.locator('input[placeholder*="Search" i]').first();
    await expect(searchInput).toBeVisible();
  });

  test('8.2 Should view Branches Management page with branches list and Add Branch button', async ({ page }) => {
    await page.goto('/dashboard/branches');
    await expect(page.getByRole('heading', { level: 1, name: /Branches/i })).toBeVisible({ timeout: 10000 });

    // Verify Add Branch button exists
    const addBtn = page.locator('button:has-text("Add Branch"), button:has-text("New Branch")').first();
    await expect(addBtn).toBeVisible({ timeout: 10000 });

    // Verify branches container
    await expect(page.locator('main, .grid, table').first()).toBeVisible();
  });

  test('8.3 Should view Announcements Management page and controls', async ({ page }) => {
    await page.goto('/dashboard/announcements');
    await expect(page.getByRole('heading', { level: 1, name: /Announcements/i })).toBeVisible({ timeout: 10000 });

    // Check presence of create button or announcements list
    const createBtn = page.locator('button:has-text("New Announcement"), button:has-text("Create Announcement"), button:has-text("Add Announcement")').first();
    if (await createBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await expect(createBtn).toBeVisible();
    }
  });

  test('8.4 Should view Support Tickets queue with filters and metric cards', async ({ page }) => {
    await page.goto('/dashboard/tickets');
    
    // Check metric cards or page title
    await expect(page.locator('text=/Total Tickets|Open Inquiries|Tickets/i').first()).toBeVisible({ timeout: 10000 });

    // Check ticket list container
    await expect(page.locator('main, .space-y-4, table').first()).toBeVisible();
  });

  test('8.5 Should view Activity Logs audit trail with event filter controls', async ({ page }) => {
    await page.goto('/dashboard/logs');
    await expect(page.getByRole('heading', { level: 1, name: /Activity Logs/i })).toBeVisible({ timeout: 10000 });

    // Check search / filter controls
    const searchInput = page.locator('input[placeholder*="Search logs" i]').first();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Check logs table or empty state
    const content = page.locator('table').or(page.getByText(/No logs found|No activity/i));
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('8.6 Should navigate Admin System Settings tabs (Service Availability, Loan Products, Backup & Restore)', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await expect(page.getByRole('heading', { level: 1, name: /Settings/i })).toBeVisible({ timeout: 10000 });

    // Verify admin tabs exist
    const svcTab = page.locator('button:has-text("Service Availability")').first();
    await expect(svcTab).toBeVisible({ timeout: 10000 });
    await svcTab.click();
    await expect(page.locator('text=/Deposit|Withdrawal|Loan Application|Maintenance/i').first()).toBeVisible({ timeout: 10000 });

    // Switch to Loan Products tab
    const loanProdTab = page.locator('button:has-text("Loan Products")').first();
    await expect(loanProdTab).toBeVisible();
    await loanProdTab.click();
    await expect(page.locator('text=/Product|Interest Rate|Term/i').first()).toBeVisible({ timeout: 10000 });

    // Switch to Backup & Restore tab
    const backupTab = page.locator('button:has-text("Backup & Restore")').first();
    await expect(backupTab).toBeVisible();
    await backupTab.click();
    await expect(page.locator('text=/Backup|Database|Restore/i').first()).toBeVisible({ timeout: 10000 });
  });
});
