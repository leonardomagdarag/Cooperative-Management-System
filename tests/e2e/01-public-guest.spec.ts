import { test, expect } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

test.describe('1. Public / Guest Workflows', () => {
  test('1.1 Should view Home page with core sections', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Settlers Credit Cooperative/);

    // Header brand & navigation
    await expect(page.locator('header, nav').first()).toBeVisible();
    await expect(page.locator('nav').getByRole('link', { name: /Home/i }).first()).toBeVisible();
    await expect(page.locator('nav').getByRole('link', { name: /About Us/i }).first()).toBeVisible();
    await expect(page.locator('nav').getByRole('link', { name: /News/i }).first()).toBeVisible();
    await expect(page.locator('nav').getByRole('link', { name: /Login/i }).first()).toBeVisible();

    // Hero section
    await expect(page.getByRole('heading', { name: /Settlers Credit Cooperative/i, level: 1 })).toBeVisible();
  });

  test('1.2 Should view About Us page', async ({ page }) => {
    await page.goto('/about');
    await expect(page).toHaveURL(/\/about/);
    await expect(page.getByRole('heading', { level: 1, name: /About Us/i })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('heading', { level: 2, name: /Our History/i })).toBeVisible();
  });

  test('1.3 Should view News & Announcements page with public announcements', async ({ page }) => {
    await page.goto('/news');
    await expect(page).toHaveURL(/\/news/);
    await expect(page.getByRole('heading', { level: 1, name: /News & Events/i })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('heading', { level: 2, name: /Latest News & Announcements/i })).toBeVisible();
  });

  test('1.4 Should view Stories page', async ({ page }) => {
    await page.goto('/stories');
    await expect(page).toHaveURL(/\/stories/);
    await expect(page.getByRole('heading', { level: 1, name: /Member Stories/i })).toBeVisible({ timeout: 10000 });
  });

  test('1.5 Should view Contact page and submit message form successfully', async ({ page }) => {
    await page.goto('/contact');
    await expect(page.getByRole('heading', { level: 2, name: /Get In Touch/i })).toBeVisible({ timeout: 10000 });

    const nameInput = page.locator('input#name');
    const emailInput = page.locator('input#email');
    const messageInput = page.locator('textarea#message');
    const submitBtn = page.getByRole('button', { name: /Send Message/i });

    await expect(nameInput).toBeVisible();
    await expect(emailInput).toBeVisible();
    await expect(messageInput).toBeVisible();

    await nameInput.fill('QA Tester');
    await emailInput.fill('qa.tester@example.com');
    await messageInput.fill('Automated functional test message for contact workflow.');
    await submitBtn.click();

    await expect(page.locator('text=/Message sent!|We\'ll get back to you soon/i').first()).toBeVisible({ timeout: 10000 });
  });

  test('1.6 Should view Login page and handle validation & invalid credentials', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1, name: /Welcome back/i })).toBeVisible({ timeout: 10000 });

    const submitBtn = page.getByRole('button', { name: /Sign in/i });

    // Client-side empty validation
    await submitBtn.click();
    await expect(page.getByText('Please enter your email or phone number')).toBeVisible();
    await expect(page.getByText('Password is required')).toBeVisible();

    // Invalid credentials
    await page.locator('input#email').fill('nonexistent.user@example.com');
    await page.locator('input#password').fill('wrongpassword123');
    await submitBtn.click();
    await expect(page.locator('text=/credentials are incorrect|credentials do not match|Captcha verification failed|Login failed/i').first()).toBeVisible({ timeout: 10000 });
  });

  test('1.7 Should view Forgot Password page', async ({ page }) => {
    await page.goto('/forgot-password');
    await expect(page.getByRole('heading', { level: 1, name: /Forgot Password\?/i })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.getByRole('button', { name: /Send Verification Code/i })).toBeVisible();
  });

  test('1.8 GuestRoute should redirect authenticated user away from login to dashboard', async ({ page }) => {
    await loginViaUI(page, 'admin');
    await expect(page).toHaveURL(/\/dashboard/);

    // Attempt visiting /login while authenticated
    await page.goto('/login');
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 10000 });

    await logoutViaUI(page);
  });

  test('1.9 Should view standalone Maintenance page directly', async ({ page }) => {
    await page.goto('/maintenance');
    // Maintenance page renders standalone Blade template
    await expect(page.locator('text=/Maintenance|System Status|unavailable|Settlers/i').first()).toBeVisible({ timeout: 10000 });
  });
});
