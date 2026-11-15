/**
 * Generator for Guest Portal (Chapter 2) Manual Figures
 * Produces side-by-side implementations for user review:
 * - Option A: Clean UI with precision numbered badges & bounding boxes + Document Legend
 * - Option B: Canvas extension with margin callout panel & SVG leader lines (Annotations outside UI)
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';
const BASE_OUT_DIR = path.resolve(__dirname, '../docs/system details/capstone/User and Secuiryt Manual/screenshots/comparison_guest');
const DIR_OPTION_A = path.join(BASE_OUT_DIR, 'option_a');
const DIR_OPTION_B = path.join(BASE_OUT_DIR, 'option_b');

[DIR_OPTION_A, DIR_OPTION_B].forEach(d => {
  if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });
});

const GUEST_TASKS = [
  {
    filename: 'fig01_public_landing_page.png',
    title: 'Public Landing Page — Hero Section & Value Proposition',
    url: `${BASE_URL}/`,
    callouts: [
      { num: 1, title: 'Logo & Institutional Brand', desc: 'Identifies cooperative emblem; clicking returns to landing hero.', selector: 'nav a[href="/"]' },
      { num: 2, title: 'Public Navigation Menu', desc: 'Direct links to About Us, News & Bulletins, and Contact portals.', selector: 'nav a[href="/about"]' },
      { num: 3, title: 'Sign-in Portal Button', desc: 'Direct entrance to multi-factor authenticated member and officer sign-in.', selector: 'a[href="/login"]' },
      { num: 4, title: 'Explore Membership CTA', desc: 'Smoothly scrolls to financial products and membership qualifications.', selector: 'a[href="#about"]' },
      { num: 5, title: 'Branch Office Inquiry CTA', desc: 'Directs visitor to branch location, hours, and physical onboarding directory.', selector: 'a[href="/contact"]' }
    ]
  },
  {
    filename: 'fig02_public_landing_services.png',
    title: 'Public Landing Page — Cooperative Financial Services & Features',
    url: `${BASE_URL}/#about`,
    beforeAction: async (page) => {
      await page.evaluate(() => {
        const el = document.getElementById('about');
        if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
      });
      await page.waitForTimeout(600);
    },
    callouts: [
      { num: 1, title: 'Core Institutional Purpose', desc: 'Foundational principles of member progress, self-help, and community empowerment.', selector: '#about h2' },
      { num: 2, title: 'Operational Performance Metrics', desc: 'Active member count, asset valuation, years of service, and operations completed.', selector: '#about .grid' },
      { num: 3, title: 'Learn More About Us Button', desc: 'Direct link to full institutional history and leadership profile.', selector: '#about a[href="/about"]' }
    ]
  },
  {
    filename: 'fig03_public_about_us.png',
    title: 'Public About Us Portal — Institutional History, Mission, and Vision',
    url: `${BASE_URL}/about`,
    callouts: [
      { num: 1, title: 'About Us Overview', desc: 'Institutional profile and community-based organizational background.', selector: 'h1.text-4xl, h1.md\\:text-6xl', textMatch: 'About Us' },
      { num: 2, title: 'Our History Narrative', desc: 'Founding roots in the Calauan Housing Project and Southville 7 resettlement community.', selector: 'h2', textMatch: 'Our History' },
      { num: 3, title: 'Community History Photo', desc: 'Historical imagery documenting community leaders and cooperative founding.', selector: 'img.rounded-2xl, div.relative img' }
    ]
  },
  {
    filename: 'fig04_public_news.png',
    title: 'Public News Portal — Cooperative Bulletins & Community Updates',
    url: `${BASE_URL}/news`,
    callouts: [
      { num: 1, title: 'News & Events Header', desc: 'Centralized public communication channel for cooperative announcements.', textMatch: 'News & Events' },
      { num: 2, title: 'Latest Announcements', desc: 'Official bulletins regarding general assemblies, dividends, and loan guidelines.', textMatch: 'Latest News & Announcements' },
      { num: 3, title: 'Featured Community Event', desc: 'Sample announcement card displaying topic summary, timestamp, and read more details.', textMatch: 'Financial Literacy Seminar' }
    ]
  },
  {
    filename: 'fig06_public_contact.png',
    title: 'Public Contact Portal — Office Location, Map, and Inquiry Form',
    url: `${BASE_URL}/contact`,
    callouts: [
      { num: 1, title: 'Get In Touch Header', desc: 'Overview of visitor communication options and branch assistance channels.', textMatch: 'Get In Touch' },
      { num: 2, title: 'Inquiry Message Form', desc: 'Allows prospective members and partners to submit inquiries directly to administration.', textMatch: 'Send us a Message' },
      { num: 3, title: 'Contact Details & Office Hours', desc: 'Physical office address in Brgy. Sto. Tomas, Calauan, phone number, and official email.', textMatch: 'Contact Information' },
      { num: 4, title: 'Send Message Action', desc: 'Transmits inquiry to staff inbox with automated validation checks.', selector: 'form button[type="submit"]' }
    ]
  },
  {
    filename: 'fig07_public_login.png',
    title: 'Member Authentication Portal — Secure Cookie Session Login',
    url: `${BASE_URL}/login`,
    callouts: [
      { num: 1, title: 'Email Address Field', desc: 'Registered email address of member, officer, or administrator.', selector: 'input#email' },
      { num: 2, title: 'Password Field', desc: 'Account security credential (minimum 8 characters, rate-limited against brute-force).', selector: 'input#password' },
      { num: 3, title: 'Remember Browser Checkbox', desc: 'Maintains encrypted session across browser restarts on trusted private devices.', selector: 'input[name="remember"]' },
      { num: 4, title: 'Sign In Action', desc: 'Authenticates credentials and initiates multi-factor step-up OTP if untrusted.', selector: 'button[type="submit"]' },
      { num: 5, title: 'Password Recovery Link', desc: 'Direct link to initiate self-service password reset via signed email verification.', selector: 'a[href="/forgot-password"]' }
    ]
  },
  {
    filename: 'fig08_public_forgot_password.png',
    title: 'Password Recovery Portal — Email Verification & Reset Request',
    url: `${BASE_URL}/forgot-password`,
    callouts: [
      { num: 1, title: 'Recovery Title & Guidelines', desc: 'Instructions for resetting lost password via verified registered email.', selector: 'h1' },
      { num: 2, title: 'Registered Email Input', desc: 'Member or officer registered email address receiving the signed reset link.', selector: 'input#email' },
      { num: 3, title: 'Send Reset Link Button', desc: 'Dispatches time-limited cryptographic reset token to registered inbox.', selector: 'button[type="submit"]' },
      { num: 4, title: 'Back to Sign In Link', desc: 'Returns user to standard login portal if password is recalled.', selector: 'a[href="/login"]' }
    ]
  }
];

// Injects Finder Helper in browser context
const FINDER_SCRIPT = `
  window.findTargetElement = function(c) {
    // 1. Selector check
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

    // 2. Specific text search on leaf/heading/button/input elements
    if (c.textMatch) {
      const candidates = Array.from(document.querySelectorAll('h1, h2, h3, h4, a, button, p, span, label, th, td'));
      const matches = candidates.filter(el => {
        const text = (el.innerText || '').trim().toLowerCase();
        return text.includes(c.textMatch.toLowerCase());
      });
      if (matches.length > 0) {
        // Sort by area ascending so we get the most specific element
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

/**
 * Option A: Clean UI with precision numbered badges and bounding boxes.
 * No floating cards covering the interface. Clean, uncluttered, publication-grade.
 */
