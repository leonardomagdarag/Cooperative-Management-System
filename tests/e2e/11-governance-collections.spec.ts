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

test.describe('11. Governance, Oversight Countersign & Field Collections', () => {

  test.describe('11.1 Field Collections UI & Navigation', () => {
    test('Loan officer can view Field Collections and My Route', async ({ page }) => {
      await loginViaUI(page, 'loan');
      await page.goto('/dashboard/collections');
      await expect(page.getByRole('heading', { level: 1, name: /Field Collections/i })).toBeVisible({ timeout: 10000 });

      // Verify My Route tab is present and active for loan officer
      const myRouteTab = page.locator('button:has-text("My Route")').first();
      await expect(myRouteTab).toBeVisible({ timeout: 10000 });

      // Container for route list or empty state
      await expect(page.locator('main, .bg-white, table').first()).toBeVisible();

      await logoutViaUI(page);
    });

    test('Manager can view Field Collections Approvals Queue and Run History', async ({ page }) => {
      await loginViaUI(page, 'manager');
      await page.goto('/dashboard/collections');
      await expect(page.getByRole('heading', { level: 1, name: /Field Collections/i })).toBeVisible({ timeout: 10000 });

      // Verify Approvals Queue tab exists
      const approvalsTab = page.locator('button:has-text("Approvals Queue")').first();
      await expect(approvalsTab).toBeVisible({ timeout: 10000 });
      await approvalsTab.click();
      await expect(page.locator('text=/Pending Approvals|No pending collection runs|Approvals Queue/i').first()).toBeVisible({ timeout: 10000 });

      // Switch to Run History tab
      const historyTab = page.locator('button:has-text("Run History")').first();
      if (await historyTab.isVisible()) {
        await historyTab.click();
        const content = page.getByRole('heading', { level: 2, name: /Run History/i })
          .or(page.locator('table'))
          .or(page.getByText(/No collection runs found/i));
        await expect(content.first()).toBeVisible({ timeout: 10000 });
      }

      await logoutViaUI(page);
    });
  });

  test.describe('11.2 Collections Draft Invariant & API Scoping', () => {
    test('Draft collection item addition does NOT mutate loan balance or post completed transaction', async ({ page }) => {
      await loginViaUI(page, 'loan');
      await page.goto('/dashboard/collections');

      // 1. Fetch available route list
      const routeRes = await apiCall(page, 'GET', '/api/collections/route-list');
      expect(routeRes.status).toBe(200);
      expect(Array.isArray(routeRes.data?.data)).toBe(true);

      if (routeRes.data.data.length > 0) {
        const targetLoan = routeRes.data.data[0];
        const loanId = targetLoan.id;
        const memberId = targetLoan.member_id;

        // Query member's loan before draft item
        const loanDetailRes = await apiCall(page, 'GET', `/api/loans/${loanId}`);
        expect(loanDetailRes.status).toBe(200);
        const loanBefore = loanDetailRes.data;
        const balanceBefore = Number(loanBefore.remaining_balance ?? loanBefore.balance ?? loanBefore.loan_amount);

        // 2. Create or retrieve draft collection run
        const createRunRes = await apiCall(page, 'POST', '/api/collections', {
          collection_date: new Date().toISOString().split('T')[0],
          branch_id: targetLoan.branch_id,
        });
        expect([200, 201]).toContain(createRunRes.status);
        const run = createRunRes.data.data;
        expect(run.status).toBe('draft');

        // 3. Add item to draft run
        const addItemRes = await apiCall(page, 'POST', `/api/collections/${run.id}/items`, {
          member_id: memberId,
          loan_id: loanId,
          account_id: targetLoan.savings_account_id ?? null,
          amount: 500,
          note: 'QA automated test draft collection item',
          visited: true,
        });
        expect(addItemRes.status).toBe(201);
        const item = addItemRes.data.data;
        expect(item.outcome).toBe('pending');

        // 4. Assert invariant: Loan balance must NOT have been changed by draft item
        const loanDetailAfterRes = await apiCall(page, 'GET', `/api/loans/${loanId}`);
        expect(loanDetailAfterRes.status).toBe(200);
        const loanAfter = loanDetailAfterRes.data;
        const balanceAfter = Number(loanAfter.remaining_balance ?? loanAfter.balance ?? loanAfter.loan_amount);
        expect(balanceAfter).toEqual(balanceBefore);

        // 5. Clean up draft item so the loan remains clean
        const removeItemRes = await apiCall(page, 'DELETE', `/api/collections/${run.id}/items/${item.id}`);
        expect(removeItemRes.status).toBe(200);
      }

      await logoutViaUI(page);
    });

    test('Member is strictly forbidden from creating or approving collection runs', async ({ page }) => {
      await loginViaUI(page, 'member');
      await page.goto('/dashboard');

      // Attempt to create run
      const createRes = await apiCall(page, 'POST', '/api/collections', {
        collection_date: new Date().toISOString().split('T')[0],
        branch_id: 1,
      });
      expect(createRes.status).toBe(403);

      // Attempt to access queues
      const queueRes = await apiCall(page, 'GET', '/api/collections/queues');
      expect(queueRes.status).toBe(403);

      await logoutViaUI(page);
    });
  });

  test.describe('11.3 Governance & Countersign Oversight Boundaries', () => {
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

    test('Loan officer is forbidden from accessing oversight requests', async ({ page }) => {
      await loginViaUI(page, 'loan');
      await page.goto('/dashboard');

      const loanOversight = await apiCall(page, 'GET', '/api/oversight/requests');
      expect(loanOversight.status).toBe(403);

      await logoutViaUI(page);
    });
  });
});
