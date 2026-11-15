/**
 * Option 1 — Stage 1: Clean Playwright Screenshot + Bounding Box Manifest Exporter
 * Scope: Complete Login & Authentication Process
 *   1. fig01_public_landing_page.png (Landing Page -> Login button)
 *   2. fig07_public_login.png (Login Page -> Email, Password, Sign In)
 *   3. fig55_security_otp_challenge_page.png (MFA Device Verification -> 6-digit OTP, Verify, Resend)
 *   4. fig54_security_login_handoff_approval.png (Multi-Device Sign-In Approval -> Verify by email, Reset password, Return to login)
 *   5. fig07b_public_login_forgot_password.png (Login Page -> Forgot password? link)
 *   6. fig08_public_forgot_password.png (Forgot Password Page -> Registered Email, Send Reset Link)
 *
 * Includes strict selector guardrails so generic selectors can NEVER accidentally match
 * the top-bar Language Selector or wrong container.
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_BASE = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots/option1_login');
const RAW_DIR = path.join(OUT_BASE, 'raw');
const MANIFEST_DIR = path.join(OUT_BASE, 'manifests');
const RENDERED_DIR = path.join(OUT_BASE, 'rendered');

for (const dir of [RAW_DIR, MANIFEST_DIR, RENDERED_DIR]) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

const VIEWPORT = { width: 1440, height: 900 };
const SCALE = 1.5;

function setMockActiveSession(email) {
  try {
    execSync(`php artisan tinker --execute="App\\Models\\User::where('email', '${email}')->update(['current_session_id' => 'existing_workstation_session_01']);"`, { stdio: 'ignore' });
  } catch {}
}

function clearActiveSessions() {
  try {
    execSync(`php artisan tinker --execute="App\\Models\\User::query()->update(['current_session_id' => null]);"`, { stdio: 'ignore' });
  } catch {}
}

function enablePendingOtpOnLatestSession() {
  const phpCode = `
    $row = DB::table('sessions')->orderByDesc('last_activity')->first();
    if ($row) {
      $data = unserialize(base64_decode($row->payload));
      $data['security.pending_otp'] = true;
      $data['security.pending_otp_purpose'] = 'new_device_login';
      unset($data['security.pending_otp_debug_code']);
      DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(serialize($data))]);
    }
  `.replace(/\r?\n/g, ' ');
  execSync(`php artisan tinker --execute="${phpCode.replace(/"/g, '\\"')}"`, { stdio: 'ignore' });
}

/**
 * Waits until all target selectors stop moving across two consecutive checks (200ms apart)
 */
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