async function renderOptionA(page, task) {
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
        console.warn('Option A: Element not found for callout:', c.num, c.title);
        return;
      }

      const rect = el.getBoundingClientRect();
      const padding = 6;

      // 1. Highlight bounding box
      const box = document.createElement('div');
      box.style.position = 'absolute';
      box.style.top = `${rect.top - padding}px`;
      box.style.left = `${rect.left - padding}px`;
      box.style.width = `${rect.width + padding * 2}px`;
      box.style.height = `${rect.height + padding * 2}px`;
      box.style.border = '3px solid #0284c7'; // Sky-600
      box.style.borderRadius = '10px';
      box.style.backgroundColor = 'rgba(2, 132, 199, 0.08)';
      // 2px white halo ensures visibility on both black and white backgrounds
      box.style.boxShadow = '0 0 0 2px #ffffff, 0 8px 24px rgba(2, 132, 199, 0.35)';
      box.style.pointerEvents = 'none';

      // 2. High-contrast circular number badge
      const badge = document.createElement('div');
      badge.innerText = c.num;
      badge.style.position = 'absolute';
      
      // Determine badge placement (default top-left, flip if too close to viewport edge)
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

/**
 * Option B: Extended Canvas with Margin Callout Panel & SVG Leader Lines
 * Webpage is constrained to 1440px on the left; annotations live in a dedicated
 * 480px right-hand editorial panel with clean SVG connectors.
 */
