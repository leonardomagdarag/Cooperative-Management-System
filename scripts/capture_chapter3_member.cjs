/**
 * Stage 1 & Stage 2 Automation: Chapter 3 (Member Portal, Figures 9 to 30)
 * Uses Option 1: Clean Playwright Capture + Native Locator Bounding Boxes + Python Pillow Vector Post-Processing.
 * Enforces strict selector guardrails (Modal scoping & header-exclusion).
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_BASE = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots/chapter3_member');
const RAW_DIR = path.join(OUT_BASE, 'raw');
const MANIFEST_DIR = path.join(OUT_BASE, 'manifests');
const RENDERED_DIR = path.join(OUT_BASE, 'rendered');
const MAIN_SCREENSHOTS_DIR = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots');

for (const dir of [RAW_DIR, MANIFEST_DIR, RENDERED_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

const VIEWPORT = { width: 1440, height: 900 };
const SCALE = 1.5;

function clearActiveSessions() {
  try {
    execSync(`php "${path.resolve(__dirname, 'clear_sessions.php')}"`, { stdio: 'ignore' });
  } catch {}
}

function getLatestOtpForEmail(email) {
  const logPath = path.resolve('storage/logs/laravel.log');
  if (!fs.existsSync(logPath)) return null;
  const content = fs.readFileSync(logPath, 'utf8');
  const escaped = email.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = [...content.matchAll(new RegExp(`for ${escaped}:\\s*(\\d{6})`, 'g'))];
  if (matches.length > 0) {
    return matches[matches.length - 1][1];
  }
  return null;
}

async function loginAsMember(page) {
  console.log('Logging in as member Arjay Ramos...');
  clearActiveSessions();
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  await page.fill('input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
  await page.fill('input#password', 'password');
  await page.click('button[type="submit"]');

  try {
    await page.waitForURL(/\/dashboard|\/login\/approval|\/security\/verify-otp/, { timeout: 15000 });
  } catch {}

  if (page.url().includes('/login/approval')) {
    console.log('Detected /login/approval, clicking verify by email...');
    const emailBtn = page.locator('button:has-text("Verify by email")');
    if (await emailBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await emailBtn.click();
      await page.waitForURL(/\/security\/verify-otp/, { timeout: 10000 });
    }
  }

  if (page.url().includes('/security/verify-otp')) {
    console.log('Detected /security/verify-otp challenge, solving OTP...');
    const otpInput = page.locator('input#otp');
    await otpInput.waitFor({ state: 'visible', timeout: 10000 });
    let otp = null;
    const codeEl = page.locator('.font-mono');
    if (await codeEl.isVisible({ timeout: 2000 }).catch(() => false)) {
      otp = (await codeEl.innerText()).trim();
    }
    if (!otp) {
      for (let i = 0; i < 10; i++) {
        otp = getLatestOtpForEmail('leo.baluyot039+arjay.ramos@gmail.com');
        if (otp) break;
        await page.waitForTimeout(500);
      }
    }
    if (otp) {
      await otpInput.fill(otp);
      await page.locator('button:has-text("Verify and continue")').click();
    }
  }

  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  await page.waitForTimeout(800);
  console.log('Member login confirmed at /dashboard.');
}

async function waitForGeometryStability(page, callouts) {
  for (let attempt = 0; attempt < 8; attempt++) {
    const snap1 = [];
    for (const c of callouts) {
      const loc = page.locator(c.selector).first();
      const box = (await loc.count()) > 0 ? await loc.boundingBox() : null;
      snap1.push(box);
    }
    await page.waitForTimeout(200);
    let stable = true;
    for (let i = 0; i < callouts.length; i++) {
      const loc = page.locator(callouts[i].selector).first();
      const box2 = (await loc.count()) > 0 ? await loc.boundingBox() : null;
      const box1 = snap1[i];
      if (!box1 || !box2) {
        stable = false;
        break;
      }
      if (
        Math.abs(box1.x - box2.x) > 0.5 ||
        Math.abs(box1.y - box2.y) > 0.5 ||
        Math.abs(box1.width - box2.width) > 0.5 ||
        Math.abs(box1.height - box2.height) > 0.5
      ) {
        stable = false;
        break;
      }
    }
    if (stable) return;
  }
}

const CHAPTER_3_TASKS = [
  {
    id: 'fig09',
    filename: 'fig09_member_dashboard.png',
    title: 'Figure 9: Member Dashboard — Personal Accounts & Quick Summaries',
    url: `${BASE_URL}/dashboard`,
    beforeAction: async (page) => {
      await page.locator('h1:has-text("Welcome back")').waitFor({ state: 'visible', timeout: 20000 });
      await page.waitForSelector('.grid.grid-cols-1.md\\:grid-cols-3', { timeout: 15000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Savings Available Balance',
        desc: 'Displays the liquid passbook savings balance recorded in the cooperative ledger.',
        selector: '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(1)',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Share Capital Equity',
        desc: 'Cumulative member equity investment held in the cooperative capital build-up account.',
        selector: '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(2)',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Total Loans Outstanding',
        desc: 'Current outstanding loan principal balance across all active member credit facilities.',
        selector: '.grid.grid-cols-1.md\\:grid-cols-3 > div:nth-child(3)',
        badgePos: 'left',
        padding: 5
      }
    ]
  },
  {
    id: 'fig10',
    filename: 'fig10_member_services_hub.png',
    title: 'Figure 10: Member Services Hub — Operational Catalog & Real-Time Account Summary',
    url: `${BASE_URL}/dashboard/services`,
    beforeAction: async (page) => {
      await page.locator('button:has-text("Deposit Money")').waitFor({ state: 'visible', timeout: 15000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Deposit Money Action',
        desc: 'Launches the deposit wizard to add funds to savings or share capital accounts.',
        selector: 'button:has-text("Deposit Money")',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Withdraw Money Action',
        desc: 'Opens the withdrawal wizard to request cash disbursement from available savings.',
        selector: 'button:has-text("Withdraw Money")',
        badgePos: 'right',
        padding: 5
      },
      {
        num: 3,
        title: 'Apply for Loan Action',
        desc: 'Initiates a new credit application for micro-business, provident, or emergency loans.',
        selector: 'button:has-text("Apply for Loan")',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 4,
        title: 'Make Payment Action',
        desc: 'Opens the loan payment wizard to settle scheduled amortizations and penalties.',
        selector: 'button:has-text("Make Payment")',
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig11a',
    filename: 'fig11a_deposit_step1_details.png',
    title: 'Figure 11: Deposit Process — Step 1: Account Type & Amount Specification',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] select').waitFor({ state: 'visible', timeout: 15000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Account Type Dropdown',
        desc: 'Selects destination ledger: liquid Savings Account or Share Capital investment.',
        selector: 'div[role="dialog"] select',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Deposit Amount Input',
        desc: 'Numeric amount field in Philippine Pesos (PHP) to be deposited.',
        selector: 'div[role="dialog"] input[type="number"]',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Next Step Button',
        desc: 'Validates inputs and advances to payment method selection.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig11b',
    filename: 'fig11b_deposit_step2_payment_method.png',
    title: 'Figure 12: Deposit Process — Step 2: Payment Method & Channel Selection',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] input[type="number"]').fill('1000');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 5000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Over the Counter Option',
        desc: 'Selects physical cash remittance at the cooperative branch counter.',
        selector: 'div[role="dialog"] button:has-text("Over the Counter")',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Bank Transfer Option',
        desc: 'Selects offline bank deposit or transfer requiring upload of deposit receipt proof.',
        selector: 'div[role="dialog"] button:has-text("Bank Transfer")',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Next Step Button',
        desc: 'Proceeds to the final review and confirmation screen.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig11c',
    filename: 'fig11c_deposit_step3_confirmation.png',
    title: 'Figure 13: Deposit Process — Step 3: Transaction Review & Final Confirmation',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] input[type="number"]').fill('1000');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 5000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 5000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Deposit Summary Card',
        desc: 'Itemizes target account type, deposit amount (₱1,000.00), channel, and zero fees.',
        selector: 'div[role="dialog"] .bg-gray-50',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Confirm Deposit Button',
        desc: 'Submits the deposit transaction to the accounting queue for cashier validation.',
        selector: 'div[role="dialog"] button:has-text("Confirm")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig11d',
    filename: 'fig11d_deposit_step4_complete.png',
    title: 'Figure 14: Deposit Process — Step 4: Transaction Submission Confirmation Slip',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] input[type="number"]').fill('1000');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 5000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 5000 });
      await page.locator('div[role="dialog"] button:has-text("Confirm")').click();
      await page.locator('div[role="dialog"] button:has-text("Close"), div[role="dialog"] button:has-text("Done")').first().waitFor({ state: 'visible', timeout: 10000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Transaction Reference Code',
        desc: 'Unique tracking number generated by the system to present to the cashier.',
        selector: 'div[role="dialog"] .font-mono',
        requireModalScope: true,
        badgePos: 'left',
        padding: 4
      },
      {
        num: 2,
        title: 'Close Action Button',
        desc: 'Dismisses the modal and returns to the Services Hub.',
        selector: 'div[role="dialog"] button:has-text("Close"), div[role="dialog"] button:has-text("Done")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig12a',
    filename: 'fig12a_withdraw_step1_details.png',
    title: 'Figure 15: Withdrawal Process — Step 1: Account & Amount Specification',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] .bg-green-50:not(:has-text("₱0.00"))').first().waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(400);
    },
    callouts: [
      {
        num: 1,
        title: 'Available Balance Indicator',
        desc: 'Live calculation of available savings excluding pending reserved claims.',
        selector: 'div[role="dialog"] .bg-green-50',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Withdrawal Amount Input',
        desc: 'Numeric amount field in Philippine Pesos (PHP) within available balance limits.',
        selector: 'div[role="dialog"] input[type="number"]',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Reason Textarea',
        desc: 'Required explanation of withdrawal purpose for fiduciary audit.',
        selector: 'div[role="dialog"] textarea',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 4,
        title: 'Next Step Button',
        desc: 'Validates available balance and advances to collection channel step.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig12b',
    filename: 'fig12b_withdraw_step2_collection_method.png',
    title: 'Figure 16: Withdrawal Process — Step 2: Collection Channel Selection',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] .bg-green-50:not(:has-text("₱0.00"))').first().waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(300);
      await page.locator('div[role="dialog"] input[type="number"]').fill('500');
      await page.locator('div[role="dialog"] textarea').fill('Emergency medical expenses');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Over the Counter Option',
        desc: 'Specifies physical cash release at the cooperative office counter.',
        selector: 'div[role="dialog"] button:has-text("Over the Counter")',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Next Step Button',
        desc: 'Advances to the withdrawal confirmation and balance impact review.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig12c',
    filename: 'fig12c_withdraw_step3_confirmation.png',
    title: 'Figure 17: Withdrawal Process — Step 3: Available Balance Verification & Review',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] .bg-green-50:not(:has-text("₱0.00"))').first().waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(300);
      await page.locator('div[role="dialog"] input[type="number"]').fill('500');
      await page.locator('div[role="dialog"] textarea').fill('Emergency medical expenses');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Withdrawal Summary Card',
        desc: 'Displays amount (₱500.00), reason, collection method, and post-transaction balance.',
        selector: 'div[role="dialog"] .bg-gray-50',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Confirm Withdrawal Button',
        desc: 'Locks the reserved amount and submits the withdrawal claim to the manager approval queue.',
        selector: 'div[role="dialog"] button:has-text("Confirm")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig12d',
    filename: 'fig12d_withdraw_step4_complete.png',
    title: 'Figure 18: Withdrawal Process — Step 4: Withdrawal Request Submission Slip',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] .bg-green-50:not(:has-text("₱0.00"))').first().waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(300);
      await page.locator('div[role="dialog"] input[type="number"]').fill('500');
      await page.locator('div[role="dialog"] textarea').fill('Emergency medical expenses');
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Confirm")').click();
      await page.locator('div[role="dialog"] button:has-text("Close"), div[role="dialog"] button:has-text("Done")').first().waitFor({ state: 'visible', timeout: 10000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Tracking Reference Code',
        desc: 'Official receipt transaction code assigned to the queued withdrawal claim.',
        selector: 'div[role="dialog"] .font-mono',
        requireModalScope: true,
        badgePos: 'left',
        padding: 4
      },
      {
        num: 2,
        title: 'Close Modal Action',
        desc: 'Closes the acknowledgment modal and returns to the active services dashboard.',
        selector: 'div[role="dialog"] button:has-text("Close"), div[role="dialog"] button:has-text("Done")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig13',
    filename: 'fig13_member_loan_application_wizard.png',
    title: 'Figure 19: Member Loan Application — Multi-Stage Eligibility & Requirement Wizard',
    url: `${BASE_URL}/dashboard/services?service=loan`,
    beforeAction: async (page) => {
      await page.locator('div[role="dialog"] .grid.grid-cols-1.md\\:grid-cols-2 button').first().waitFor({ state: 'visible', timeout: 15000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Loan Products Grid',
        desc: 'Catalog of active credit facilities (Micro-Business, Provident, Emergency loans).',
        selector: 'div[role="dialog"] .grid.grid-cols-1.md\\:grid-cols-2',
        requireModalScope: true,
        badgePos: 'left',
        padding: 6
      },
      {
        num: 2,
        title: 'Select Product Action',
        desc: 'Click any loan card to view requirements and proceed to amount and term entry.',
        selector: 'div[role="dialog"] .grid.grid-cols-1.md\\:grid-cols-2 button:first-child',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig14a',
    filename: 'fig14a_loan_payment_step1_details.png',
    title: 'Figure 20: Loan Repayment — Step 1: Installment Selection & Due Amount Review',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    beforeAction: async (page) => {
      const select = page.locator('div[role="dialog"] select');
      await select.waitFor({ state: 'visible', timeout: 15000 });
      await select.selectOption('16');
      await page.locator('div[role="dialog"] input[type="checkbox"]').first().waitFor({ state: 'visible', timeout: 10000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Target Loan Selector',
        desc: 'Selects the active loan account to amortize from the member loan portfolio.',
        selector: 'div[role="dialog"] select',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Installment Selection Checkbox',
        desc: 'Selects one or more scheduled installments in chronological order.',
        selector: 'div[role="dialog"] input[type="checkbox"]',
        requireModalScope: true,
        badgePos: 'left',
        padding: 4
      },
      {
        num: 3,
        title: 'Next Step Button',
        desc: 'Advances to payment channel selection.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig14b',
    filename: 'fig14b_loan_payment_step2_payment_method.png',
    title: 'Figure 21: Loan Repayment — Step 2: Payment Channel Selection',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    beforeAction: async (page) => {
      const select = page.locator('div[role="dialog"] select');
      await select.waitFor({ state: 'visible', timeout: 15000 });
      await select.selectOption('16');
      await page.waitForTimeout(500);
      const chk = page.locator('div[role="dialog"] input[type="checkbox"]').first();
      await chk.waitFor({ state: 'visible', timeout: 10000 });
      await chk.check();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Over the Counter Option',
        desc: 'Specifies cash payment at the cooperative office counter.',
        selector: 'div[role="dialog"] button:has-text("Over the Counter")',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Next Step Button',
        desc: 'Advances to installment allocation breakdown and review.',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig14c',
    filename: 'fig14c_loan_payment_step3_confirmation.png',
    title: 'Figure 22: Loan Repayment — Step 3: Principal, Interest & Penalty Breakdown',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    beforeAction: async (page) => {
      const select = page.locator('div[role="dialog"] select');
      await select.waitFor({ state: 'visible', timeout: 15000 });
      await select.selectOption('16');
      await page.waitForTimeout(500);
      const chk = page.locator('div[role="dialog"] input[type="checkbox"]').first();
      await chk.waitFor({ state: 'visible', timeout: 10000 });
      await chk.check();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Repayment Breakdown Card',
        desc: 'Itemizes target loan number, installment period, principal, interest, and late penalties.',
        selector: 'div[role="dialog"] .bg-gray-50',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Confirm Payment Button',
        desc: 'Transmits the payment record to the teller queue for verification.',
        selector: 'div[role="dialog"] button:has-text("Confirm")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig14d',
    filename: 'fig14d_loan_payment_step4_complete.png',
    title: 'Figure 23: Loan Repayment — Step 4: Repayment Submission Confirmation Slip',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    beforeAction: async (page) => {
      const select = page.locator('div[role="dialog"] select');
      await select.waitFor({ state: 'visible', timeout: 15000 });
      await select.selectOption('16');
      await page.waitForTimeout(500);
      const chk = page.locator('div[role="dialog"] input[type="checkbox"]').first();
      await chk.waitFor({ state: 'visible', timeout: 10000 });
      await chk.check();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Over the Counter")').click();
      await page.waitForTimeout(200);
      await page.locator('div[role="dialog"] button:has-text("Next")').click();
      await page.locator('div[role="dialog"] button:has-text("Confirm")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('div[role="dialog"] button:has-text("Confirm")').click();
      await page.locator('div[role="dialog"] button:has-text("Close")').waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    afterAction: async () => {
      clearActiveSessions();
    },
    callouts: [
      {
        num: 1,
        title: 'Official Transaction Reference',
        desc: 'Unique repayment tracking number to present at the cashier window.',
        selector: 'div[role="dialog"] .font-mono',
        requireModalScope: true,
        badgePos: 'left',
        padding: 4
      },
      {
        num: 2,
        title: 'Close Action Button',
        desc: 'Concludes the repayment wizard and returns to the active service catalog.',
        selector: 'div[role="dialog"] button:has-text("Close")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig15',
    filename: 'fig15_member_loans_management.png',
    title: 'Figure 24: Member Loan Portfolio — Active Amortization Schedule & Payment Tracking',
    url: `${BASE_URL}/dashboard/member-loans`,
    beforeAction: async (page) => {
      await page.locator('table tbody tr').first().waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Loan Account Record',
        desc: 'Displays loan tracking identifier and credit product classification.',
        selector: 'table tbody tr:first-child td:first-child',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Principal & Repayment Metrics',
        desc: 'Reflects approved principal, current interest rate, and total repayment liability.',
        selector: 'table tbody tr:first-child td:nth-child(3)',
        badgePos: 'top',
        padding: 5
      },
      {
        num: 3,
        title: 'Loan Servicing Status',
        desc: 'Indicates current loan servicing status (Active, Approved, or Pending).',
        selector: 'table tbody tr:first-child td:nth-child(5)',
        badgePos: 'right',
        padding: 4
      }
    ]
  },
  {
    id: 'fig16',
    filename: 'fig16_member_transaction_history.png',
    title: 'Figure 25: Member Transaction Passbook — Historical Ledger of Deposits & Repayments',
    url: `${BASE_URL}/dashboard/history`,
    beforeAction: async (page) => {
      await page.locator('.space-y-3 > div.rounded-xl').first().waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Transaction Type Filter',
        desc: 'Filter passbook entries by Deposits, Withdrawals, Interest, or Loan Payments.',
        selector: '.table-toolbar select:has(option[value="deposit"])',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Passbook Ledger Entry',
        desc: 'Immutable historical record displaying date, reference code, transaction type, and amount.',
        selector: '.space-y-3 > div.rounded-xl:first-child',
        badgePos: 'left',
        padding: 5
      }
    ]
  },
  {
    id: 'fig17',
    filename: 'fig17_member_announcements.png',
    title: 'Figure 26: Member Communication Hub — Cooperative Announcements & Events',
    url: `${BASE_URL}/dashboard/announcements`,
    beforeAction: async (page) => {
      await page.locator('.space-y-3 > div.rounded-lg').first().waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Featured Announcement Card',
        desc: 'Official circular broadcasting Board decisions, dividend payouts, or assembly schedules.',
        selector: '.space-y-3 > div.rounded-lg:first-child',
        badgePos: 'left',
        padding: 6
      }
    ]
  },
  {
    id: 'fig18',
    filename: 'fig18_member_notifications.png',
    title: 'Figure 27: Member Notification Center — Real-Time Transaction Alerts & Due Date Warnings',
    url: `${BASE_URL}/dashboard/notifications`,
    beforeAction: async (page) => {
      await page.locator('.space-y-2 > button').first().waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Notification Alert Item',
        desc: 'Real-time alert regarding deposit confirmations, loan status updates, or payment reminders.',
        selector: '.space-y-2 > button:first-child',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Mark All Read Action',
        desc: 'Acknowledges and clears all unread notification badges in a single operation.',
        selector: 'button:has-text("Mark All Read")',
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig19',
    filename: 'fig19_member_settings_profile.png',
    title: 'Figure 28: Member Profile & KYC Record — Personal Information & Contact Details',
    url: `${BASE_URL}/dashboard/profile`,
    beforeAction: async (page) => {
      await page.locator('.space-y-8 > div').first().waitFor({ state: 'visible', timeout: 12000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Member Demographic Record',
        desc: 'Verified legal full name, date of birth, civil status, and educational background.',
        selector: '.space-y-8 > div:first-child',
        badgePos: 'left',
        padding: 6
      },
      {
        num: 2,
        title: 'Government IDs & KYC Section',
        desc: 'Verified National ID, TIN, SSS, and PhilHealth identification numbers.',
        selector: '.space-y-8 > div:nth-child(2)',
        badgePos: 'left',
        padding: 6
      }
    ]
  },
  {
    id: 'fig20',
    filename: 'fig20_member_settings_security.png',
    title: 'Figure 29: Member Account Security — Password Modification & OTP Management',
    url: `${BASE_URL}/dashboard/settings?tab=account`,
    beforeAction: async (page) => {
      const btn = page.locator('button:has-text("Change Password")').first();
      await btn.waitFor({ state: 'visible', timeout: 8000 });
      await btn.click();
      await page.locator('input[placeholder*="6-digit"]').waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'Email Security Verification Field',
        desc: 'Input for the 6-digit one-time code required before committing credential updates.',
        selector: 'input[placeholder*="6-digit"]',
        badgePos: 'left',
        padding: 4
      },
      {
        num: 2,
        title: 'Send Verification Code Action',
        desc: 'Dispatches a fresh 6-digit OTP code to the member registered email inbox.',
        selector: 'button:has-text("Send code")',
        badgePos: 'right',
        padding: 4
      },
      {
        num: 3,
        title: 'Update Password Button',
        desc: 'Validates credential complexity and revokes untrusted concurrent sessions.',
        selector: 'button:has-text("Update Password")',
        badgePos: 'right',
        padding: 5
      }
    ]
  },
  {
    id: 'fig21',
    filename: 'fig21_member_help_support.png',
    title: 'Figure 30: Member Support Help Desk — Inquiries, FAQs, and Ticket Filing',
    url: `${BASE_URL}/dashboard/settings?tab=help-support`,
    beforeAction: async (page) => {
      const subTab = page.locator('button:has-text("Active Tickets & Messages")');
      await subTab.waitFor({ state: 'visible', timeout: 8000 });
      await subTab.click();
      await page.locator('button:has-text("New Ticket")').waitFor({ state: 'visible', timeout: 8000 });
      await page.locator('.space-y-3 > div.group').first().waitFor({ state: 'visible', timeout: 8000 });
      await page.waitForTimeout(500);
    },
    callouts: [
      {
        num: 1,
        title: 'New Ticket Action Button',
        desc: 'Opens the support ticket creation form to submit formal inquiries or dispute transactions.',
        selector: 'button:has-text("New Ticket")',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Support Tickets Queue Card',
        desc: 'Displays active member ticket tracking number, category, priority, and inquiry status.',
        selector: '.space-y-3 > div.group:first-child',
        badgePos: 'left',
        padding: 6
      }
    ]
  }
];

async function run() {
  console.log('=== Stage 1: Capturing Clean Screenshots & Geometry for Chapter 3 (Member Portal) ===');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: SCALE
  });

  await context.route('**/*cloudflare.com/**', route => route.abort());

  const page = await context.newPage();
  await loginAsMember(page);

  for (const task of CHAPTER_3_TASKS) {
    console.log(`\nCapturing: ${task.filename} (${task.title})`);

    await page.goto(task.url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);

    if (task.beforeAction) {
      await task.beforeAction(page);
    }

    await waitForGeometryStability(page, task.callouts);

    const resolvedCallouts = [];
    for (const c of task.callouts) {
      const loc = page.locator(c.selector).first();
      const count = await loc.count();
      if (count === 0) {
        throw new Error(`[FATAL] Selector not found for [${c.num}] ${c.title}: ${c.selector}`);
      }

      if (!c.allowHeaderMatch) {
        const isAccidentalHeaderMatch = await loc.evaluate((el) => {
          const inTopNavbar = Boolean(el.closest('header.sticky, header.top-0, nav'));
          const isLangSelect = el.tagName === 'SELECT' && Boolean(el.closest('header, nav'));
          return inTopNavbar || isLangSelect;
        });
        if (isAccidentalHeaderMatch) {
          throw new Error(`[FATAL] Selector "${c.selector}" for "${c.title}" matched an element inside the top header/navbar!`);
        }
      }

      if (c.requireModalScope) {
        const insideModal = await loc.evaluate((el) => Boolean(el.closest('[role="dialog"]')));
        if (!insideModal) {
          throw new Error(`[FATAL] Selector "${c.selector}" for "${c.title}" did NOT match inside [role="dialog"]!`);
        }
      }

      const box = await loc.boundingBox();
      if (!box) {
        throw new Error(`[FATAL] BoundingBox null for [${c.num}] ${c.title}: ${c.selector}`);
      }

      const elSummary = await loc.evaluate((el) => {
        const tag = el.tagName.toLowerCase();
        const id = el.id ? `#${el.id}` : '';
        const text = (el.innerText || el.getAttribute('placeholder') || el.getAttribute('aria-label') || '').trim().slice(0, 40);
        return `<${tag}${id}> "${text}"`;
      });

      const pad = c.padding ?? 6;
      resolvedCallouts.push({
        num: c.num,
        title: c.title,
        desc: c.desc,
        selector: c.selector,
        matchedElement: elSummary,
        badgePos: c.badgePos || 'top-left',
        padding: pad,
        cssBox: {
          x: Math.round(box.x * 100) / 100,
          y: Math.round(box.y * 100) / 100,
          width: Math.round(box.width * 100) / 100,
          height: Math.round(box.height * 100) / 100
        },
        pixelBox: {
          x: Math.round((box.x - pad) * SCALE),
          y: Math.round((box.y - pad) * SCALE),
          width: Math.round((box.width + pad * 2) * SCALE),
          height: Math.round((box.height + pad * 2) * SCALE)
        }
      });
      console.log(`  -> [${c.num}] ${c.title}: matched ${elSummary} at css=(${Math.round(box.x)}, ${Math.round(box.y)}, ${Math.round(box.width)}x${Math.round(box.height)})`);
    }

    const rawPath = path.join(RAW_DIR, task.filename);
    await page.screenshot({ path: rawPath, type: 'png' });

    const manifestName = task.filename.replace(/\.png$/i, '.json');
    const manifestPath = path.join(MANIFEST_DIR, manifestName);
    const manifest = {
      id: task.id,
      filename: task.filename,
      title: task.title,
      viewport: VIEWPORT,
      deviceScaleFactor: SCALE,
      imageSize: {
        width: VIEWPORT.width * SCALE,
        height: VIEWPORT.height * SCALE
      },
      callouts: resolvedCallouts
    };
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');
    console.log(`  -> Saved raw PNG & manifest: ${manifestName}`);

    if (task.afterAction) {
      await task.afterAction(page);
    }
  }

  clearActiveSessions();
  await browser.close();
  console.log('\nStage 1 for Chapter 3 complete!');
}

run().catch(err => {
  clearActiveSessions();
  console.error('Stage 1 failed:', err);
  process.exit(1);
});
