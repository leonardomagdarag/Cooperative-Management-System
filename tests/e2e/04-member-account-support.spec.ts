import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('4. Member Account, Profile, Notifications & Helpdesk', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUI(page, 'member');
  });

  test.afterEach(async ({ page }) => {
    await logoutViaUI(page);
  });

  test('4.1 Should view Member Profile and navigate profile tabs', async ({ page }) => {
    await page.goto('/dashboard/profile');
    await expect(page.locator('h2').first()).toBeVisible({ timeout: 10000 });

    // Verify presence of member name & email
    await expect(page.locator('text=/arjay.ramos@example.com/i').first()).toBeVisible({ timeout: 10000 });

    // Switch between Profile tabs
    await page.locator('button:has-text("Contact & Address")').first().click();
    await expect(page.locator('text=/Contact Information|Address/i').first()).toBeVisible({ timeout: 5000 });

    await page.locator('button:has-text("Employment")').first().click();
    await expect(page.locator('text=/Employment Details|Employer|Occupation/i').first()).toBeVisible({ timeout: 5000 });

    await page.locator('button:has-text("Financial")').first().click();
    await expect(page.locator('text=/Accounts|Savings|Share Capital/i').first()).toBeVisible({ timeout: 5000 });

    // Open Edit Personal Info modal
    const editBtn = page.locator('button:has-text("Edit Personal Info")').first();
    await expect(editBtn).toBeVisible();
    await editBtn.click();

    // Verify modal elements
    await expect(page.locator('text=/Edit Profile|Personal Information/i').first()).toBeVisible({ timeout: 5000 });
    const cancelOrCloseBtn = page.locator('button:has-text("Cancel"), button[aria-label="Close"]').first();
    await cancelOrCloseBtn.click();
  });

  test('4.2 Should view Notifications page with filter and management controls', async ({ page }) => {
    await page.goto('/dashboard/notifications');
    await expect(page.getByRole('heading', { level: 1, name: /Notifications/i })).toBeVisible({ timeout: 10000 });

    // Verify controls exist
    await expect(page.locator('button:has-text("Mark All Read")').first()).toBeVisible();
    await expect(page.locator('select').first()).toBeVisible();

    // Verify notification list container or empty state
    await expect(page.locator('main, .max-w-7xl').first()).toBeVisible();
  });

  test('4.3 Should view Settings My Account tab with security and device controls', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await expect(page.getByRole('heading', { level: 1, name: /Settings/i })).toBeVisible({ timeout: 10000 });

    // Ensure My Account tab is selected
    const myAccountTab = page.locator('button:has-text("My Account")').first();
    await expect(myAccountTab).toBeVisible();
    await myAccountTab.click();

    // Check presence of Change Password section
    await expect(page.locator('text=/Change Password/i').first()).toBeVisible({ timeout: 10000 });

    // Check presence of Trusted Devices or Security section
    await expect(page.locator('text=/Trusted Devices|Active Sessions|Security/i').first()).toBeVisible({ timeout: 10000 });
  });

  test('4.4 Should browse and search FAQs in Help & Support tab', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await expect(page.getByRole('heading', { level: 1, name: /Settings/i })).toBeVisible({ timeout: 10000 });

    // Switch to Help & Support tab
    const helpTab = page.locator('button:has-text("Help & Support")').first();
    await expect(helpTab).toBeVisible();
    await helpTab.click();

    // Ensure FAQs section is active
    await expect(page.locator('text=/FAQs & Guides/i').first()).toBeVisible({ timeout: 10000 });

    // Search FAQ
    const searchInput = page.locator('input[placeholder*="Search" i]').first();
    await expect(searchInput).toBeVisible();
    await searchInput.fill('loan');

    // Verify FAQ items or categories respond
    await expect(page.locator('.rounded-2xl, .space-y-6').first()).toBeVisible();
  });

  test('4.5 Should submit a new Support Ticket and view in active tickets list', async ({ page }) => {
    await page.goto('/dashboard/settings');
    await expect(page.getByRole('heading', { level: 1, name: /Settings/i })).toBeVisible({ timeout: 10000 });

    // Switch to Help & Support tab
    const helpTab = page.locator('button:has-text("Help & Support")').first();
    await expect(helpTab).toBeVisible();
    await helpTab.click();

    // Click Submit a Support Ticket button or switch to Tickets subtab
    const submitTicketBtn = page.locator('button:has-text("Submit a Support Ticket"), button:has-text("New Ticket")').first();
    await expect(submitTicketBtn).toBeVisible({ timeout: 10000 });
    await submitTicketBtn.click();

    // Modal should be open
    await expect(page.locator('text=/Submit Support Ticket/i').first()).toBeVisible({ timeout: 10000 });

    // Fill ticket form
    const subjectInput = page.locator('input[placeholder*="withdrawal" i], input[placeholder*="OTP code" i]').first();
    await expect(subjectInput).toBeVisible({ timeout: 5000 });
    await subjectInput.fill('E2E Loan Interest Inquiry ' + Date.now());

    const messageInput = page.locator('textarea[placeholder*="describe what happened" i]').first();
    await expect(messageInput).toBeVisible({ timeout: 5000 });
    await messageInput.fill('This is an automated functional verification message regarding member loan interest rates.');

    // Submit ticket
    const submitBtn = page.getByRole('button', { name: /Submit Ticket/i });
    await submitBtn.click();

    // Verify ticket created either in list or detail modal
    const ticketElement = page.locator('text=/TCK-|Support ticket created successfully/i').first();
    await expect(ticketElement).toBeVisible({ timeout: 15000 });

    // Close detail modal if open
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    const closeBtn = page.locator('button[aria-label="Close dialog"], button[aria-label="Close"]').first();
    if (await closeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await closeBtn.click().catch(() => {});
    }
  });
});
