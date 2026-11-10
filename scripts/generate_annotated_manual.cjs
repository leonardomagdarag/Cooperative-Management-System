/**
 * Automated Annotated Screenshot Generator for Capstone User & Security Manual
 * Uses Playwright + Driver.js + SVG/Canvas Overlays to produce high-contrast, large-text,
 * arrow-free publication-ready screenshots for Word/PDF embedding.
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUTPUT_DIR = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots');
const DRIVER_JS_PATH = path.resolve(__dirname, '../node_modules/driver.js/dist/driver.js.iife.js');
const DRIVER_CSS_PATH = path.resolve(__dirname, '../node_modules/driver.js/dist/driver.css');
const LOG_PATH = path.resolve(__dirname, '../storage/logs/laravel.log');

if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

function clearActiveSessions() {
  try {
    execSync('php artisan tinker --execute="App\\Models\\User::query()->update([\'current_session_id\' => null]);"', { stdio: 'ignore' });
  } catch {}
}

function getLatestOtpForEmail(email) {
  if (!fs.existsSync(LOG_PATH)) return null;
  const content = fs.readFileSync(LOG_PATH, 'utf8');
  const escaped = email.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = [...content.matchAll(new RegExp(`for ${escaped}:\\s*(\\d{6})`, 'g'))];
  if (matches.length > 0) {
    return matches[matches.length - 1][1];
  }
  return null;
}

async function loginAsRole(page, email, password = 'password') {
  clearActiveSessions();
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);

  const emailInput = await page.$('input#email');
  if (emailInput) {
    await page.fill('input#email', email);
    await page.fill('input#password', password);
    await page.click('button[type="submit"]');

    try {
      await page.waitForURL(/\/dashboard|\/security\/verify-otp/, { timeout: 15000 });
    } catch {}

    if (page.url().includes('/security/verify-otp')) {
      let otp = null;
      for (let i = 0; i < 10; i++) {
        otp = getLatestOtpForEmail(email);
        if (otp) break;
        await page.waitForTimeout(500);
      }
      if (otp) {
        await page.fill('input#otp', otp);
        await page.click('button:has-text("Verify and continue")');
        await page.waitForURL(/\/dashboard/, { timeout: 15000 });
      }
    }
  }
}

async function injectDriverAndStyles(page) {
  await page.addScriptTag({ path: DRIVER_JS_PATH });
  await page.addStyleTag({ path: DRIVER_CSS_PATH });

  await page.addStyleTag({
    content: `
      .driver-popover.print-optimized-popover {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-radius: 20px !important;
        box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(0,0,0,0.05) !important;
        padding: 30px 34px !important;
        max-width: 660px !important;
        border: 5px solid #0284c7 !important;
        font-family: inherit !important;
      }
      .driver-popover.print-optimized-popover .driver-popover-arrow {
        display: none !important;
      }
      .driver-popover.print-optimized-popover .driver-popover-title {
        font-size: 32px !important;
        font-weight: 800 !important;
        color: #0369a1 !important;
        margin-bottom: 14px !important;
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        line-height: 1.25 !important;
      }
      .driver-popover.print-optimized-popover .driver-popover-description {
        font-size: 24px !important;
        line-height: 1.5 !important;
        color: #1e293b !important;
        font-weight: 600 !important;
        margin-bottom: 18px !important;
      }
    `
  });
}

async function applyMultiCallout(page, callouts) {
  await page.evaluate((callouts) => {
    if (window._driverObj) window._driverObj.destroy();
    document.querySelectorAll('.driver-overlay, .driver-popover, .manual-callout-overlay').forEach(e => e.remove());

    const overlay = document.createElement('div');
    overlay.className = 'manual-callout-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.pointerEvents = 'none';
    overlay.style.zIndex = '99999';
    overlay.innerHTML = `<div id="manual-cards-container" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>`;
    document.body.appendChild(overlay);

    const container = document.getElementById('manual-cards-container');

    function findElement(c) {
      if (c.selector) {
        try {
          const el = document.querySelector(c.selector);
          if (el) return el;
        } catch {}
      }
      if (c.fallbackSelector) {
        try {
          const el = document.querySelector(c.fallbackSelector);
          if (el) return el;
        } catch {}
      }
      if (c.textMatch) {
        const all = Array.from(document.querySelectorAll('h1, h2, h3, h4, p, a, button, div, span, label, article, section, select, input'));
        const matched = all.find(el => el.innerText && el.innerText.toLowerCase().includes(c.textMatch.toLowerCase()));
        if (matched) return matched;
      }
      return null;
    }

    callouts.forEach(c => {
      const el = findElement(c);

      if (el) {
        const rect = el.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
          const highlightBox = document.createElement('div');
          highlightBox.style.position = 'absolute';
          highlightBox.style.top = `${rect.top - 5}px`;
          highlightBox.style.left = `${rect.left - 5}px`;
          highlightBox.style.width = `${rect.width + 10}px`;
          highlightBox.style.height = `${rect.height + 10}px`;
          highlightBox.style.border = '4px solid #0284c7';
          highlightBox.style.backgroundColor = 'rgba(2, 132, 199, 0.12)';
          highlightBox.style.borderRadius = '12px';
          highlightBox.style.boxShadow = '0 0 20px rgba(2, 132, 199, 0.6)';
          highlightBox.style.pointerEvents = 'none';
          highlightBox.style.zIndex = '100000';

          const badge = document.createElement('div');
          badge.innerText = c.num;
          badge.style.position = 'absolute';
          badge.style.top = '-18px';
          badge.style.left = '-18px';
          badge.style.width = '38px';
          badge.style.height = '38px';
          badge.style.backgroundColor = '#0284c7';
          badge.style.color = '#ffffff';
          badge.style.borderRadius = '50%';
          badge.style.display = 'flex';
          badge.style.alignItems = 'center';
          badge.style.justifyContent = 'center';
          badge.style.fontSize = '20px';
          badge.style.fontWeight = '900';
          badge.style.boxShadow = '0 4px 10px rgba(0,0,0,0.35)';
          badge.style.border = '3px solid #ffffff';
          highlightBox.appendChild(badge);
          container.appendChild(highlightBox);
        }
      }

      // Card in negative space (1.5x scale)
      const card = document.createElement('div');
      card.style.position = 'absolute';
      if (c.cardPos.top !== undefined) card.style.top = `${c.cardPos.top}px`;
      if (c.cardPos.bottom !== undefined) card.style.bottom = `${c.cardPos.bottom}px`;
      if (c.cardPos.left !== undefined) card.style.left = `${c.cardPos.left}px`;
      if (c.cardPos.right !== undefined) card.style.right = `${c.cardPos.right}px`;
      card.style.width = c.cardPos.width ? `${c.cardPos.width}px` : '440px';
      card.style.backgroundColor = '#ffffff';
      card.style.border = '4px solid #0284c7';
      card.style.borderRadius = '16px';
      card.style.padding = '16px 20px';
      card.style.boxShadow = '0 20px 40px -10px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(2, 132, 199, 0.2)';
      card.style.fontFamily = 'inherit';
      card.style.zIndex = '100001';

      card.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
          <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #0284c7; color: white; border-radius: 50%; font-size: 22px; font-weight: 900; flex-shrink: 0; box-shadow: 0 4px 8px rgba(2, 132, 199, 0.4);">${c.num}</span>
          <span style="font-weight: 800; font-size: 24px; color: #0369a1; letter-spacing: -0.01em;">${c.title}</span>
        </div>
        <div style="font-size: 20px; font-weight: 600; line-height: 1.4; color: #1e293b;">${c.desc}</div>
      `;
      container.appendChild(card);
    });
  }, callouts);

  await page.waitForTimeout(300);
}

// CHAPTER 2: PUBLIC & VISITOR PORTAL
const CHAPTER_2_TASKS = [
  {
    filename: 'fig01_public_landing_page.png',
    url: `${BASE_URL}/`,
    callouts: [
      { num: 1, title: 'Logo & Home', desc: 'Return to main landing page.', selector: 'nav a[href="/"]', cardPos: { top: 110, left: 40 } },
      { num: 2, title: 'Main Navigation', desc: 'Browse About, News, and Contact.', selector: 'nav a[href="/about"]', cardPos: { top: 110, left: 540 } },
      { num: 3, title: 'Sign In Portal', desc: 'Open member and officer login.', selector: 'a[href="/login"]', cardPos: { top: 110, right: 40 } },
      { num: 4, title: 'Explore Membership', desc: 'View qualifications and benefits.', selector: 'a[href="#about"]', cardPos: { top: 800, left: 120 } },
      { num: 5, title: 'Branch Office', desc: 'Find branch contacts & location.', selector: 'a[href="/contact"]', cardPos: { top: 800, left: 640 } }
    ]
  },
  {
    filename: 'fig02_public_landing_services.png',
    url: `${BASE_URL}/#about`,
    beforeAction: async (page) => {
      await page.evaluate(() => {
        const el = document.getElementById('about');
        if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
      });
      await page.waitForTimeout(500);
    },
    callouts: [
      { num: 1, title: 'About Cooperative', desc: 'Core purpose and community impact.', selector: '#about .max-w-3xl', cardPos: { top: 70, left: 40, width: 440 } },
      { num: 2, title: 'Operational Metrics', desc: 'Active members, assets, and service track record.', selector: '#about .grid', cardPos: { top: 70, right: 40, width: 440 } },
      { num: 3, title: 'Learn More Button', desc: 'Direct link to full institutional history.', selector: '#about a[href="/about"]', cardPos: { top: 400, left: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig03_public_about_us.png',
    url: `${BASE_URL}/about`,
    callouts: [
      { num: 1, title: 'About Us Overview', desc: 'Institutional background & community focus.', selector: 'h1.mb-6', cardPos: { top: 120, left: 40, width: 380 } },
      { num: 2, title: 'Cooperative Mission', desc: 'Accessible savings, credit & empowerment.', cardPos: { top: 120, right: 40, width: 380 } },
      { num: 3, title: 'Our History Narrative', desc: 'Founding roots in Brgy. Sto. Tomas, Calauan.', selector: 'h2.public-heading', cardPos: { top: 660, left: 30, width: 350 } }
    ]
  },
  {
    filename: 'fig04_public_news.png',
    url: `${BASE_URL}/news`,
    callouts: [
      { num: 1, title: 'News & Events Hub', desc: 'Official bulletins & assembly schedules.', selector: 'h1.mb-6', cardPos: { top: 140, left: 40, width: 380 } },
      { num: 2, title: 'Official Bulletins', desc: 'Recent announcements & cooperative circulars.', selector: 'h2.public-heading', cardPos: { top: 140, right: 40, width: 380 } },
      { num: 3, title: 'Announcement Circular', desc: 'Financial literacy & operational notices.', selector: 'div.space-y-4 > div:first-child', fallbackSelector: 'div.space-y-4', cardPos: { top: 590, right: 30, width: 350 } }
    ]
  },
  {
    filename: 'fig06_public_contact.png',
    url: `${BASE_URL}/contact`,
    callouts: [
      { num: 1, title: 'Inquiry Message Form', desc: 'Name, email, and inquiry subject fields.', selector: 'form.space-y-6', cardPos: { top: 320, left: 30, width: 350 } },
      { num: 2, title: 'Contact Details & Hours', desc: 'Office location, phone numbers & email.', selector: 'div.public-card.p-8.mb-3', cardPos: { top: 320, right: 30, width: 350 } },
      { num: 3, title: 'Send Message Action', desc: 'Submit inquiry directly to staff.', selector: 'form button.public-btn', fallbackSelector: 'button[type="submit"]', cardPos: { top: 740, left: 30, width: 350 } }
    ]
  },
  {
    filename: 'fig07_public_login.png',
    url: `${BASE_URL}/login`,
    callouts: [
      { num: 1, title: 'User Identifier', desc: 'Enter registered email or mobile number.', selector: 'input#email', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Account Password', desc: 'Enter account security password.', selector: 'input#password', cardPos: { top: 400, left: 60, width: 420 } },
      { num: 3, title: 'Password Recovery', desc: 'Request email reset link if forgotten.', selector: 'a[href="/forgot-password"]', cardPos: { top: 160, right: 60, width: 420 } },
      { num: 4, title: 'Sign In Action', desc: 'Authenticate encrypted cookie session.', selector: 'button[type="submit"]', cardPos: { top: 400, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig08_public_forgot_password.png',
    url: `${BASE_URL}/forgot-password`,
    callouts: [
      { num: 1, title: 'Recovery Stepper', desc: '3-stage verified password reset workflow.', selector: '.flex.items-center.justify-center.gap-3', fallbackSelector: 'h1', cardPos: { top: 110, left: 60, width: 430 } },
      { num: 2, title: 'Registered Email', desc: 'Specify email associated with account.', selector: 'input#email, form input', fallbackSelector: 'input', cardPos: { top: 360, left: 60, width: 430 } },
      { num: 3, title: 'Send Reset Link', desc: 'Dispatches signed verification link.', selector: 'button:has-text("Send Verification Code"), button[type="submit"]', fallbackSelector: 'button', cardPos: { top: 110, right: 60, width: 430 } },
      { num: 4, title: 'Return to Login', desc: 'Return to credentials sign-in screen.', selector: 'a[href="/login"]', fallbackSelector: 'a', cardPos: { top: 360, right: 60, width: 430 } }
    ]
  }
];

// CHAPTER 3: MEMBER SELF-SERVICE PORTAL
const CHAPTER_3_TASKS = [
  {
    filename: 'fig09_member_dashboard.png',
    role: 'member',
    url: `${BASE_URL}/dashboard`,
    callouts: [
      { num: 1, title: 'Savings Balance', desc: 'Live passbook savings ledger.', selector: '.bg-gradient-to-br.from-blue-500', cardPos: { top: 140, right: 40, width: 420 } },
      { num: 2, title: 'Share Capital', desc: 'Cumulative member equity & ownership.', selector: '.bg-gradient-to-br.from-emerald-500', cardPos: { top: 380, right: 40, width: 420 } },
      { num: 3, title: 'Navigation Sidebar', desc: 'Services, My Loans, History & Settings.', selector: 'aside, nav', cardPos: { top: 140, left: 280, width: 420 } },
      { num: 4, title: 'Account Overview', desc: 'Personal balance overview & status.', selector: 'h1', cardPos: { bottom: 60, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig10_member_services_hub.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services`,
    callouts: [
      { num: 1, title: 'Account Balances', desc: 'Quick overview of current balances.', selector: 'section[aria-labelledby="services-overview-title"]', cardPos: { top: 110, right: 40, width: 440 } },
      { num: 2, title: 'Deposit Money', desc: 'Add funds to savings or share capital.', textMatch: 'Deposit Money', cardPos: { top: 430, left: 280, width: 420 } },
      { num: 3, title: 'Withdraw Money', desc: 'Request withdrawal from savings.', textMatch: 'Withdraw Money', cardPos: { top: 430, right: 40, width: 420 } },
      { num: 4, title: 'Loan Services', desc: 'Apply for loan or record repayments.', textMatch: 'Apply for Loan', cardPos: { bottom: 40, left: 280, width: 420 } }
    ]
  },
  {
    filename: 'fig11a_deposit_step1_details.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    callouts: [
      { num: 1, title: 'Account Type', desc: 'Select Savings Account or Share Capital.', selector: 'select, [role="combobox"]', fallbackSelector: 'input[type="text"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Deposit Amount', desc: 'Specify currency amount to deposit.', selector: 'input[placeholder*="0.00"], input[type="number"]', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Proceed to Next Step', desc: 'Advance to payment channel selection.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig11b_deposit_step2_payment_method.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      const input = page.locator('input[placeholder*="0.00"], input[type="number"]').first();
      await input.fill('1000');
      await page.waitForTimeout(300);
      await page.locator('button:has-text("Next")').first().click();
      await page.waitForTimeout(800);
    },
    callouts: [
      { num: 1, title: 'Payment Channel', desc: 'Select OTC Cashier or Bank Transfer.', textMatch: 'Payment Method', fallbackSelector: 'button', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Channel Reference', desc: 'Specify transaction or teller reference.', selector: 'input[type="text"]', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Proceed to Review', desc: 'Advance to final review breakdown.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig11c_deposit_step3_confirmation.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    beforeAction: async (page) => {
      const input = page.locator('input[placeholder*="0.00"], input[type="number"]').first();
      await input.fill('1000');
      await page.waitForTimeout(300);
      await page.locator('button:has-text("Next")').first().click();
      await page.waitForTimeout(500);
      const nextBtn2 = page.locator('button:has-text("Next")').first();
      if (await nextBtn2.isVisible()) {
        await nextBtn2.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Deposit Summary', desc: 'Review target account, amount & details.', textMatch: 'Review', fallbackSelector: 'div', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Submit Deposit', desc: 'Transmit deposit request to cashier queue.', selector: 'button:has-text("Confirm"), button:has-text("Submit")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig11d_deposit_step4_complete.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=deposit`,
    callouts: [
      { num: 1, title: 'Acknowledgment Slip', desc: 'Official transaction reference & timestamp.', selector: 'div[class*="border"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Teller Presentation', desc: 'Present slip at branch teller counter.', selector: 'button:has-text("Done"), button:has-text("Close")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig12a_withdraw_step1_details.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    callouts: [
      { num: 1, title: 'Savings Balance', desc: 'Inspect available withdrawable funds.', selector: 'input[placeholder*="0.00"], select', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Withdrawal Amount', desc: 'Specify amount to withdraw from savings.', selector: 'input[type="number"], input[placeholder*="0.00"]', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Step Navigation', desc: 'Advance to collection method step.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig12b_withdraw_step2_collection_method.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      const input = page.locator('input[placeholder*="0.00"], input[type="number"]').first();
      await input.fill('500');
      await page.waitForTimeout(300);
      await page.locator('button:has-text("Next")').first().click();
      await page.waitForTimeout(800);
    },
    callouts: [
      { num: 1, title: 'Collection Channel', desc: 'Branch OTC Cashier cash release.', textMatch: 'Collection', fallbackSelector: 'button', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'ID Verification', desc: 'Valid government photo ID required.', textMatch: 'ID', fallbackSelector: 'p', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Step Navigation', desc: 'Advance to final review step.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig12c_withdraw_step3_confirmation.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    beforeAction: async (page) => {
      const input = page.locator('input[placeholder*="0.00"], input[type="number"]').first();
      await input.fill('500');
      await page.waitForTimeout(300);
      await page.locator('button:has-text("Next")').first().click();
      await page.waitForTimeout(500);
      const nextBtn2 = page.locator('button:has-text("Next")').first();
      if (await nextBtn2.isVisible()) {
        await nextBtn2.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Balance Impact', desc: 'Review deduction and remaining savings balance.', textMatch: 'Review', fallbackSelector: 'div', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Submit Withdrawal', desc: 'Submit withdrawal request for teller approval.', selector: 'button:has-text("Confirm"), button:has-text("Submit")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig12d_withdraw_step4_complete.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=withdraw`,
    callouts: [
      { num: 1, title: 'Tracking Code', desc: 'Queued request tracking number.', selector: 'div[class*="border"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Cashier Pickup', desc: 'Present reference at teller counter.', selector: 'button:has-text("Done"), button:has-text("Close")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig13_member_loan_application_wizard.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=loan`,
    callouts: [
      { num: 1, title: 'Loan Product', desc: 'Select Microfinance, Provident, or Emergency.', selector: 'select, [role="combobox"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Requested Amount', desc: 'Specify loan amount within credit limit.', selector: 'input[placeholder*="0.00"], input[type="number"]', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Repayment Term', desc: 'Select monthly or semi-monthly duration.', selector: 'select:last-of-type, input[type="text"]:last-of-type', cardPos: { top: 220, right: 60, width: 420 } },
      { num: 4, title: 'Submit Application', desc: 'Route application to loan officer review.', selector: 'button:has-text("Next"), button:has-text("Submit")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig14a_loan_payment_step1_details.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    callouts: [
      { num: 1, title: 'Target Loan', desc: 'Select active loan account to settle.', selector: 'select, [role="combobox"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Installment Amount', desc: 'Specify payment amount or regular due.', selector: 'input[placeholder*="0.00"], input[type="number"]', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Step Navigation', desc: 'Proceed to payment channel selection.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig14b_loan_payment_step2_payment_method.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    callouts: [
      { num: 1, title: 'Payment Channel', desc: 'External cash payment or bank deposit.', textMatch: 'Payment Method', fallbackSelector: 'button', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Fiduciary Rule', desc: 'External funds required; savings not debited.', textMatch: 'savings', fallbackSelector: 'p', cardPos: { top: 450, left: 280, width: 420 } },
      { num: 3, title: 'Step Navigation', desc: 'Advance to allocation breakdown.', selector: 'button:has-text("Next")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig14c_loan_payment_step3_confirmation.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    callouts: [
      { num: 1, title: 'Allocation Breakdown', desc: 'Principal, interest & penalty breakdown.', textMatch: 'Review', fallbackSelector: 'div', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Confirm Repayment', desc: 'Submit payment for cashier recording.', selector: 'button:has-text("Confirm"), button:has-text("Submit")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig14d_loan_payment_step4_complete.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/services?service=payment`,
    callouts: [
      { num: 1, title: 'Repayment Slip', desc: 'Certified temporary acknowledgment receipt.', selector: 'div[class*="border"]', cardPos: { top: 220, left: 280, width: 420 } },
      { num: 2, title: 'Amortization Update', desc: 'Updated principal & next installment date.', selector: 'button:has-text("Done"), button:has-text("Close")', cardPos: { top: 450, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig15_member_loans_management.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/member-loans`,
    callouts: [
      { num: 1, title: 'Loan Portfolio', desc: 'Active credit facilities and loan status.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Amortization Table', desc: 'Installment breakdown, due dates & balances.', selector: 'table, .public-card', cardPos: { top: 400, right: 40, width: 440 } },
      { num: 3, title: 'Make Repayment', desc: 'Direct link to loan settlement wizard.', selector: 'button:has-text("Pay"), a:has-text("Pay")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig16_member_transaction_history.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/history`,
    callouts: [
      { num: 1, title: 'Passbook Ledger', desc: 'Certified chronological transaction history.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Transaction Filters', desc: 'Filter deposits, withdrawals & loan payments.', selector: 'select, input[type="search"]', cardPos: { top: 400, right: 40, width: 440 } },
      { num: 3, title: 'Certified Records', desc: 'Audited balances, official receipt numbers.', selector: 'table', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig17_member_announcements.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/announcements`,
    callouts: [
      { num: 1, title: 'Member Bulletins', desc: 'Official circulars, schedules & dividend news.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Circular Cards', desc: 'Chronological list with publication metadata.', selector: 'div.space-y-4, .grid', cardPos: { top: 400, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig18_member_notifications.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/notifications`,
    callouts: [
      { num: 1, title: 'Real-Time Alerts', desc: 'Deposit approvals, due dates & security notices.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Alert Actions', desc: 'Mark all as read or inspect item details.', selector: 'button:has-text("Mark")', fallbackSelector: 'button', cardPos: { top: 400, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig19_member_settings_profile.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/settings`,
    callouts: [
      { num: 1, title: 'Member KYC Profile', desc: 'Legal name, member number & birth date.', selector: 'h1, h2', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Contact Information', desc: 'Verified mobile number & home address.', selector: 'form, div.space-y-4', cardPos: { top: 420, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig20_member_settings_security.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/settings`,
    beforeAction: async (page) => {
      const secTab = page.locator('button:has-text("Security"), a:has-text("Security")').first();
      if (await secTab.isVisible()) {
        await secTab.click();
        await page.waitForTimeout(600);
      }
    },
    callouts: [
      { num: 1, title: 'Password Management', desc: 'Change password with security enforcement.', selector: 'input[type="password"]', fallbackSelector: 'h2', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Session Controls', desc: 'Active login devices & remote termination.', textMatch: 'Session', fallbackSelector: 'button', cardPos: { top: 420, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig21_member_help_support.png',
    role: 'member',
    url: `${BASE_URL}/dashboard/tickets`,
    callouts: [
      { num: 1, title: 'Help Desk Tickets', desc: 'Support inquiries and resolution status.', selector: 'h1, h2', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Create Ticket', desc: 'Submit new question or assistance request.', selector: 'button:has-text("Ticket"), button:has-text("New")', fallbackSelector: 'button', cardPos: { top: 420, right: 40, width: 440 } }
    ]
  }
];

// CHAPTER 5: CASHIERING, ACCOUNTING & GENERAL LEDGER PORTAL (ACCOUNTANT)
const CHAPTER_5_TASKS = [
  {
    filename: 'fig26_accountant_dashboard.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard`,
    callouts: [
      { num: 1, title: 'Cash Flow Overview', desc: 'Real-time summary of daily inflows and expenses.', selector: '.bg-gradient-to-br.from-emerald-700', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Financial KPI Metrics', desc: 'Total savings deposits, share capital & loans.', selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Accounting Navigation', desc: 'Direct access to Ledger, Expenses, and Reports.', selector: 'aside, nav', cardPos: { top: 120, left: 280, width: 420 } }
    ]
  },
  {
    filename: 'fig27_accountant_counter_services.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/services`,
    callouts: [
      { num: 1, title: 'Member Selection', desc: 'Search and select member for walk-in transactions.', textMatch: 'Select a member', fallbackSelector: 'input[type="text"]', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Service Categories', desc: 'Process deposits, withdrawals, or loan payments.', selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4', fallbackSelector: '.grid', cardPos: { top: 400, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig28_accountant_general_ledger.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/ledger`,
    callouts: [
      { num: 1, title: 'Ledger Search & Filter', desc: 'Filter journal entries by category, date & status.', selector: '.flex.flex-col.sm\\:flex-row', fallbackSelector: 'input[type="text"]', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Chronological Entries', desc: 'Audited debit and credit transaction history.', selector: 'table', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Transaction Actions', desc: 'Inspect transaction details or reprint receipts.', selector: 'table tbody tr:first-child td:last-child', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig23a_transaction_details_modal.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/ledger`,
    beforeAction: async (page) => {
      const viewBtn = page.locator('table tbody tr:first-child button[title="View details"], table tbody tr:first-child button[aria-label="View transaction details"]').first();
      if (await viewBtn.isVisible()) {
        await viewBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Transaction Overview', desc: 'Official transaction number, date, and posting type.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 120, left: 60, width: 420 } },
      { num: 2, title: 'Financial Breakdown', desc: 'Principal amount, fee allocations & running balances.', selector: 'div[role="dialog"] .grid', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 400, left: 60, width: 420 } },
      { num: 3, title: 'Close Details Modal', desc: 'Dismiss dialog and return to General Ledger view.', selector: 'div[role="dialog"] button:has-text("Close")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 400, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig23b_transaction_receipt_modal.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/ledger`,
    beforeAction: async (page) => {
      const printBtn = page.locator('table tbody tr:first-child button[title="Reprint Receipt"]').first();
      if (await printBtn.isVisible()) {
        await printBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Certified Receipt', desc: 'Official certified duplicate transaction receipt.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 120, left: 60, width: 420 } },
      { num: 2, title: 'Transaction Metadata', desc: 'Date, teller reference & itemized amount.', selector: 'div[role="dialog"] .border', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 400, left: 60, width: 420 } },
      { num: 3, title: 'Print Receipt', desc: 'Print physical copy for member records.', selector: 'div[role="dialog"] button:has-text("Print")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 400, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig29_accountant_expenses_management.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/expenses`,
    callouts: [
      { num: 1, title: 'Expense Overview', desc: 'Total recorded operating expenses and summary.', selector: '.bg-amber-50', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Add Expense Voucher', desc: 'Encode new operational disbursement voucher.', selector: 'button:has-text("Add Expense")', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Expense Vouchers Table', desc: 'Itemized vouchers, categories, amounts and audit state.', selector: 'table', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig24a_expense_create_modal.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/expenses`,
    beforeAction: async (page) => {
      const addBtn = page.locator('button:has-text("Add Expense")').first();
      if (await addBtn.isVisible()) {
        await addBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Expense Category', desc: 'Select expense category (Utilities, Supplies, etc.).', selector: 'input[list="expense-categories"]', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Disbursement Amount', desc: 'Specify payment amount and disbursement date.', selector: 'input[placeholder="0.00"]', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Save Expense Voucher', desc: 'Commit operating expense to cooperative ledger.', selector: 'button[type="submit"]', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig24b_expense_edit_modal.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/expenses`,
    beforeAction: async (page) => {
      const editBtn = page.locator('table tbody tr:first-child button[title="Edit"]').first();
      if (await editBtn.isVisible()) {
        await editBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Dual-Control Policy', desc: 'Modifications require Chairman countersign approval.', selector: '.bg-amber-50', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Update Fields', desc: 'Modify category, description, or voucher amount.', selector: 'input[placeholder="0.00"]', cardPos: { top: 420, left: 60, width: 430 } },
      { num: 3, title: 'Submit for Approval', desc: 'Forward proposed changes to oversight queue.', selector: 'button:has-text("Submit for approval")', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig24c_expense_delete_modal.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/expenses`,
    beforeAction: async (page) => {
      const delBtn = page.locator('table tbody tr:first-child button[title="Delete"]').first();
      if (await delBtn.isVisible()) {
        await delBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Deletion Confirmation', desc: 'Safeguard confirmation before removing voucher.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Submit Deletion', desc: 'Submit deletion request for Chairman countersign.', selector: 'div[role="dialog"] button:has-text("Submit for approval")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig30_accountant_financial_reports.png',
    role: 'accountant',
    url: `${BASE_URL}/dashboard/reports`,
    callouts: [
      { num: 1, title: 'Financial Report Catalog', desc: 'Access Financial Summary, Balance Sheet & Member Savings.', selector: 'nav, .flex.space-x-2', fallbackSelector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Reporting Date Range', desc: 'Select start and end dates for accounting period.', selector: '.flex.flex-wrap.items-center.gap-2', fallbackSelector: 'input[type="date"]', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Export Statements', desc: 'Download audit-ready reports in Excel or PDF format.', selector: 'button:has-text("Export"), button:has-text("Download")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  }
];

// CHAPTER 6: MANAGER PORTAL
const CHAPTER_6_TASKS = [
  {
    filename: 'fig32_manager_dashboard.png',
    role: 'manager',
    url: `${BASE_URL}/dashboard`,
    callouts: [
      { num: 1, title: 'Executive Operations', desc: 'Branch-wide operational metrics and loan portfolio.', selector: '.bg-gradient-to-br.from-blue-700', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Pending Review Alerts', desc: 'Review alerts for loans, transactions & members.', selector: '.flex.flex-wrap.gap-3', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Manager Sidebar', desc: 'Access Members, Services, Loans, and Reports.', selector: 'aside, nav', cardPos: { top: 120, left: 280, width: 420 } }
    ]
  },
  {
    filename: 'fig33_manager_transactions_approval_queue.png',
    role: 'manager',
    url: `${BASE_URL}/dashboard/services`,
    beforeAction: async (page) => {
      const approveBtn = page.locator('button:has-text("Transactions"), button:has-text("Approve")').first();
      if (await approveBtn.isVisible()) {
        await approveBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Approval Queue', desc: 'Dual-control transaction verification for high-value entries.', selector: 'h2, h3, h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Pending Queue Table', desc: 'Review member, transaction type, amount, and channel.', selector: 'table, .divide-y', fallbackSelector: 'section', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Approve / Reject Action', desc: 'Authorize valid transactions or reject irregular requests.', selector: 'table tbody tr:first-child td:last-child, button:has-text("Approve")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig34_manager_members_directory.png',
    role: 'manager',
    url: `${BASE_URL}/dashboard/members`,
    callouts: [
      { num: 1, title: 'Member Directory', desc: 'Centralized registry of cooperative members & applicants.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Member Status Filters', desc: 'Filter by Active, Pending Approval, or Inactive status.', selector: '.table-toolbar, select', fallbackSelector: 'select', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Member KYC & Actions', desc: 'View profile KYC, approve new applications, or edit data.', selector: 'table tbody tr:first-child td:last-child', fallbackSelector: 'table', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  }
];

// CHAPTER 7: CHAIRMAN (BOARD GOVERNANCE) PORTAL
const CHAPTER_7_TASKS = [
  {
    filename: 'fig35_chairman_dashboard.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard`,
    callouts: [
      { num: 1, title: 'Governance Overview', desc: 'Cooperative-wide financial health & portfolio governance.', selector: '.bg-gradient-to-br.from-emerald-700', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Oversight Countersigns', desc: 'Pending privileged actions requiring Board countersign.', selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Governance Navigation', desc: 'Access Approvals, Officers, Branches & Audit Logs.', selector: 'aside, nav', cardPos: { top: 120, left: 280, width: 420 } }
    ]
  },
  {
    filename: 'fig36_chairman_oversight_approvals.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard/approvals`,
    callouts: [
      { num: 1, title: 'Privileged Action Queue', desc: 'Requests requiring second-officer countersign authorization.', selector: 'h1, h2', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Action Details & Maker', desc: 'Requesting officer identity, submission timestamp & changes.', selector: 'article:first-child', fallbackSelector: 'section', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Review & Approve', desc: 'Open Step-Up OTP challenge to confirm countersign.', selector: 'button:has-text("Review and approve")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig32a_countersign_otp_panel.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard/approvals`,
    beforeAction: async (page) => {
      const revBtn = page.locator('button:has-text("Review and approve")').first();
      if (await revBtn.isVisible()) {
        await revBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Countersign Summary', desc: 'Review impact and requested modifications in detail.', selector: 'section h2', fallbackSelector: 'section', cardPos: { top: 140, left: 60, width: 430 } },
      { num: 2, title: 'Step-Up OTP Challenge', desc: 'Enter 6-digit one-time code dispatched to email.', selector: 'input[inputmode="numeric"], input[autocomplete="one-time-code"]', cardPos: { top: 400, left: 60, width: 430 } },
      { num: 3, title: 'Verify and Approve', desc: 'Commit privileged action with cryptographic audit trail.', selector: 'button:has-text("Verify and approve")', cardPos: { top: 400, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig37_chairman_officers_management.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard/officers`,
    callouts: [
      { num: 1, title: 'Officer Directory', desc: 'Staff registry, institutional assignments & roles.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Branch & Role Badges', desc: 'Active Spatie roles and assigned branch affiliations.', selector: 'table tbody tr:first-child td:nth-child(2)', fallbackSelector: 'table', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Inspect Staff Profile', desc: 'View comprehensive KYC profile and system permissions.', selector: 'table tbody tr:first-child td:last-child button', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig38_chairman_branches_management.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard/branches`,
    callouts: [
      { num: 1, title: 'Branch Network', desc: 'Cooperative branch network and regional office registry.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Branch Data Scope', desc: 'Branch isolation boundaries and operational status.', selector: 'table, .grid', fallbackSelector: 'main', cardPos: { top: 380, right: 40, width: 440 } }
    ]
  },
  {
    filename: 'fig39_audit_logs.png',
    role: 'chairman',
    url: `${BASE_URL}/dashboard/logs`,
    callouts: [
      { num: 1, title: 'Immutable Audit Trail', desc: '365-day tamper-evident log of all system state changes.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Security Event Filter', desc: 'Filter by event type, actor officer, date range & IP.', selector: '.flex.flex-wrap, input[type="text"]', fallbackSelector: 'select', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Audit Event Records', desc: 'Actor name, event type, affected model & client IP.', selector: 'table', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  }
];

// CHAPTER 8: SYSTEM ADMINISTRATOR PORTAL
const CHAPTER_8_TASKS = [
  {
    filename: 'fig40_admin_dashboard.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard`,
    callouts: [
      { num: 1, title: 'System Health Dashboard', desc: 'Real-time database, environment, and services health.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Service Availability Matrix', desc: 'Status of public login, deposits, withdrawals & loans.', selector: '.grid', fallbackSelector: 'main', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Admin Controls Sidebar', desc: 'Access Officers, Roles, Service Availability & Backups.', selector: 'aside, nav', cardPos: { top: 120, left: 280, width: 420 } }
    ]
  },
  {
    filename: 'fig37a_officer_create_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/officers`,
    beforeAction: async (page) => {
      const addBtn = page.locator('button:has-text("Add Officer")').first();
      if (await addBtn.isVisible()) {
        await addBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Officer Demographics', desc: 'Enter legal name, contact email & staff credentials.', selector: 'input[name="first_name"], input[type="text"]', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Role & Branch Assignment', desc: 'Assign built-in or custom role and branch scope.', selector: 'select', fallbackSelector: 'input', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Create Officer Account', desc: 'Save staff account and initiate credential dispatch.', selector: 'button:has-text("Save"), button:has-text("Create")', fallbackSelector: 'button[type="submit"]', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig37b_officer_view_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/officers`,
    beforeAction: async (page) => {
      const viewBtn = page.locator('table tbody tr:first-child button[title*="profile"], table tbody tr:first-child button:has(.fa-eye)').first();
      if (await viewBtn.isVisible()) {
        await viewBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Officer Profile', desc: 'Staff KYC details, contact information and status.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 140, left: 60, width: 420 } },
      { num: 2, title: 'Effective Permissions', desc: 'Assigned Spatie roles and active permission sets.', selector: 'div[role="dialog"] .grid, div[role="dialog"] ul', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Close Profile', desc: 'Dismiss officer profile view.', selector: 'div[role="dialog"] button:has-text("Close")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig37c_officer_edit_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/officers`,
    beforeAction: async (page) => {
      const editBtn = page.locator('table tbody tr:first-child button[title*="Edit"], table tbody tr:first-child button:has(.fa-edit)').first();
      if (await editBtn.isVisible()) {
        await editBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Edit Officer Details', desc: 'Update staff demographic or contact information.', selector: 'input[name="first_name"], input[type="text"]', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Role Reassignment', desc: 'Adjust primary role or branch jurisdiction.', selector: 'select', fallbackSelector: 'input', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Save Modifications', desc: 'Commit officer account changes to database.', selector: 'button:has-text("Save"), button:has-text("Update")', fallbackSelector: 'button[type="submit"]', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig37d_officer_permissions_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/officers`,
    beforeAction: async (page) => {
      const permBtn = page.locator('table tbody tr:first-child button[title*="permission"], table tbody tr:first-child button:has(.fa-key), table tbody tr:first-child button:has(.fa-lock)').first();
      if (await permBtn.isVisible()) {
        await permBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Fine-Grained Overrides', desc: 'Explicit Allow or Deny overrides per officer.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 140, left: 60, width: 420 } },
      { num: 2, title: 'Permission Catalog', desc: 'Browse permissions grouped by functional domain.', selector: 'div[role="dialog"] .space-y-4, div[role="dialog"] table', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Save Overrides', desc: 'Apply permission overrides directly to account.', selector: 'div[role="dialog"] button:has-text("Save")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig37e_officer_delete_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/officers`,
    beforeAction: async (page) => {
      const delBtn = page.locator('table tbody tr:first-child button[title*="Delete"], table tbody tr:first-child button:has(.fa-trash)').first();
      if (await delBtn.isVisible()) {
        await delBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Deactivation Safeguard', desc: 'Confirmation alert before deactivating officer.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Confirm Deactivation', desc: 'Submit officer removal for Chairman countersign.', selector: 'div[role="dialog"] button:has-text("Submit"), div[role="dialog"] button:has-text("Delete")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig41_admin_roles_permissions.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/role-permissions`,
    callouts: [
      { num: 1, title: 'Roles & Permissions', desc: 'Configure Spatie permissions for database roles.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Add Custom Role', desc: 'Create new custom role tailored for institutional needs.', selector: 'button:has-text("Add Custom Role")', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Role Permissions Matrix', desc: 'Domain-grouped checkboxes for granular access control.', selector: '.space-y-4, .divide-y', fallbackSelector: 'main', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig38a_custom_role_create_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/role-permissions`,
    beforeAction: async (page) => {
      const addRoleBtn = page.locator('button:has-text("Add Custom Role")').first();
      if (await addRoleBtn.isVisible()) {
        await addRoleBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Role Identifier', desc: 'Define descriptive role name and internal key.', selector: 'input[placeholder*="Role"], input[type="text"]', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Role Template', desc: 'Optionally clone permissions from existing role preset.', selector: 'select', fallbackSelector: 'input', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Create Role', desc: 'Submit new role creation for Chairman countersign.', selector: 'button:has-text("Create"), button:has-text("Save")', fallbackSelector: 'button[type="submit"]', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  },
  {
    filename: 'fig42_admin_service_availability.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/service-availability`,
    callouts: [
      { num: 1, title: 'Public Portal Controls', desc: 'Toggle public website, registration & login gates.', selector: 'section:first-of-type', fallbackSelector: 'main', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Financial Service Gates', desc: 'Individual maintenance switches for deposits & loans.', selector: 'section:nth-of-type(2)', fallbackSelector: 'main', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Commit Availability', desc: 'Save changes with dual-control Chairman approval.', selector: 'button:has-text("Save Changes")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig43_admin_backup_restore.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/backups`,
    callouts: [
      { num: 1, title: 'Disaster Recovery Hub', desc: 'Automated GFS tier archives and offsite sync status.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'On-Demand Backup', desc: 'Trigger immediate daily backup snapshot.', selector: 'button:has-text("Back Up Now")', fallbackSelector: 'button', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Archive Tier Cards', desc: 'Daily, Weekly, and Monthly snapshot archives.', selector: '.grid.grid-cols-1.md\\:grid-cols-3', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig40a_backup_restore_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/backups`,
    beforeAction: async (page) => {
      const restoreBtn = page.locator('button:has-text("Restore")').first();
      if (await restoreBtn.isVisible()) {
        await restoreBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Recovery Warning', desc: 'Restoring replaces active database with snapshot.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Password Challenge', desc: 'Enter administrator password to authorize restore.', selector: 'input[type="password"]', fallbackSelector: 'input', cardPos: { top: 420, left: 60, width: 430 } },
      { num: 3, title: 'Submit for Countersign', desc: 'Submit restore request for Chairman countersign.', selector: 'div[role="dialog"] button:has-text("Submit"), div[role="dialog"] button:has-text("Restore")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig40b_backup_create_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/backups`,
    beforeAction: async (page) => {
      const backupBtn = page.locator('button:has-text("Back Up Now")').first();
      if (await backupBtn.isVisible()) {
        await backupBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Backup Parameters', desc: 'Configure tier and scope for immediate execution.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Execute Backup', desc: 'Start backup worker and generate database snapshot.', selector: 'div[role="dialog"] button:has-text("Start"), div[role="dialog"] button:has-text("Back up")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig40c_backup_delete_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/backups`,
    beforeAction: async (page) => {
      const cleanBtn = page.locator('button:has-text("Clean")').first();
      if (await cleanBtn.isVisible()) {
        await cleanBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Archive Pruning Policy', desc: 'Automated lifecycle pruning policy replaces manual delete.', selector: 'div[role="dialog"] h2, div[role="dialog"] h3', fallbackSelector: 'h2', cardPos: { top: 160, left: 60, width: 430 } },
      { num: 2, title: 'Confirm Pruning', desc: 'Prune local archives exceeding configured retention window.', selector: 'div[role="dialog"] button:has-text("Clean"), div[role="dialog"] button:has-text("Confirm")', fallbackSelector: 'button', cardPos: { top: 420, right: 60, width: 430 } }
    ]
  },
  {
    filename: 'fig44_admin_financial_accounts.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/financial-accounts`,
    callouts: [
      { num: 1, title: 'Savings Interest Rules', desc: 'Configure annual interest rate and minimum balance.', selector: 'section:first-of-type, .space-y-6 > div:first-child', fallbackSelector: 'main', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Share Capital Policy', desc: 'Dividend yield, compounding interval & required equity.', selector: 'section:nth-of-type(2)', fallbackSelector: 'main', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Withdrawal Boundaries', desc: 'Set minimum and maximum single withdrawal limits.', selector: 'section:nth-of-type(3)', fallbackSelector: 'main', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig45_admin_loan_products.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/loan-products`,
    callouts: [
      { num: 1, title: 'Loan Products Catalog', desc: 'Manage institutional credit facilities and criteria.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Add Loan Product', desc: 'Define new credit facility with interest and term rules.', selector: 'button:has-text("Add Product"), button:has-text("New")', fallbackSelector: 'button', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Product Parameters Table', desc: 'Interest rates, allowable terms, and principal limits.', selector: 'table', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig46_admin_integrations.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/integrations`,
    callouts: [
      { num: 1, title: 'SMS Gateway Credentials', desc: 'Configure Semaphore / PhilSMS API key and sender ID.', selector: 'section:first-of-type, form', fallbackSelector: 'main', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'SMTP Email Delivery', desc: 'Host, port, and credentials for transactional OTP mail.', selector: 'section:nth-of-type(2)', fallbackSelector: 'main', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Send Test Dispatch', desc: 'Verify API connectivity with live test message.', selector: 'button:has-text("Test"), button:has-text("Send")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig47_admin_public_content.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/public-content`,
    callouts: [
      { num: 1, title: 'Public CMS Management', desc: 'Manage public website content, news, and circulars.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Content Section Tabs', desc: 'Switch between News Articles, About Us, and FAQs.', selector: 'nav, .flex.space-x-2', fallbackSelector: 'button', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'Publish New Article', desc: 'Compose and publish public bulletin or news story.', selector: 'button:has-text("Add"), button:has-text("New"), button:has-text("Create")', fallbackSelector: 'button', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig48_admin_support_tickets.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/tickets`,
    callouts: [
      { num: 1, title: 'Support Desk Queue', desc: 'Monitor and triage member inquiries and help requests.', selector: 'h1', cardPos: { top: 120, right: 40, width: 440 } },
      { num: 2, title: 'Ticket Priority & Category', desc: 'Filter tickets by urgency, department, or open status.', selector: '.grid.grid-cols-2, select', fallbackSelector: 'select', cardPos: { top: 380, right: 40, width: 440 } },
      { num: 3, title: 'New Ticket Button', desc: 'Encode new inquiry or issue on behalf of member.', selector: 'button:has-text("New Ticket")', cardPos: { bottom: 50, left: 280, width: 440 } }
    ]
  },
  {
    filename: 'fig41a_ticket_create_modal.png',
    role: 'admin',
    url: `${BASE_URL}/dashboard/tickets`,
    beforeAction: async (page) => {
      const newTicketBtn = page.locator('button:has-text("New Ticket")').first();
      if (await newTicketBtn.isVisible()) {
        await newTicketBtn.click();
        await page.waitForTimeout(800);
      }
    },
    callouts: [
      { num: 1, title: 'Category & Urgency', desc: 'Select issue category and priority level.', selector: 'select', fallbackSelector: 'div[role="dialog"]', cardPos: { top: 160, left: 60, width: 420 } },
      { num: 2, title: 'Subject & Description', desc: 'Specify concise title and detailed explanation.', selector: 'input[placeholder*="Subject"], textarea', fallbackSelector: 'textarea', cardPos: { top: 420, left: 60, width: 420 } },
      { num: 3, title: 'Submit Support Ticket', desc: 'Transmit ticket to cooperative support team.', selector: 'div[role="dialog"] button:has-text("Submit")', fallbackSelector: 'div[role="dialog"] button', cardPos: { top: 420, right: 60, width: 420 } }
    ]
  }
];

async function run() {
  const args = process.argv.slice(2);
  const chapterArg = args.find(a => a.startsWith('--chapter='));
  const targetChapter = chapterArg ? parseInt(chapterArg.split('=')[1]) : null;

  console.log(`Starting automated screenshot generation for User Manual...`);
  console.log(`Target URL: ${BASE_URL}`);
  console.log(`Output Directory: ${OUTPUT_DIR}\n`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1
  });
  const page = await context.newPage();

  let activeRole = null;

  async function executeTasks(taskList) {
    for (const task of taskList) {
      console.log(`Processing: ${task.filename}...`);

      if (task.role && task.role !== activeRole) {
        console.log(` -> Logging in as ${task.role}...`);
        if (task.role === 'member') {
          await loginAsRole(page, 'leo.baluyot039+arjay.ramos@gmail.com');
        } else if (task.role === 'admin') {
          await loginAsRole(page, 'leo.baluyot039+admin@gmail.com');
        } else if (task.role === 'chairman') {
          await loginAsRole(page, 'leo.baluyot039+chairman@gmail.com');
        } else if (task.role === 'manager') {
          await loginAsRole(page, 'leo.baluyot039+manager@gmail.com');
        } else if (task.role === 'accountant') {
          await loginAsRole(page, 'leo.baluyot039+accountant@gmail.com');
        } else if (task.role === 'loan') {
          await loginAsRole(page, 'leo.baluyot039+loan@gmail.com');
        }
        activeRole = task.role;
      }

      await page.goto(task.url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1200);

      if (task.beforeAction) {
        await task.beforeAction(page);
      }

      await injectDriverAndStyles(page);

      if (task.spotlight) {
        await applySpotlight(page, task.spotlight);
      } else if (task.callouts) {
        await applyMultiCallout(page, task.callouts);
      }

      const outputPath = path.join(OUTPUT_DIR, task.filename);
      await page.screenshot({ path: outputPath, type: 'png' });
      console.log(` -> Saved: ${outputPath}`);
    }
  }

  if (!targetChapter || targetChapter === 2) {
    console.log(`--- RUNNING CHAPTER 2 TASKS ---`);
    await executeTasks(CHAPTER_2_TASKS);
  }

  if (!targetChapter || targetChapter === 3) {
    console.log(`\n--- RUNNING CHAPTER 3 TASKS ---`);
    await executeTasks(CHAPTER_3_TASKS);
  }

  if (!targetChapter || targetChapter === 5) {
    console.log(`\n--- RUNNING CHAPTER 5 (ACCOUNTANT) TASKS ---`);
    await executeTasks(CHAPTER_5_TASKS);
  }

  if (!targetChapter || targetChapter === 6) {
    console.log(`\n--- RUNNING CHAPTER 6 (MANAGER) TASKS ---`);
    await executeTasks(CHAPTER_6_TASKS);
  }

  if (!targetChapter || targetChapter === 7) {
    console.log(`\n--- RUNNING CHAPTER 7 (CHAIRMAN) TASKS ---`);
    await executeTasks(CHAPTER_7_TASKS);
  }

  if (!targetChapter || targetChapter === 8) {
    console.log(`\n--- RUNNING CHAPTER 8 (ADMIN) TASKS ---`);
    await executeTasks(CHAPTER_8_TASKS);
  }

  await browser.close();
  console.log(`\nExecution finished successfully!`);
}

run().catch(err => {
  console.error('Error running automated screenshots:', err);
  process.exit(1);
});
