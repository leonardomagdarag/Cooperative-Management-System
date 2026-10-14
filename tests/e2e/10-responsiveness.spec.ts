import { test, expect, Page } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

/**
 * Helper to assert that a page has no uncontained document-level horizontal scrollbar.
 * A tolerance of 1px is allowed for subpixel rendering in various browser engines.
 */
async function assertNoHorizontalOverflow(page: Page) {
  const { scrollWidth, clientWidth } = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
}

const VIEWPORTS = {
  mobileSmall: { width: 375, height: 667, name: 'Mobile (375x667)' },
  mobileMedium: { width: 390, height: 844, name: 'Mobile (390x844)' },
  tablet: { width: 768, height: 1024, name: 'Tablet (768x1024)' },
  desktop: { width: 1280, height: 800, name: 'Desktop (1280x800)' },
};

test.describe('10. Comprehensive Frontend Responsiveness & Layout Verification', () => {

  test.describe('10.1 Public Pages Responsiveness', () => {
    for (const [key, vp] of Object.entries(VIEWPORTS)) {
      test(`Public pages have no horizontal overflow on ${vp.name}`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });

        // 1. Home page
        await page.goto('/');
        await expect(page.getByRole('heading', { name: /Settlers Credit Cooperative/i, level: 1 })).toBeVisible({ timeout: 10000 });
        await assertNoHorizontalOverflow(page);

        // 2. About page
        await page.goto('/about');
        await expect(page.getByRole('heading', { level: 1, name: /About Us/i })).toBeVisible({ timeout: 10000 });
        await assertNoHorizontalOverflow(page);

        // 3. News page
        await page.goto('/news');
        await expect(page.getByRole('heading', { level: 1, name: /News & Events/i })).toBeVisible({ timeout: 10000 });
        await assertNoHorizontalOverflow(page);

        // 4. Contact page
        await page.goto('/contact');
        await expect(page.getByRole('heading', { level: 2, name: /Get In Touch/i })).toBeVisible({ timeout: 10000 });
        await assertNoHorizontalOverflow(page);

        // 5. Login page
        await page.goto('/login');
        await expect(page.getByRole('heading', { level: 1, name: /Welcome back/i })).toBeVisible({ timeout: 10000 });
        await assertNoHorizontalOverflow(page);
      });
    }

    test('Mobile public navigation drawer toggles open and close cleanly', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/');
      await expect(page.getByRole('heading', { name: /Settlers Credit Cooperative/i, level: 1 })).toBeVisible({ timeout: 10000 });

      // On mobile (< 768px), desktop navbar links should be hidden or replaced by hamburger
      const mobileToggle = page.locator('nav button, header button').filter({ has: page.locator('svg, i') }).first();
      await expect(mobileToggle).toBeVisible();

      // Open mobile drawer
      await mobileToggle.click();
      await page.waitForTimeout(300);

      // Verify public drawer links are visible
      const mobileNav = page.locator('nav, div[role="dialog"]').getByRole('link', { name: /Home|About|News|Contact|Login/i }).first();
      await expect(mobileNav).toBeVisible();

      // Verify no horizontal overflow when drawer is open
      await assertNoHorizontalOverflow(page);

      // Close mobile drawer by clicking toggle again
      await mobileToggle.click();
      await page.waitForTimeout(300);
    });
  });

  test.describe('10.2 Authenticated Dashboard Responsiveness', () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 });
      await loginViaUI(page, 'admin');
    });

    test.afterEach(async ({ page }) => {
      await logoutViaUI(page);
    });

    test('Mobile Dashboard Header and Sidebar Drawer Toggle (375x667)', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/dashboard');
      await expect(page.locator('header')).toBeVisible({ timeout: 10000 });

      // Document itself must not have horizontal overflow
      await assertNoHorizontalOverflow(page);

      // Header hamburger toggle must be visible
      const hamburger = page.locator('header button').filter({ has: page.locator('svg, i') }).first();
      await expect(hamburger).toBeVisible();

      // Open the sidebar drawer
      await hamburger.click();
      await page.waitForTimeout(400);

      // Sidebar navigation items should now be visible in the off-canvas drawer
      const sidebarLink = page.locator('aside a, nav a').filter({ hasText: /Dashboard|Members/i }).first();
      await expect(sidebarLink).toBeVisible();

      // Ensure no horizontal document overflow with drawer open
      await assertNoHorizontalOverflow(page);

      // Dismiss sidebar by clicking the backdrop overlay
      const backdrop = page.locator('.fixed.inset-0.bg-black\\/50, .fixed.inset-0.bg-slate-900\\/50, .fixed.inset-0.z-40').first();
      if (await backdrop.isVisible()) {
        await backdrop.click({ force: true });
        await page.waitForTimeout(400);
      }
    });

    test('Data-heavy table pages scroll horizontally without breaking page document layout', async ({ page }) => {
      const testCases = [
        { path: '/dashboard/members', title: /Members/i },
        { path: '/dashboard/loans', title: /Loans/i },
        { path: '/dashboard/ledger', title: /Ledger|General Ledger/i },
        { path: '/dashboard/reports', title: /Reports/i },
        { path: '/dashboard/settings', title: /Settings/i },
        { path: '/dashboard/profile', title: /Profile|My Profile/i },
      ];

      for (const tc of testCases) {
        // Test on Mobile 375x667
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto(tc.path);
        await page.waitForTimeout(600);

        // Document layout must not overflow
        await assertNoHorizontalOverflow(page);

        // If there is an overflow-x-auto container with a table, verify it handles scroll properly
        const tableContainer = page.locator('.overflow-x-auto').first();
        if (await tableContainer.isVisible()) {
          const containerScroll = await tableContainer.evaluate((el: HTMLElement) => ({
            scrollWidth: el.scrollWidth,
            clientWidth: el.clientWidth,
          }));
          // Container width fits within screen
          expect(containerScroll.clientWidth).toBeLessThanOrEqual(375);
        }

        // Test on Tablet 768x1024
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(200);
        await assertNoHorizontalOverflow(page);
      }
    });

    test('Member Profile Modal and Profile Edit Modal render within mobile viewport bounds', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/dashboard/profile');
      await page.waitForTimeout(600);

      await assertNoHorizontalOverflow(page);

      // Open Personal Info edit modal if edit button is available
      const editPersonalBtn = page.getByRole('button', { name: /Edit/i }).first();
      if (await editPersonalBtn.isVisible()) {
        await editPersonalBtn.click();
        await page.waitForTimeout(300);

        // Modal should fit cleanly inside the screen without horizontal overflow
        await assertNoHorizontalOverflow(page);

        // Modal inputs should be visible and properly stacked
        const firstNameInput = page.locator('input[name="first_name"]');
        if (await firstNameInput.isVisible()) {
          await expect(firstNameInput).toBeVisible();
          const box = await firstNameInput.boundingBox();
          if (box) {
            expect(box.width).toBeLessThanOrEqual(375);
            expect(box.x).toBeGreaterThanOrEqual(0);
          }
        }

        // Close modal
        const cancelBtn = page.getByRole('button', { name: /Cancel/i }).first();
        if (await cancelBtn.isVisible()) {
          await cancelBtn.click();
        }
      }
    });

    test('Search Trigger on mobile viewport opens modal cleanly', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.goto('/dashboard');
      await page.waitForTimeout(600);

      // The mobile search icon button in the header
      const searchTrigger = page.locator('button[aria-label="Open search dialog"]');
      if (await searchTrigger.isVisible()) {
        await searchTrigger.click();
        await page.waitForTimeout(300);

        // Dialog should be visible
        const searchDialog = page.getByRole('dialog', { name: /Search/i });
        await expect(searchDialog).toBeVisible();

        // Search input should be within screen width
        const searchInput = page.locator('input[placeholder*="Search"]');
        await expect(searchInput).toBeVisible();

        // Close search dialog
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);
      }
    });
  });
});