async function renderOptionB(page, task) {
  const callouts = task.callouts;
  await page.evaluate((callouts) => {
    document.querySelectorAll('.manual-overlay-b, .manual-sidebar-b').forEach(e => e.remove());

    // Constrain app layout to 1440px width
    const appEl = document.getElementById('app') || document.body;
    appEl.style.width = '1440px';
    appEl.style.maxWidth = '1440px';
    appEl.style.margin = '0';
    appEl.style.position = 'relative';

    // 1. Sidebar Container (480px width on right)
    const sidebar = document.createElement('div');
    sidebar.className = 'manual-sidebar-b';
    sidebar.style.position = 'fixed';
    sidebar.style.top = '0';
    sidebar.style.right = '0';
    sidebar.style.width = '480px';
    sidebar.style.height = '100vh';
    sidebar.style.backgroundColor = '#F8FAFC'; // Slate 50
    sidebar.style.borderLeft = '2px solid #CBD5E1';
    sidebar.style.boxShadow = '-10px 0 30px rgba(0, 0, 0, 0.08)';
    sidebar.style.zIndex = '999998';
    sidebar.style.display = 'flex';
    sidebar.style.flexDirection = 'column';
    sidebar.style.fontFamily = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    sidebar.style.boxSizing = 'border-box';
    sidebar.style.overflow = 'hidden';

    // Header
    const header = document.createElement('div');
    header.style.padding = '18px 24px';
    header.style.backgroundColor = '#FFFFFF';
    header.style.borderBottom = '2px solid #E2E8F0';
    header.innerHTML = `
      <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #0284C7; margin-bottom: 4px;">USER MANUAL • CHAPTER 2</div>
      <div style="font-size: 19px; font-weight: 800; color: #0F172A; letter-spacing: -0.02em;">Key Interface Controls</div>
    `;
    sidebar.appendChild(header);

    // Cards Container
    const cardsList = document.createElement('div');
    cardsList.style.padding = '16px 20px';
    cardsList.style.display = 'flex';
    cardsList.style.flexDirection = 'column';
    cardsList.style.gap = '12px';
    cardsList.style.overflowY = 'auto';
    cardsList.style.flex = '1';
    sidebar.appendChild(cardsList);

    document.body.appendChild(sidebar);

    // 2. SVG Overlay for Leader Lines & Highlights
    const overlay = document.createElement('div');
    overlay.className = 'manual-overlay-b';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '1920px';
    overlay.style.height = '100vh';
    overlay.style.pointerEvents = 'none';
    overlay.style.zIndex = '999999';

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '1920');
    svg.setAttribute('height', '1080');
    svg.style.position = 'absolute';
    svg.style.top = '0';
    svg.style.left = '0';
    svg.style.width = '100%';
    svg.style.height = '100%';
    overlay.appendChild(svg);
    document.body.appendChild(overlay);

    // Render cards and connect leader lines
    callouts.forEach(c => {
      const el = window.findTargetElement(c);
      if (!el) {
        console.warn('Option B: Element not found for callout:', c.num, c.title);
        return;
      }

      const rect = el.getBoundingClientRect();
      const padding = 5;

      // Highlight box on element
      const box = document.createElement('div');
      box.style.position = 'absolute';
      box.style.top = `${rect.top - padding}px`;
      box.style.left = `${rect.left - padding}px`;
      box.style.width = `${rect.width + padding * 2}px`;
      box.style.height = `${rect.height + padding * 2}px`;
      box.style.border = '3px solid #0284c7';
      box.style.borderRadius = '8px';
      box.style.backgroundColor = 'rgba(2, 132, 199, 0.08)';
      box.style.boxShadow = '0 0 0 2px #ffffff, 0 6px 18px rgba(2, 132, 199, 0.35)';

      const badge = document.createElement('div');
      badge.innerText = c.num;
      badge.style.position = 'absolute';
      badge.style.top = '-14px';
      badge.style.left = '-14px';
      badge.style.width = '30px';
      badge.style.height = '30px';
      badge.style.borderRadius = '50%';
      badge.style.backgroundColor = '#0284c7';
      badge.style.color = '#ffffff';
      badge.style.display = 'flex';
      badge.style.alignItems = 'center';
      badge.style.justifyContent = 'center';
      badge.style.fontSize = '16px';
      badge.style.fontWeight = '900';
      badge.style.border = '2.5px solid #ffffff';
      badge.style.boxShadow = '0 3px 8px rgba(0, 0, 0, 0.35)';
      box.appendChild(badge);
      overlay.appendChild(box);

      // Create card in sidebar
      const card = document.createElement('div');
      card.id = `sidebar-card-${c.num}`;
      card.style.backgroundColor = '#FFFFFF';
      card.style.border = '1.5px solid #E2E8F0';
      card.style.borderRadius = '12px';
      card.style.padding = '12px 14px';
      card.style.boxShadow = '0 2px 6px rgba(0, 0, 0, 0.04)';
      card.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
          <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background-color: #0284c7; color: white; border-radius: 50%; font-size: 14px; font-weight: 900; flex-shrink: 0;">${c.num}</span>
          <span style="font-weight: 800; font-size: 15px; color: #0F172A; line-height: 1.2;">${c.title}</span>
        </div>
        <div style="font-size: 13px; font-weight: 500; color: #475569; line-height: 1.45; margin-left: 36px;">${c.desc}</div>
      `;
      cardsList.appendChild(card);
    });

    // Draw SVG Connector Lines after cards are laid out
    setTimeout(() => {
      callouts.forEach(c => {
        const el = window.findTargetElement(c);
        const card = document.getElementById(`sidebar-card-${c.num}`);
        if (!el || !card) return;

        const elRect = el.getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();

        // Start anchor: right-edge middle of element box
        const x1 = Math.min(elRect.right + 6, 1430);
        const y1 = elRect.top + elRect.height / 2;

        // End anchor: left-edge middle of card
        const x2 = cardRect.left;
        const y2 = cardRect.top + cardRect.height / 2;

        const dx = Math.abs(x2 - x1) * 0.45;
        const d = `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;

        // White background halo path for contrast
        const haloEl = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        haloEl.setAttribute('d', d);
        haloEl.setAttribute('fill', 'none');
        haloEl.setAttribute('stroke', '#ffffff');
        haloEl.setAttribute('stroke-width', '5');
        haloEl.setAttribute('stroke-linecap', 'round');
        svg.appendChild(haloEl);

        // Core line
        const pathEl = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        pathEl.setAttribute('d', d);
        pathEl.setAttribute('fill', 'none');
        pathEl.setAttribute('stroke', '#0284c7');
        pathEl.setAttribute('stroke-width', '2.5');
        pathEl.setAttribute('stroke-dasharray', '5,4');
        pathEl.setAttribute('stroke-linecap', 'round');
        svg.appendChild(pathEl);

        // Start anchor dot
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', x1);
        circle.setAttribute('cy', y1);
        circle.setAttribute('r', '5');
        circle.setAttribute('fill', '#0284c7');
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '2');
        svg.appendChild(circle);

        // End anchor dot
        const endDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        endDot.setAttribute('cx', x2);
        endDot.setAttribute('cy', y2);
        endDot.setAttribute('r', '5');
        endDot.setAttribute('fill', '#0284c7');
        endDot.setAttribute('stroke', '#ffffff');
        endDot.setAttribute('stroke-width', '2');
        svg.appendChild(endDot);
      });
    }, 100);

  }, callouts);

  await page.waitForTimeout(400);
}

