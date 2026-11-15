/**
 * Task-Focused Option A Generator for Guest Portal (Chapter 2)
 * Only highlights the relevant interactive controls for the specific task of each figure.
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT_DIR = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots/comparison_guest/option_a_focused');

if (!fs.existsSync(OUT_DIR)) {
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

const FOCUSED_GUEST_TASKS = [
  {
    // Task: Navigating to Sign In from Landing Page
    filename: 'fig01_public_landing_page.png',
    title: 'Public Landing Page — Accessing the Sign-In Portal',
    url: `${BASE_URL}/`,
    callouts: [
      {
        num: 1,
        title: 'Sign In Button',
        desc: 'Direct link in the top navigation to open the credentials sign-in portal.',
        selector: 'a[href="/login"]'
      }
    ]
  },
  {
    // Task: Exploring Financial Services & Performance Metrics
    filename: 'fig02_public_landing_services.png',
    title: 'Public Landing Page — Cooperative Performance Metrics & About Link',
    url: `${BASE_URL}/#about`,
    beforeAction: async (page) => {
      await page.evaluate(() => {
        const el = document.getElementById('about');
        if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
      });
      await page.waitForTimeout(600);
    },
    callouts: [
      {
        num: 1,
        title: 'Operational Performance Metrics',
        desc: 'Displays active member count, total asset valuation, and years of community service.',
        selector: '#about .grid'
      },
      {
        num: 2,
        title: 'Learn More About Us Button',
        desc: 'Navigates to the full institutional history, mission, and leadership page.',
        selector: '#about a[href="/about"]'
      }
    ]
  },
  {
    // Task: Reading Institutional History
    filename: 'fig03_public_about_us.png',
    title: 'Public About Us Portal — Historical Overview & Contact Action',
    url: `${BASE_URL}/about`,
    callouts: [
      {
        num: 1,
        title: 'Our History Section',
        desc: 'Chronicles the founding of the cooperative in the Southville 7 resettlement area.',
        selector: 'h2',
        textMatch: 'Our History'
      },
      {
        num: 2,
        title: 'Contact Consultation Link',
        desc: 'Directs the visitor to the contact page to speak with branch staff.',
        textMatch: 'Contact Us',
        selector: 'a[href*="Contact"]'
      }
    ]
  },
  {
    // Task: Accessing News and Bulletins
    filename: 'fig04_public_news.png',
    title: 'Public News Portal — Reading Circulars and Event Bulletins',
    url: `${BASE_URL}/news`,
    callouts: [
      {
        num: 1,
        title: 'Featured Announcement Card',
        desc: 'Clickable circular detailing general assembly schedules, dividends, or educational seminars.',
        textMatch: 'Financial Literacy Seminar'
      }
    ]
  },
  {
    // Task: Submitting an Inquiry Message
    filename: 'fig06_public_contact.png',
    title: 'Public Contact Portal — Submitting an Inquiry Form',
    url: `${BASE_URL}/contact`,
    callouts: [
      {
        num: 1,
        title: 'Sender Name & Email Fields',
        desc: 'Input fields for visitor legal name and valid email address for administrative response.',
        selector: 'input#name'
      },
      {
        num: 2,
        title: 'Inquiry Message Area',
        desc: 'Textarea for specifying membership inquiries, loan queries, or partnership questions.',
        selector: 'textarea#message'
      },
      {
        num: 3,
        title: 'Send Message Button',
        desc: 'Transmits inquiry message directly to cooperative management.',
        selector: 'form button[type="submit"]'
      }
    ]
  },
  {
    // Task: Logging In (Standard Authentication)
    filename: 'fig07_public_login.png',
    title: 'Member Authentication Portal — Standard Sign-In',
    url: `${BASE_URL}/login`,
    callouts: [
      {
        num: 1,
        title: 'Email Address Field',
        desc: 'Enter registered email address (e.g., member@domain.com).',
        selector: 'input#email'
      },
      {
        num: 2,
        title: 'Password Field',
        desc: 'Enter secure password associated with the account.',
        selector: 'input#password'
      },
      {
        num: 3,
        title: 'Sign In Action',
        desc: 'Submit credentials to initiate encrypted cookie session and multi-factor verification.',
        selector: 'button[type="submit"]'
      }
    ]
  },
  {
    // Task: Forgotten Password — Initiating Reset from Login Screen
    filename: 'fig07b_public_login_forgot_password.png',
    title: 'Member Authentication Portal — Initiating Password Recovery',
    url: `${BASE_URL}/login`,
    callouts: [
      {
        num: 1,
        title: 'Forgot Password Link',
        desc: 'Click to navigate to the self-service password recovery portal.',
        selector: 'a[href="/forgot-password"]'
      }
    ]
  },
  {
    // Task: Submitting Password Recovery Request
    filename: 'fig08_public_forgot_password.png',
    title: 'Password Recovery Portal — Submitting Reset Request',
    url: `${BASE_URL}/forgot-password`,
    callouts: [
      {
        num: 1,
        title: 'Registered Email Input',
        desc: 'Enter the registered email address to receive the cryptographic reset link.',
        selector: 'input#email'
      },
      {
        num: 2,
        title: 'Send Reset Link Button',
        desc: 'Dispatches a signed, time-limited password reset link to the email inbox.',
        selector: 'button[type="submit"]'
      }
    ]
  }
];

const FINDER_SCRIPT = `
  window.findTargetElement = function(c) {
    if (c.selector) {
      const parts = c.selector.split(',').map(s => s.trim());
      for (const sel of parts) {
        try {
          const el = document.querySelector(sel);
          if (el) {
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) return el;
          }
        } catch (e) {}
      }
    }

    if (c.textMatch) {
      const candidates = Array.from(document.querySelectorAll('h1, h2, h3, h4, a, button, p, span, label, th, td'));
      const matches = candidates.filter(el => {
        const text = (el.innerText || '').trim().toLowerCase();
        return text.includes(c.textMatch.toLowerCase());
      });
      if (matches.length > 0) {
        matches.sort((a, b) => {
          const ra = a.getBoundingClientRect();
          const rb = b.getBoundingClientRect();
          return (ra.width * ra.height) - (rb.width * rb.height);
        });
        for (const m of matches) {
          const r = m.getBoundingClientRect();
          if (r.width > 0 && r.height > 0) return m;
        }
      }
    }

    return null;
  };
`;

async function renderOptionAFocused(page, task) {
  const callouts = task.callouts;
  await page.evaluate((callouts) => {
    document.querySelectorAll('.manual-overlay-a').forEach(e => e.remove());

    const overlay = document.createElement('div');
    overlay.className = 'manual-overlay-a';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.pointerEvents = 'none';
    overlay.style.zIndex = '999999';
    document.body.appendChild(overlay);

    callouts.forEach(c => {
      const el = window.findTargetElement(c);
      if (!el) {
        console.warn('Element not found for callout:', c.num, c.title);
        return;
      }

      const rect = el.getBoundingClientRect();
      const padding = 6;

      // Precision bounding box
      const box = document.createElement('div');
      box.style.position = 'absolute';
      box.style.top = `${rect.top - padding}px`;
      box.style.left = `${rect.left - padding}px`;
      box.style.width = `${rect.width + padding * 2}px`;
      box.style.height = `${rect.height + padding * 2}px`;
      box.style.border = '3px solid #0284c7';
      box.style.borderRadius = '10px';
      box.style.backgroundColor = 'rgba(2, 132, 199, 0.08)';
      box.style.boxShadow = '0 0 0 2px #ffffff, 0 8px 24px rgba(2, 132, 199, 0.35)';
      box.style.pointerEvents = 'none';

      // Number badge
      const badge = document.createElement('div');
      badge.innerText = c.num;
      badge.style.position = 'absolute';

      if (rect.top - padding < 24) {
        badge.style.top = '6px';
      } else {
        badge.style.top = '-16px';
      }

      if (rect.left - padding < 24) {
        badge.style.left = '6px';
      } else {
        badge.style.left = '-16px';
      }

      badge.style.width = '34px';
      badge.style.height = '34px';
      badge.style.borderRadius = '50%';
      badge.style.backgroundColor = '#0284c7';
      badge.style.color = '#ffffff';
      badge.style.display = 'flex';
      badge.style.alignItems = 'center';
      badge.style.justifyContent = 'center';
      badge.style.fontSize = '18px';
      badge.style.fontWeight = '900';
      badge.style.fontFamily = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
      badge.style.border = '3px solid #ffffff';
      badge.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.35)';

      box.appendChild(badge);
      overlay.appendChild(box);
    });
  }, callouts);

  await page.waitForTimeout(300);
}

async function run() {
  console.log('Generating Task-Focused Option A Screenshots for Guest Portal...');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1.5
  });
  const page = await context.newPage();

  for (const task of FOCUSED_GUEST_TASKS) {
    console.log(`Processing: ${task.filename}...`);
    await page.goto(task.url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    if (task.beforeAction) {
      await task.beforeAction(page);
    }

    await page.evaluate(FINDER_SCRIPT);
    await renderOptionAFocused(page, task);

    const outPath = path.join(OUT_DIR, task.filename);
    await page.screenshot({ path: outPath, type: 'png' });
    console.log(` -> Saved: ${outPath}`);
  }

  await browser.close();
  console.log('\nTask-Focused Option A generated successfully!');
}

run().catch(err => {
  console.error('Error generating focused Option A:', err);
  process.exit(1);
});