const LOGIN_FLOW_TASKS = [
  {
    id: 'step1_landing_login_btn',
    filename: 'fig01_public_landing_page.png',
    title: 'Step 1: Public Landing Page — Accessing the Login Portal',
    url: `${BASE_URL}/`,
    callouts: [
      {
        num: 1,
        title: 'Login Button',
        desc: 'Direct navigation button in the top header bar that opens the authentication portal (/login).',
        selector: 'header a[href="/login"], nav a[href="/login"], a[href="/login"]',
        allowHeaderMatch: true,
        badgePos: 'bottom',
        padding: 5
      }
    ]
  },
  {
    id: 'step2_standard_login',
    filename: 'fig07_public_login.png',
    title: 'Step 2: Authentication Portal — Entering Credentials & Signing In',
    url: `${BASE_URL}/login`,
    callouts: [
      {
        num: 1,
        title: 'Email or Mobile Number Field',
        desc: 'Input field for the member or officer registered email address or verified mobile number.',
        selector: '.public-card input#email',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Password Field',
        desc: 'Masked input field for the account password, equipped with a visibility toggle icon.',
        selector: '.public-card input#password',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Sign In Button',
        desc: 'Submits primary credentials to establish an encrypted session and initiate device verification.',
        selector: '.public-card button[type="submit"]',
        badgePos: 'left',
        padding: 5
      }
    ]
  },
  {
    id: 'step3_otp_verification',
    filename: 'fig55_security_otp_challenge_page.png',
    title: 'Step 3: Multi-Factor Authentication — 6-Digit Device Verification (OTP)',
    setup: async (page, context) => {
      clearActiveSessions();
      await context.clearCookies();
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(600);
      await page.fill('input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
      await page.fill('input#password', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(/\/dashboard|\/security\/verify-otp/, { timeout: 15000 });
      enablePendingOtpOnLatestSession();
      await page.goto(`${BASE_URL}/security/verify-otp`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(500);
      await page.evaluate(() => {
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
          document.activeElement.blur();
        }
      });
      await page.waitForTimeout(200);
    },
    callouts: [
      {
        num: 1,
        title: '6-Digit Verification Code Input',
        desc: 'Numeric input field for the 6-digit One-Time Password dispatched to the registered email or SMS.',
        selector: 'main.public-card input#otp',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Verify and Continue Button',
        desc: 'Submits the 6-digit OTP to satisfy the otp.verified gate and trust the browser endpoint.',
        selector: 'main.public-card form[action*="verify-otp"] button[type="submit"]',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Resend Code Control',
        desc: 'Allows requesting a fresh verification code if the initial token was delayed or expired.',
        selector: 'main.public-card form[action*="resend"] button[type="submit"]',
        badgePos: 'left',
        padding: 4
      }
    ]
  },
  {
    id: 'step4_login_handoff_approval',
    filename: 'fig54_security_login_handoff_approval.png',
    title: 'Step 4: Multi-Device Sign-In Approval Challenge (/login/approval)',
    setup: async (page, context) => {
      await context.clearCookies();
      setMockActiveSession('leo.baluyot039+arjay.ramos@gmail.com');
      // Ensure production button label ("Can’t access that device? Verify by email instead") is shown
      await page.route('**/api/login-handoffs/status', async (route) => {
        const response = await route.fetch();
        const json = await response.json();
        json.email_otp_bypassed = false;
        await route.fulfill({ response, json });
      });
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(600);
      await page.fill('input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
      await page.fill('input#password', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(/\/login\/approval/, { timeout: 15000 });
      await page.waitForTimeout(600);
    },
    callouts: [
      {
        num: 1,
        title: 'Verify by Email Fallback Button',
        desc: 'Switches to an out-of-band 6-digit email OTP challenge when the user cannot access their previously signed-in device.',
        selector: 'main section button[type="button"]',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Reset Password Link',
        desc: 'Immediate self-service password reset shortcut if the active session is unrecognized.',
        selector: 'main section a[href="/forgot-password"]',
        badgePos: 'left',
        padding: 2
      },
      {
        num: 3,
        title: 'Return to Login Link',
        desc: 'Cancels the pending sign-in handoff and returns to the primary sign-in screen.',
        selector: 'main section a[href="/login"]',
        badgePos: 'right',
        padding: 2
      }
    ]
  },
  {
    id: 'step5_login_forgot_password_link',
    filename: 'fig07b_public_login_forgot_password.png',
    title: 'Step 5: Initiating Password Recovery from the Login Screen',
    setup: async (page, context) => {
      clearActiveSessions();
      await context.clearCookies();
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
    },
    callouts: [
      {
        num: 1,
        title: 'Forgot Password Link',
        desc: 'Direct recovery link on the sign-in card that routes the user to the self-service password reset portal.',
        selector: '.public-card a[href="/forgot-password"]',
        badgePos: 'left',
        padding: 4
      }
    ]
  },
  {
    id: 'step6_forgot_password_page',
    filename: 'fig08_public_forgot_password.png',
    title: 'Step 6: Submitting a Password Reset Request',
    url: `${BASE_URL}/forgot-password`,
    callouts: [
      {
        num: 1,
        title: 'Registered Email Address Input',
        desc: 'Input field where the user enters their registered cooperative email address.',
        selector: '.public-card input#email',
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Send Reset Link Button',
        desc: 'Dispatches a time-limited, cryptographically signed password reset link to the user inbox.',
        selector: '.public-card button[type="submit"]',
        badgePos: 'left',
        padding: 5
      }
    ]
  },
  {
    id: 'demo_fig11a_deposit_modal_scoped',
    filename: 'fig11a_deposit_step1_details.png',
    title: 'Figure 11: Member Deposit Wizard (Step 1) — Modal-Scoped Option 1 Verification',
    setup: async (page, context) => {
      clearActiveSessions();
      await context.clearCookies();
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(600);
      await page.fill('input#email', 'leo.baluyot039+arjay.ramos@gmail.com');
      await page.fill('input#password', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(/\/dashboard/, { timeout: 15000 });
      await page.goto(`${BASE_URL}/dashboard/services?service=deposit`, { waitUntil: 'domcontentloaded' });
      await page.locator('div[role="dialog"] select').first().waitFor({ state: 'visible', timeout: 10000 });
      await page.waitForTimeout(600);
    },
    callouts: [
      {
        num: 1,
        title: 'Account Type Dropdown (Inside Modal)',
        desc: 'Selects whether the deposit credits the member Savings Account or Share Capital ledger.',
        selector: 'div[role="dialog"] select',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 2,
        title: 'Deposit Amount Input',
        desc: 'Numeric currency field for specifying the exact Philippine Peso (PHP) deposit amount.',
        selector: 'div[role="dialog"] input[type="number"]',
        requireModalScope: true,
        badgePos: 'left',
        padding: 5
      },
      {
        num: 3,
        title: 'Next Step Button',
        desc: 'Validates Step 1 inputs and advances the wizard to Step 2 (Payment Method selection).',
        selector: 'div[role="dialog"] button:has-text("Next")',
        requireModalScope: true,
        badgePos: 'right',
        padding: 5
      }
    ]
  }
];

async function run() {
  console.log('=== Stage 1: Clean Playwright Screenshot + Bounding Box Extractor ===');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: SCALE
  });

  // Block external Cloudflare Turnstile test widget script so it never causes layout shift or test-only red banners
  await context.route('**/*cloudflare.com/**', route => route.abort());

  const page = await context.newPage();

  for (const task of LOGIN_FLOW_TASKS) {
    console.log(`\nCapturing clean screenshot & geometry: ${task.filename}`);

    if (task.setup) {
      await task.setup(page, context);
    } else if (task.url) {
      await page.goto(task.url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
    }

    await waitForGeometryStability(page, task.callouts);

    // Extract bounding boxes using Playwright's native locator engine + strict scope guardrails
    const resolvedCallouts = [];
    for (const c of task.callouts) {
      const loc = page.locator(c.selector).first();
      const count = await loc.count();
      if (count === 0) {
        throw new Error(`[FATAL] Selector not found for [${c.num}] ${c.title}: ${c.selector}`);
      }

      // Guardrail: Ensure non-header callouts never accidentally match top navbar or language selector
      if (!c.allowHeaderMatch) {
        const isAccidentalHeaderMatch = await loc.evaluate((el) => {
          const inHeader = Boolean(el.closest('header'));
          const isLangSelect = el.tagName === 'SELECT' && Boolean(el.closest('header, nav'));
          return inHeader || isLangSelect;
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

    // Save 100% clean, unannotated screenshot
    const rawPath = path.join(RAW_DIR, task.filename);
    await page.screenshot({ path: rawPath, type: 'png' });

    // Save JSON manifest
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
  }

  clearActiveSessions();
  await browser.close();
  console.log('\nStage 1 complete!');
}

run().catch(err => {
  clearActiveSessions();
  console.error('Stage 1 failed:', err);
  process.exit(1);
});
