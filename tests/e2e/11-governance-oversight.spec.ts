import { test, expect, Page } from '@playwright/test';
import { loginViaUI, logoutViaUI } from './helpers/auth';

/**
 * Helper to execute authenticated API calls directly inside the page session
 * ensuring X-XSRF-TOKEN and session cookies are sent correctly.
 */
async function apiCall(page: Page, method: string, url: string, data?: any) {
  return await page.evaluate(async ({ method, url, data }) => {
    const xsrfCookie = document.cookie
      .split('; ')
      .find(row => row.startsWith('XSRF-TOKEN='));
    const token = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : '';
    const res = await fetch(url, {
      method,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: data ? JSON.stringify(data) : undefined,
    });
    let body = null;
    try {
      body = await res.json();
    } catch {}
    return { status: res.status, data: body };
  }, { method, url, data });
}

test.describe('11. Governance & Countersign Oversight Boundaries', () => {
    test('Chairman can view oversight privileged action requests queue', async ({ page }) => {
      await loginViaUI(page, 'chairman');
      await page.goto('/dashboard');

      const oversightRes = await apiCall(page, 'GET', '/api/oversight/requests');
      expect(oversightRes.status).toBe(200);
      expect(Array.isArray(oversightRes.data?.data)).toBe(true);

      // Verify structure of oversight requests if any exist
      if (oversightRes.data.data.length > 0) {
        const req = oversightRes.data.data[0];
        expect(req).toHaveProperty('id');
        expect(req).toHaveProperty('action_type');
        expect(req).toHaveProperty('status');
      }

      await logoutViaUI(page);
    });

    test('Regular member is forbidden from accessing oversight requests', async ({ page }) => {
      await loginViaUI(page, 'member');
      await page.goto('/dashboard');

      const memberOversight = await apiCall(page, 'GET', '/api/oversight/requests');
      expect(memberOversight.status).toBe(403);

      await logoutViaUI(page);
    });

  });
});