async function run() {
  console.log('===============================================================');
  console.log('GUEST PORTAL (CHAPTER 2) COMPARISON: OPTION A vs OPTION B');
  console.log('===============================================================');
  console.log(`Target URL: ${BASE_URL}`);
  console.log(`Option A Output: ${DIR_OPTION_A}`);
  console.log(`Option B Output: ${DIR_OPTION_B}\n`);

  const browser = await chromium.launch({ headless: true });

  // 1. RUN OPTION A (Clean UI with badges + Word legend)
  console.log('--- GENERATING OPTION A (Numbered Badges, Uncovered UI) ---');
  const contextA = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1.5
  });
  const pageA = await contextA.newPage();

  for (const task of GUEST_TASKS) {
    console.log(`[Option A] Processing ${task.filename}...`);
    await pageA.goto(task.url, { waitUntil: 'domcontentloaded' });
    await pageA.waitForTimeout(1000);

    if (task.beforeAction) {
      await task.beforeAction(pageA);
    }

    await pageA.evaluate(FINDER_SCRIPT);
    await renderOptionA(pageA, task);

    const outPath = path.join(DIR_OPTION_A, task.filename);
    await pageA.screenshot({ path: outPath, type: 'png' });
    console.log(` -> Saved: ${outPath}`);
  }
  await contextA.close();

  // 2. RUN OPTION B (Extended Canvas with Margin Panel + Leader Lines)
  console.log('\n--- GENERATING OPTION B (Margin Callout Panel + SVG Leader Lines) ---');
  const contextB = await browser.newContext({
    viewport: { width: 1920, height: 960 },
    deviceScaleFactor: 1.5
  });
  const pageB = await contextB.newPage();

  for (const task of GUEST_TASKS) {
    console.log(`[Option B] Processing ${task.filename}...`);
    await pageB.goto(task.url, { waitUntil: 'domcontentloaded' });
    await pageB.waitForTimeout(1000);

    if (task.beforeAction) {
      await task.beforeAction(pageB);
    }

    await pageB.evaluate(FINDER_SCRIPT);
    await renderOptionB(pageB, task);

    const outPath = path.join(DIR_OPTION_B, task.filename);
    await pageB.screenshot({ path: outPath, type: 'png' });
    console.log(` -> Saved: ${outPath}`);
  }
  await contextB.close();

  await browser.close();
  console.log('\nAll comparisons generated successfully!');
}

run().catch(err => {
  console.error('Fatal error during comparison generation:', err);
  process.exit(1);
});
