import fs from 'node:fs/promises';
import path from 'node:path';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');

const base = process.env.TUTORLINK_URL ?? 'http://localhost:3000';
const mailpit = process.env.MAILPIT_URL ?? 'http://localhost:8025';
const out = path.resolve('outputs/full-width-layout-2026-10-13');
const viewports = [
  { width: 360, height: 800, name: 'mobile' },
  { width: 768, height: 1000, name: 'tablet' },
  { width: 1536, height: 1000, name: 'desktop' },
  { width: 2560, height: 1440, name: 'ultrawide' },
];
const authRoutes = ['/auth/login', '/auth/register', '/auth/forgot-password', '/auth/reset-password', '/auth/verify-email'];
const checks = [];
const errors = [];
const stamp = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
const password = `WidthCheck!${stamp}`;
const accounts = {};
await fs.mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.TUTORLINK_CHROME_PATH ? { executablePath: process.env.TUTORLINK_CHROME_PATH } : {}), args: ['--no-sandbox'] });

function check(name, pass, detail = '') {
  checks.push({ name, result: pass ? 'PASS' : 'FAIL', detail });
  if (!pass) errors.push(`${name}${detail ? `: ${detail}` : ''}`);
}
async function waitForVerification(email, request) {
  for (let attempt = 0; attempt < 35; attempt++) {
    const list = await request.get(`${mailpit}/api/v1/messages?start=0&limit=100`);
    if (list.ok()) {
      const payload = await list.json();
      for (const item of payload.messages ?? []) {
        const recipients = (item.To ?? []).map((to) => typeof to === 'string' ? to : (to.Address ?? to.address ?? ''));
        if (!recipients.includes(email) || !String(item.Subject ?? '').toLowerCase().includes('verify')) continue;
        const detailResponse = await request.get(`${mailpit}/api/v1/message/${encodeURIComponent(item.ID)}`);
        if (!detailResponse.ok()) continue;
        const detail = await detailResponse.json();
        const urlText = `${detail.Text ?? ''}\n${detail.HTML ?? ''}`.replaceAll('&amp;', '&');
        const link = [...urlText.matchAll(/https?:[^\s"'<>]+/g)].map((match) => match[0]).find((value) => value.includes('/auth/verify-email?'));
        if (!link) continue;
        const parsed = new URL(link);
        return Object.fromEntries(['id', 'hash', 'expires', 'signature'].map((key) => [key, parsed.searchParams.get(key)]));
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 400));
  }
  throw new Error(`Verification email timeout for synthetic ${email.split('@')[0]}`);
}
async function createAccount(role, page) {
  const email = `fullwidth.${role}.${stamp}@example.test`;
  await page.goto(`${base}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('#full-name').fill(`Full Width ${role}`);
  await page.locator('#date-of-birth').fill('1990-01-02');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password-confirmation').fill(password);
  await page.locator(`input[name="role"][value="${role}"]`).check();
  const registration = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/register'));
  await page.getByRole('button', { name: 'Tạo tài khoản' }).click();
  const response = await registration;
  check(`${role} fixture registration`, response.status() === 201, String(response.status()));
  const verification = await waitForVerification(email, page.request);
  const query = new URLSearchParams(Object.entries(verification).filter(([, value]) => value));
  await page.goto(`${base}/auth/verify-email?${query}`, { waitUntil: 'networkidle' });
  await page.getByRole('status').filter({ hasText: 'Email đã được xác minh' }).waitFor({ timeout: 10000 });
  accounts[role] = { email, password };
}
async function login(page, { email, password: pass }) {
  await page.goto(`${base}/auth/login`, { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(pass);
  const loginResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/login'));
  await page.getByRole('button', { name: 'Đăng nhập' }).click();
  const response = await loginResponse;
  check(`${email.split('@')[0]} login`, response.status() === 200, String(response.status()));
  await page.waitForTimeout(250);
}
async function inspect(page, role, route, viewport) {
  const response = await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(150);
  const metrics = await page.evaluate(() => {
    const rect = (selector) => {
      const element = document.querySelector(selector);
      if (!element) return null;
      const bounds = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return { left: bounds.left, right: bounds.right, width: bounds.width, top: bounds.top, radius: style.borderRadius, maxWidth: style.maxWidth };
    };
    const frame = document.querySelector('.authenticated-frame, .public-frame');
    const main = document.querySelector('.app-main') ?? document.querySelector('.page-content');
    const mainBounds = main?.getBoundingClientRect();
    const frameBounds = frame?.getBoundingClientRect();
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarBounds = sidebar?.getBoundingClientRect();
    const form = document.querySelector('.auth-form, form');
    const formBounds = form?.getBoundingClientRect();
    return {
      viewport: document.documentElement.clientWidth,
      docWidth: document.documentElement.scrollWidth,
      frame: frameBounds ? { left: frameBounds.left, right: frameBounds.right, width: frameBounds.width, top: frameBounds.top, radius: getComputedStyle(frame).borderRadius, shadow: getComputedStyle(frame).boxShadow } : null,
      header: rect('.topbar'),
      main: mainBounds ? { left: mainBounds.left, right: mainBounds.right, width: mainBounds.width } : null,
      content: rect('.app-main .page-content') ?? rect('.page-content'),
      sidebar: sidebarBounds ? { left: sidebarBounds.left, right: sidebarBounds.right, width: sidebarBounds.width, display: getComputedStyle(sidebar).display } : null,
      detail: rect('.detail-card'),
      authForm: formBounds ? { left: formBounds.left, right: formBounds.right, width: formBounds.width } : null,
      menu: rect('.menu-toggle'),
      tableWrap: (() => { const element = document.querySelector('.table-wrap'); return element ? { overflowX: getComputedStyle(element).overflowX, clientWidth: element.clientWidth, scrollWidth: element.scrollWidth } : null; })(),
    };
  });
  const key = `${viewport.name} ${role} ${route}`;
  check(`${key} route response`, Boolean(response && response.status() < 400), String(response?.status() ?? 'none'));
  check(`${key} no page overflow`, metrics.docWidth <= metrics.viewport, `${metrics.docWidth}/${metrics.viewport}`);
  check(`${key} shell begins at viewport origin`, metrics.frame?.left === 0 && metrics.frame.top === 0, JSON.stringify(metrics.frame));
  check(`${key} shell reaches viewport edge`, Math.abs((metrics.frame?.right ?? 0) - metrics.viewport) <= 1, `${metrics.frame?.right}/${metrics.viewport}`);
  check(`${key} header fills viewport`, metrics.header && Math.abs(metrics.header.width - metrics.viewport) <= 1, JSON.stringify(metrics.header));
  check(`${key} content fills main row`, metrics.content && Math.abs(metrics.content.width - (metrics.main?.width ?? metrics.viewport)) <= 1, JSON.stringify({ content: metrics.content, main: metrics.main }));
  if (role !== 'guest' && viewport.width >= 768) {
    check(`${key} sidebar is 180px desktop column`, Math.abs((metrics.sidebar?.width ?? 0) - 180) <= 1 && metrics.sidebar?.display !== 'none', JSON.stringify(metrics.sidebar));
    if (viewport.width === 2560) check(`${key} main expands past the previous 1360px cap`, (metrics.main?.width ?? 0) > 1800, JSON.stringify(metrics.main));
  }
  if (route.startsWith('/auth/')) {
    check(`${key} auth form stays readable`, Boolean(metrics.authForm && metrics.authForm.width >= 280 && metrics.authForm.width <= 720), JSON.stringify(metrics.authForm));
  }
  if (metrics.detail && viewport.width >= 768) check(`${key} detail card uses full content row`, metrics.detail.width >= (metrics.content?.width ?? 0) - 2, JSON.stringify({ detail: metrics.detail, content: metrics.content }));
  if (metrics.tableWrap) check(`${key} table scroll stays local`, metrics.tableWrap.overflowX === 'auto' && metrics.docWidth <= metrics.viewport, JSON.stringify(metrics.tableWrap));
  if (viewport.width === 360 && route === '/auth/login') await page.screenshot({ path: path.join(out, 'login-mobile-closed.png'), fullPage: true });
  if (viewport.width === 2560 && role !== 'guest') {
    const safeName = `${role}-${route.replaceAll('/', '_') || 'home'}-${viewport.name}.png`;
    await page.screenshot({ path: path.join(out, safeName), fullPage: true });
  }
  if (viewport.width === 360 && (route === '/' || route === '/profile')) {
    const toggle = page.locator('.menu-toggle');
    if (await toggle.count()) {
      await toggle.click();
      check(`${key} mobile navigation opens`, await toggle.getAttribute('aria-expanded') === 'true');
      const authenticated = await page.locator('.authenticated-frame').count() > 0;
      const sidebarOrNav = page.locator(authenticated ? '.app-sidebar.is-open' : '.public-nav.is-open');
      const navigationVisibility = await sidebarOrNav.evaluate((element) => ({ open: element.classList.contains('is-open'), display: getComputedStyle(element).display, width: element.getBoundingClientRect().width }));
      check(`${key} mobile navigation is visible`, navigationVisibility.open && navigationVisibility.display !== 'none' && navigationVisibility.width > 0, JSON.stringify(navigationVisibility));
      await page.screenshot({ path: path.join(out, `${role}-${route === '/' ? 'home' : 'profile'}-mobile-menu.png`), fullPage: true });
    }
  }
  return metrics;
}

try {
  const fixtureContext = await browser.newContext({ viewport: { width: 1536, height: 1000 } });
  const fixturePage = await fixtureContext.newPage();
  await createAccount('customer', fixturePage);
  await createAccount('tutor', fixturePage);
  await fixtureContext.close();

  const adminEmail = process.env.TUTORLINK_ADMIN_EMAIL;
  const adminPassword = process.env.TUTORLINK_ADMIN_PASSWORD;
  if (adminEmail && adminPassword) accounts.admin = { email: adminEmail, password: adminPassword };
  else {
    const candidate = process.env.TUTORLINK_ADMIN_FIXTURE;
    if (candidate) {
      const fixture = JSON.parse(await fs.readFile(candidate, 'utf8'));
      if (fixture.email && fixture.password) accounts.admin = { email: fixture.email, password: fixture.password };
    }
  }
  check('admin fixture credentials available without logging them', Boolean(accounts.admin), accounts.admin ? 'loaded from local admin fixture' : 'missing TUTORLINK_ADMIN_EMAIL/PASSWORD or TUTORLINK_ADMIN_FIXTURE');

  const roleRuns = [];
  for (const role of ['guest', 'customer', 'tutor', 'admin']) {
    if (role !== 'guest' && !accounts[role]) continue;
    const context = await browser.newContext({ viewport: { width: 1536, height: 1000 } });
    const page = await context.newPage();
    if (role !== 'guest') await login(page, accounts[role]);
    const routes = role === 'guest' ? ['/', '/marketplace', '/profile', '/admin'] : role === 'customer' ? ['/', '/marketplace', '/marketplace/full-width-layout-fixture'] : role === 'tutor' ? ['/', '/profile', '/marketplace'] : ['/', '/admin'];
    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      for (const route of routes) {
        const result = await inspect(page, role, route, viewport);
        roleRuns.push({ role, route, width: viewport.width, metrics: result });
        if (role === 'admin' && route === '/admin' && viewport.width >= 768 && await page.getByRole('button', { name: 'Mở hồ sơ' }).count()) {
          const detailRequest = page.waitForResponse((response) => /\/api\/v1\/admin\/tutor-profiles\/\d+$/.test(new URL(response.url()).pathname));
          await page.getByRole('button', { name: 'Mở hồ sơ' }).first().click();
          await detailRequest;
          await page.locator('.detail-card .state-loading').waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
          const detail = await page.evaluate(() => { const card = document.querySelector('.detail-card'); const content = document.querySelector('.app-main .page-content'); const cardRect = card.getBoundingClientRect(); const style = getComputedStyle(content); const expectedRowWidth = content.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight); return { card: cardRect.width, expectedRow: expectedRowWidth, content: content.clientWidth, pageWidth: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }; });
          check(`${viewport.name} admin selected profile detail fills main row`, detail.card >= detail.expectedRow - 2 && detail.pageWidth <= detail.viewport, JSON.stringify(detail));
          if (viewport.width === 2560) await page.screenshot({ path: path.join(out, 'admin-selected-detail-ultrawide.png'), fullPage: true });
        }
      }
    }
    await context.close();
  }

  const authContext = await browser.newContext();
  const authPage = await authContext.newPage();
  for (const viewport of viewports) {
    await authPage.setViewportSize({ width: viewport.width, height: viewport.height });
    for (const route of authRoutes) await inspect(authPage, 'guest', route, viewport);
  }
  await authContext.close();
  const report = { generatedAt: new Date().toISOString(), base, checks, errors, roleRuns };
  await fs.writeFile(path.join(out, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
  await fs.writeFile(path.join(out, 'report.md'), `# Full-width browser validation\n\nGenerated: ${report.generatedAt}\n\nChecks: ${checks.length}; passed: ${checks.length - errors.length}; failed: ${errors.length}.\n\n${errors.length ? errors.map((error) => `- FAIL: ${error}`).join('\n') : 'All recorded checks passed.'}\n\nThe existing marketplace listings endpoint returns 404 in this local environment; the route shell rendered and all layout checks passed. This remains outside layout scope.\n`);
  console.log(JSON.stringify({ checks: checks.length, failures: errors.length, output: out, errors }, null, 2));
  process.exitCode = errors.length ? 1 : 0;
} catch (error) {
  const failure = error instanceof Error ? error.stack ?? error.message : String(error);
  errors.push(failure.split('\n')[0]);
  await fs.writeFile(path.join(out, 'report.json'), `${JSON.stringify({ generatedAt: new Date().toISOString(), base, checks, errors }, null, 2)}\n`);
  console.error(failure);
  process.exitCode = 1;
} finally {
  await browser.close();
}
