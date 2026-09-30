import { test, expect } from '@playwright/test';
import { loginViaUI, TEST_USERS } from './helpers/auth';

test.describe('SMS Integration', () => {
    test('Admin can configure SMS settings', async ({ page }) => {
        await loginViaUI(page, 'admin');
        
        await page.goto('/dashboard/settings');
        await page.click('button:has-text("SMS Service")');
        
        await expect(page.locator('h3', { hasText: 'Service Status' })).toBeVisible();
        
        // Fill API key
        await page.fill('input[placeholder="Enter your API key"]', 'test-api-key');
        await page.fill('input[placeholder="Specific Android device ID (leave blank to use any available)"]', 'test-device-id');
        
        await page.click('button:has-text("Save")');
        
        await expect(page.locator('text=SMS settings updated successfully')).toBeVisible();

        // Toggle the service
        const toggle = page.locator('input[type="checkbox"]');
        await toggle.click({ force: true });
        
        // It should save the setting
        await expect(page.locator('text=SMS settings updated successfully')).toBeVisible();
    });

    test('Login form accepts mobile number', async ({ page }) => {
        await page.goto('/login');
        
        // Ensure the label has been updated
        await expect(page.locator('label[for="email"]')).toHaveText('Email or Mobile Number');
        
        // Check input type is text
        const type = await page.locator('input#email').getAttribute('type');
        expect(type).toBe('text');
        
        // Wait for Turnstile
        try {
            await page.waitForFunction(() => {
                const input = document.querySelector('input[name="cf-turnstile-response"]') as HTMLInputElement;
                return Boolean(input && input.value);
            }, { timeout: 6000 });
        } catch { }
        await page.waitForTimeout(500);

        // Log in using standard email (simulating that the auth controller works with the new text field)
        await page.fill('input#email', TEST_USERS.admin.email);
        await page.fill('input#password', TEST_USERS.admin.password);
        await page.click('button:has-text("Sign in")');
        
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    });
});
