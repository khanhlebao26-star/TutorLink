import fs from 'node:fs/promises';
import path from 'node:path';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');

const base = process.env.TUTORLINK_URL ?? 'http://localhost:3000';
const mailpit = process.env.MAILPIT_URL ?? 'http://localhost:8025';
const out = path.resolve(process.env.ISSUE22_OUTPUT_DIR ?? 'outputs/issue-22-redesign-2026-10-13');
const onboardingOnly = process.argv.includes('--onboarding-only');
const authRoutes = [
  { key: 'login', route: '/auth/login', ref: 'Login page.png', refSize: [1536, 674], margins: [0, 0], viewport: [1536, 674], card: [550, 96, 435, 518], illustration: 'student-man', headerHeight: 76 },
  { key: 'register', route: '/auth/register', ref: 'Register page.png', refSize: [1521, 681], margins: [0, 0], viewport: [1521, 681], card: [533, 102, 455, 548], illustration: 'student-woman', headerHeight: 76 },
  { key: 'forgot', route: '/auth/forgot-password', ref: 'Forgot password page.png', refSize: [1536, 674], margins: [0, 0], viewport: [1536, 674], card: [918, 165, 440, 404], illustration: 'forgot', headerHeight: 76 },
  { key: 'reset', route: '/auth/reset-password?email=visual.reset%40example.test&token=visual-only', ref: 'Reset password.png', refSize: [1526, 708], margins: [21, 0], viewport: [1505, 708], card: [910, 122, 440, 522], illustration: 'reset', headerHeight: 76 },
  { key: 'verify', route: '/auth/verify-email', ref: 'Verify Password.png', refSize: [1557, 754], margins: [21, 40], viewport: [1536, 714], card: [918, 127, 440, 479], illustration: 'verification', headerHeight: 76 },
];
const steps = ['Thông tin cơ bản', 'Chuyên môn', 'Kinh nghiệm', 'Tài liệu xác minh', 'Xem lại'];
const references = [
  { file: 'Tutor Dashboard-thongtincoban.png', size: [2096, 1208], innerHeight: 1162 },
  { file: 'Tutor Dashboard-chuyenmon.png', size: [2096, 1239], innerHeight: 1193 },
  { file: 'Tutor Dashboard-kinhnghiem.png', size: [2096, 1239], innerHeight: 1193 },
  { file: 'Tutor Dashboard-tailieuxacminh.png', size: [2096, 1225], innerHeight: 1179 },
  { file: 'Tutor Dashboard-xemlai.png', size: [2096, 1225], innerHeight: 1179 },
];
const viewports = [
  { width: 2034, height: 1162, name: 'reference-shell' },
  { width: 360, height: 800, name: 'mobile' },
  { width: 768, height: 1000, name: 'tablet' },
  { width: 1536, height: 1000, name: 'desktop' },
  { width: 2560, height: 1440, name: 'ultrawide' },
];
const checks = [];
const errors = [];
const authRuns = [];
const onboardingRuns = [];
const roleRuns = [];
const limitations = [
  'Forgot and Reset use the documented approximate CSS illustrations because the exact source layers are unavailable.',
  'Reset and Verify reference PNGs include black export margins; their captures use the inner viewport dimensions from design-spec.md.',
  'Auth uses a shared 76px public header; the Register reference PNG is 84px tall, while the card retains its reference y-position.',
];
const stamp = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
const password = `Issue22Visual!${stamp}`;
await fs.mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.TUTORLINK_CHROME_PATH ? { executablePath: process.env.TUTORLINK_CHROME_PATH } : {}), args: ['--no-sandbox'] });

function check(name, pass, detail = '') {
  checks.push({ name, result: pass ? 'PASS' : 'FAIL', detail });
  if (!pass) errors.push(`${name}${detail ? `: ${detail}` : ''}`);
}

function pngSize(file) {
  const data = Buffer.from(file);
  return { width: data.readUInt32BE(16), height: data.readUInt32BE(20) };
}

function roundedBox(rect) {
  return rect && Object.fromEntries(Object.entries(rect).map(([key, value]) => [key, Math.round(value * 10) / 10]));
}

async function waitForVerification(email, request) {
  for (let attempt = 0; attempt < 35; attempt++) {
    const response = await request.get(`${mailpit}/api/v1/messages?start=0&limit=100`);
    if (response.ok()) {
      const payload = await response.json();
      for (const message of payload.messages ?? []) {
        const recipients = (message.To ?? message.to ?? []).map((to) => typeof to === 'string' ? to : (to.Address ?? to.address ?? ''));
        if (!recipients.includes(email) || !String(message.Subject ?? message.subject ?? '').toLowerCase().includes('verify')) continue;
        const id = message.ID ?? message.id;
        const detailResponse = await request.get(`${mailpit}/api/v1/message/${encodeURIComponent(id)}`);
        if (!detailResponse.ok()) continue;
        const detail = await detailResponse.json();
        const body = `${detail.Text ?? ''}\n${detail.HTML ?? ''}`.replaceAll('&amp;', '&');
        const link = [...body.matchAll(/https?:[^\s"'<>]+/g)].map((match) => match[0]).find((value) => value.includes('/auth/verify-email?'));
        if (!link) continue;
        const url = new URL(link);
        return Object.fromEntries(['id', 'hash', 'expires', 'signature'].map((key) => [key, url.searchParams.get(key)]));
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 400));
  }
  throw new Error(`Verification email timed out for synthetic fixture ${email.split('@')[0].split('.').at(-1)}`);
}

async function createVerifiedAccount(page, role, name) {
  const email = `issue22.visual.${role}.${stamp}@example.test`;
  await page.goto(`${base}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('#full-name').fill(name);
  await page.locator('#date-of-birth').fill('1990-01-02');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password-confirmation').fill(password);
  await page.locator(`input[name="role"][value="${role}"]`).check();
  const registration = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/register'));
  await page.getByRole('button', { name: 'Tạo tài khoản' }).click();
  const response = await registration;
  check(`${role} visual fixture registration`, response.status() === 201, String(response.status()));
  const verify = await waitForVerification(email, page.request);
  const query = new URLSearchParams(Object.entries(verify).filter(([, value]) => value));
  await page.goto(`${base}/auth/verify-email?${query}`, { waitUntil: 'networkidle' });
  await page.getByRole('status').filter({ hasText: 'Email đã được xác minh' }).waitFor({ timeout: 10000 });
  const verifyActions = page.locator('.auth-verify-actions');
  const verifyCta = verifyActions.getByRole('link', { name: 'Tiếp tục đăng nhập' });
  check(`${role} verified state shows exactly one login CTA and no resend action`, await verifyActions.getByRole('link').count() === 1 && await verifyCta.count() === 1 && await verifyActions.locator('button, form.auth-verify-resend').count() === 0, 'success state actions inspected without recording the account address');
  return email;
}

async function login(page, email, expectedPath) {
  await page.goto(`${base}/auth/login`, { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  const loginResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/login'));
  await page.getByRole('button', { name: 'Đăng nhập' }).click();
  const response = await loginResponse;
  check(`synthetic ${expectedPath} login`, response.status() === 200, String(response.status()));
  await page.waitForURL(new RegExp(`${expectedPath.replaceAll('/', '\\/')}(?:$|[?#])`), { timeout: 10000 });
}

async function captureAuth(page, entry) {
  await page.setViewportSize({ width: entry.viewport[0], height: entry.viewport[1] });
  const response = await page.goto(`${base}${entry.route}`, { waitUntil: 'networkidle' });
  await page.locator('.auth-card').waitFor({ state: 'visible', timeout: 10000 });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(120);
  const metrics = await page.evaluate(() => {
    const box = (selector) => {
      const element = document.querySelector(selector);
      if (!element) return null;
      const rect = element.getBoundingClientRect();
      return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
    };
    const visibleHeights = [...document.querySelectorAll('.auth-card input:not([type="hidden"]), .auth-card select, .auth-card textarea, .auth-card button')]
      .filter((element) => element.getClientRects().length)
      .map((element) => ({ tag: element.tagName.toLowerCase(), id: element.id || null, height: element.getBoundingClientRect().height }));
    const dateInput = document.querySelector('#date-of-birth');
    const dateWrapperStyle = dateInput?.parentElement?.matches('.auth-input-icon') ? getComputedStyle(dateInput.parentElement, '::before') : null;
    const calendarIndicator = dateInput ? getComputedStyle(dateInput, '::-webkit-calendar-picker-indicator') : null;
    const registerSlogan = document.querySelector('.auth-slogan-register');
    const registerHeart = registerSlogan ? getComputedStyle(registerSlogan, '::after') : null;
    const image = document.querySelector('.auth-illustration-image');
    const imageUrl = image?.currentSrc ? new URL(image.currentSrc) : null;
    return {
      viewport: { width: document.documentElement.clientWidth, height: window.innerHeight },
      documentWidth: document.documentElement.scrollWidth,
      frame: box('.public-frame'),
      header: box('.public-topbar'),
      scene: box('.auth-scene'),
      card: box('.auth-card'),
      submit: box('.auth-card button[type="submit"]'),
      roleOptionHeights: [...document.querySelectorAll('.auth-role-option')].map((element) => element.getBoundingClientRect().height),
      registerHeart: registerHeart ? { content: registerHeart.content, color: registerHeart.color, backgroundColor: registerHeart.backgroundColor, backgroundImage: registerHeart.backgroundImage, width: registerHeart.width, height: registerHeart.height } : null,
      birthDateControl: dateInput ? { type: dateInput.type, hasNativeCalendar: Boolean(calendarIndicator && calendarIndicator.display !== 'none' && calendarIndicator.opacity !== '0'), leadingIconImage: dateWrapperStyle?.backgroundImage ?? 'none' } : null,
      heading: box('.auth-card-heading h1'),
      firstControl: box('.auth-card input:not([type="hidden"]), .auth-card select'),
      illustration: box('.auth-illustration'),
      illustrationClass: document.querySelector('.auth-illustration')?.className ?? null,
      image: image ? { complete: image.complete, naturalWidth: image.naturalWidth, naturalHeight: image.naturalHeight, src: imageUrl?.searchParams.get('url') ?? imageUrl?.pathname } : null,
      sloganCount: document.querySelectorAll('.auth-slogan').length,
      controlHeights: visibleHeights,
    };
  });
  const filename = `redesign-auth-${entry.key}.png`;
  const bytes = await page.screenshot({ path: path.join(out, filename), animations: 'disabled' });
  const dimensions = pngSize(bytes);
  const [x, y, width, height] = entry.card;
  const intentionalHeightAdjustment = entry.key === 'register' ? 56 : 0;
  const intendedHeight = height + intentionalHeightAdjustment;
  const card = metrics.card ?? { x: NaN, y: NaN, width: NaN, height: NaN };
  const deltas = { x: Math.round(card.x - x), y: Math.round(card.y - y), width: Math.round(card.width - width), height: Math.round(card.height - height) };
  const adjustedHeightDelta = card.height - intendedHeight;
  const geometryPass = Math.abs(deltas.x) <= 28 && Math.abs(deltas.y) <= 28 && Math.abs(deltas.width) <= 20 && Math.abs(adjustedHeightDelta) <= (intentionalHeightAdjustment ? 16 : 45);
  check(`${entry.key} response`, Boolean(response && response.status() < 400), String(response?.status() ?? 'no response'));
  check(`${entry.key} exact viewport PNG dimensions`, dimensions.width === entry.viewport[0] && dimensions.height === entry.viewport[1], JSON.stringify({ expected: { width: entry.viewport[0], height: entry.viewport[1] }, actual: dimensions }));
  check(`${entry.key} public shell spans viewport`, metrics.frame && Math.abs(metrics.frame.width - entry.viewport[0]) <= 1 && metrics.frame.x === 0, JSON.stringify(metrics.frame));
  check(`${entry.key} header matches reference height`, Math.abs((metrics.header?.height ?? 0) - entry.headerHeight) <= 3, JSON.stringify(metrics.header));
  check(`${entry.key} no horizontal overflow`, metrics.documentWidth <= entry.viewport[0], `${metrics.documentWidth}/${entry.viewport[0]}`);
  check(`${entry.key} auth card near reference bounds`, geometryPass, JSON.stringify({ expectedReference: { x, y, width, height }, intentionalHeightAdjustment, expectedHeightAfterAdjustment: intendedHeight, actual: roundedBox(card), deltaFromReference: deltas, deltaFromIntendedHeight: Math.round(adjustedHeightDelta) }));
  if (entry.key === 'register') check('register submit CTA fits in exact reference viewport', Boolean(metrics.submit && metrics.submit.y + metrics.submit.height <= entry.viewport[1]), JSON.stringify({ submit: roundedBox(metrics.submit), viewportHeight: entry.viewport[1] }));
  if (entry.key === 'register') {
    check('register role options retain 44px geometry', metrics.roleOptionHeights.length === 2 && metrics.roleOptionHeights.every((height) => Math.abs(height - 44) <= 1), JSON.stringify(metrics.roleOptionHeights));
    check('register heart decoration is visible without a filled pill', Boolean(metrics.registerHeart?.content.includes('♡') && metrics.registerHeart.backgroundImage === 'none' && metrics.registerHeart.backgroundColor === 'rgba(0, 0, 0, 0)'), JSON.stringify(metrics.registerHeart));
    check('registration birth date uses the native calendar without a leading icon', Boolean(metrics.birthDateControl?.type === 'date' && metrics.birthDateControl.hasNativeCalendar && metrics.birthDateControl.leadingIconImage === 'none'), JSON.stringify(metrics.birthDateControl));
  }
  if (entry.key === 'forgot' || entry.key === 'reset' || entry.key === 'verify') check(`${entry.key} support card is 440px wide`, Math.abs((metrics.card?.width ?? 0) - 440) <= 1, JSON.stringify(metrics.card));
  check(`${entry.key} intended illustration slot present`, Boolean(metrics.illustrationClass?.includes(`auth-illustration-${entry.illustration}`)), String(metrics.illustrationClass));
  if (['student-man', 'student-woman', 'verification'].includes(entry.illustration)) {
    check(`${entry.key} source illustration image loaded`, Boolean(metrics.image?.complete && metrics.image.naturalWidth > 0 && metrics.image.src?.includes(`${entry.illustration === 'verification' ? 'verification-envelope' : entry.illustration}.png`)), JSON.stringify(metrics.image));
  }
  if (entry.key === 'register') check('register has both reference slogans', metrics.sloganCount === 2, String(metrics.sloganCount));
  authRuns.push({ ...entry, filename, dimensions, metrics, intentionalHeightAdjustment, referenceCard: { x, y, width, height }, delta: deltas, adjustedHeightDelta: Math.round(adjustedHeightDelta) });
}

async function testResetRecovery(page) {
  const manualEmail = process.env.ISSUE22_ADMIN_EMAIL ?? `issue22.visual.invalid.${stamp}@example.test`;
  const manualToken = `invalid-manual-token-${stamp}`;
  await page.goto(`${base}/auth/reset-password`, { waitUntil: 'networkidle' });
  await page.locator('#support-email').fill(manualEmail);
  await page.locator('#reset-token').fill(manualToken);
  await page.locator('#new-password').fill(`RecoveryPass!${stamp}`);
  await page.locator('#new-password-confirmation').fill(`RecoveryPass!${stamp}`);
  check('manual reset token survives other field edits', await page.locator('#reset-token').inputValue() === manualToken);
  const manualRequest = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/reset-password'));
  await page.getByRole('button', { name: 'Cập nhật mật khẩu' }).click();
  const manualResponse = await manualRequest;
  const manualAlert = page.getByRole('alert').first();
  await manualAlert.waitFor({ state: 'visible', timeout: 5000 });
  const manualErrorText = (await manualAlert.textContent())?.trim() ?? '';
  check('manual reset token reaches real API and receives 422', manualResponse.status() === 422, String(manualResponse.status()));
  check('manual token and email remain editable after 422', await page.locator('#reset-token').inputValue() === manualToken && await page.locator('#support-email').inputValue() === manualEmail && await page.locator('#reset-token').isEnabled() && await page.locator('#support-email').isEnabled());
  check('invalid manual reset returns a meaningful error', /token|expired|hết hạn|liên kết|mật khẩu/i.test(manualErrorText), 'API 422 error message inspected without recording the email address');
  const recoveredEmail = `issue22.visual.recovery.${stamp}@example.test`;
  await page.locator('#support-email').fill(recoveredEmail);
  check('manual reset email can be corrected after API error', await page.locator('#support-email').inputValue() === recoveredEmail);

  await page.goto(`${base}/auth/reset-password?email=not-an-email&token=visual-only`, { waitUntil: 'networkidle' });
  await page.locator('#new-password').fill(`RecoveryPass!${stamp}`);
  await page.locator('#new-password-confirmation').fill(`RecoveryPass!${stamp}`);
  check('linked reset keeps synthetic token in form state', await page.locator('input[name="token"]').inputValue() === 'visual-only');
  const linkedRequest = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/reset-password'));
  await page.getByRole('button', { name: 'Cập nhật mật khẩu' }).click();
  const linkedResponse = await linkedRequest;
  const emailField = page.locator('#support-email');
  const emailError = page.locator('#support-email-error');
  await emailField.waitFor({ state: 'visible', timeout: 5000 });
  await emailError.waitFor({ state: 'visible', timeout: 5000 });
  const fieldErrorText = (await emailError.textContent())?.trim() ?? '';
  check('linked email validation reaches real API and receives 422', linkedResponse.status() === 422, String(linkedResponse.status()));
  check('email error reveals an editable address field', await emailField.getAttribute('aria-invalid') === 'true' && await emailField.isEnabled() && await emailField.inputValue() === 'not-an-email');
  check('new email validation has a meaningful field error', /email|valid|địa chỉ|hợp lệ/i.test(fieldErrorText), fieldErrorText);
  await emailField.fill(recoveredEmail);
  check('linked reset email can be corrected after API error', await emailField.inputValue() === recoveredEmail);

  const validLinkEmail = `issue22.visual.link.${stamp}@example.test`;
  await page.goto(`${base}/auth/reset-password?email=${encodeURIComponent(validLinkEmail)}&token=visual-only`, { waitUntil: 'networkidle' });
  check('valid reset link keeps prefilled email hidden', await page.locator('#support-email').count() === 0 && await page.locator('input[name="email"][type="hidden"]').inputValue() === validLinkEmail);
}

async function captureOnboarding(page, stepIndex, viewport) {
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(100);
  const metrics = await page.evaluate(() => {
    const box = (element) => {
      if (!element) return null;
      const rect = element.getBoundingClientRect();
      return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
    };
    const selectorBox = (selector) => box(document.querySelector(selector));
    const controls = [...document.querySelectorAll('.onboarding-step-main input:not([type="hidden"]), .onboarding-step-main textarea, .onboarding-step-main select, .onboarding-step-main .onboarding-file-picker')]
      .filter((element) => element.getClientRects().length)
      .map((element) => ({ tag: element.tagName.toLowerCase(), id: element.id || null, className: typeof element.className === 'string' ? element.className : '', width: element.getBoundingClientRect().width, height: element.getBoundingClientRect().height }));
    const stepperCircles = [...document.querySelectorAll('.onboarding-step-number')].map((element) => {
      const rect = element.getBoundingClientRect();
      return Math.round(rect.left + rect.width / 2);
    });
    const main = document.querySelector('.app-main');
    const content = document.querySelector('.app-main .page-content');
    const shell = document.querySelector('.authenticated-frame');
    const sidebar = document.querySelector('.app-sidebar');
    const aside = document.querySelector('.onboarding-editor-aside');
    return {
      viewport: { width: document.documentElement.clientWidth, height: window.innerHeight },
      documentWidth: document.documentElement.scrollWidth,
      shell: box(shell),
      header: selectorBox('.app-topbar'),
      main: box(main),
      content: box(content),
      sidebar: sidebar ? { ...box(sidebar), display: getComputedStyle(sidebar).display } : null,
      pageHeading: selectorBox('.onboarding-page-title'),
      stepper: selectorBox('.onboarding-stepper'),
      stepperCircles,
      activeStep: document.querySelector('.onboarding-step[aria-current="step"]')?.textContent?.trim() ?? document.querySelector('.onboarding-step.is-current')?.textContent?.trim() ?? '',
      panelHeading: selectorBox('.onboarding-panel-heading h2'),
      panelHeadingText: document.querySelector('.onboarding-panel-heading h2')?.textContent?.trim() ?? '',
      editor: selectorBox('.onboarding-editor-grid'),
      mainCard: selectorBox('.onboarding-step-main'),
      statusAside: aside ? box(aside) : null,
      statusCard: selectorBox('.onboarding-review-status'),
      controls,
    };
  });
  const filename = `redesign-tutor-step-${stepIndex}-${viewport.name}.png`;
  const bytes = await page.screenshot({ path: path.join(out, filename), animations: 'disabled' });
  const dimensions = pngSize(bytes);
  const key = `step ${stepIndex} ${viewport.width}px ${viewport.name}`;
  const expectedHeadings = ['Thông tin cơ bản', 'Chuyên môn giảng dạy', 'Kinh nghiệm giảng dạy', 'Tài liệu xác minh', 'Xem lại hồ sơ'];
  check(`${key} exact viewport PNG dimensions`, dimensions.width === viewport.width && dimensions.height === viewport.height, JSON.stringify({ expected: { width: viewport.width, height: viewport.height }, actual: dimensions }));
  check(`${key} authenticated frame spans viewport`, metrics.shell && Math.abs(metrics.shell.x) <= 1 && Math.abs(metrics.shell.width - viewport.width) <= 1, JSON.stringify(metrics.shell));
  check(`${key} main row and page content fill available column`, metrics.main && metrics.content && Math.abs(metrics.content.width - metrics.main.width) <= 1, JSON.stringify({ main: metrics.main, content: metrics.content }));
  check(`${key} no horizontal overflow`, metrics.documentWidth <= viewport.width, `${metrics.documentWidth}/${viewport.width}`);
  check(`${key} expected section heading visible`, Boolean(metrics.panelHeading?.width && metrics.panelHeading.height && metrics.panelHeadingText === expectedHeadings[stepIndex - 1]), JSON.stringify({ text: metrics.panelHeadingText, box: metrics.panelHeading }));
  if (viewport.width >= 768) check(`${key} 180px role sidebar`, metrics.sidebar?.display !== 'none' && Math.abs((metrics.sidebar?.width ?? 0) - 180) <= 1, JSON.stringify(metrics.sidebar));
  if ([1, 2, 3, 4].includes(stepIndex) && viewport.width === 2034) check(`${key} editor panel fills content row`, metrics.editor && metrics.mainCard && Math.abs(metrics.editor.width - metrics.mainCard.width) <= 2, JSON.stringify({ editor: metrics.editor, card: metrics.mainCard }));
  if (stepIndex === 5 && viewport.width === 2034) {
    check(`${key} review summary and status remain side by side`, metrics.editor && metrics.statusAside && metrics.statusCard && metrics.statusAside.x > metrics.mainCard.x, JSON.stringify({ editor: metrics.editor, mainCard: metrics.mainCard, aside: metrics.statusAside, statusCard: metrics.statusCard }));
    check(`${key} status aside close to 320px reference`, Math.abs((metrics.statusCard?.width ?? 0) - 320) <= 28, JSON.stringify(metrics.statusCard));
  }
  if (viewport.width === 2034 && stepIndex === 1) check(`${key} dashboard heading near 28px`, Math.abs((await page.locator('.onboarding-page-title').evaluate((element) => parseFloat(getComputedStyle(element).fontSize))) - 28) <= 2, JSON.stringify(metrics.pageHeading));
  onboardingRuns.push({ step: stepIndex, label: steps[stepIndex - 1], viewport, filename, dimensions, metrics });
}

async function captureRoleShell(page, role, pathName, expectedRole, navName) {
  const response = await page.goto(`${base}${pathName}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(250);
  const result = await page.evaluate(async () => {
    const me = await fetch('http://localhost:8000/api/v1/me', { credentials: 'include', headers: { Accept: 'application/json' } });
    const payload = me.ok ? await me.json() : null;
    const user = payload?.data?.user ?? payload?.data ?? payload?.user ?? payload;
    return {
      role: user?.role ?? null,
      frameClass: document.querySelector('.app-frame')?.className ?? null,
      sidebarLabel: document.querySelector('.sidebar-label')?.textContent?.trim() ?? null,
      nav: document.querySelector('.sidebar-nav')?.innerText?.trim() ?? '',
      accessHeading: document.querySelector('.access-state h1')?.textContent?.trim() ?? null,
      accessLabel: document.querySelector('.access-state .eyebrow')?.textContent?.trim() ?? null,
    };
  });
  check(`${role} protected route response`, Boolean(response && response.status() < 400), String(response?.status() ?? 'no response'));
  check(`${role} /me reports its real role`, result.role === expectedRole, String(result.role));
  if (navName) check(`${role} role navigation is correct`, result.nav.includes(navName), result.nav.replaceAll('\n', ' | '));
  roleRuns.push({ role, path: pathName, ...result });
  return result;
}

try {
  if (!onboardingOnly) {
    const guestContext = await browser.newContext({ viewport: { width: 1536, height: 1000 }, deviceScaleFactor: 1 });
    const guest = await guestContext.newPage();
    for (const entry of authRoutes) await captureAuth(guest, entry);
    await testResetRecovery(guest);
    await guest.setViewportSize({ width: 1536, height: 1000 });
    await guest.goto(`${base}/admin`, { waitUntil: 'networkidle' });
    const guestAccess = await guest.locator('.access-state h1').textContent().catch(() => '');
    check('guest cannot open Admin route', String(guestAccess).includes('đăng nhập') || String(guestAccess).includes('Đăng nhập'), String(guestAccess).trim());
    roleRuns.push({ role: 'guest', path: '/admin', accessHeading: String(guestAccess).trim() });
    await guestContext.close();

    const customerContext = await browser.newContext({ viewport: { width: 1536, height: 1000 }, deviceScaleFactor: 1 });
    const customer = await customerContext.newPage();
    const customerEmail = await createVerifiedAccount(customer, 'customer', 'Issue 22 Visual Customer');
    await login(customer, customerEmail, '/marketplace');
    const customerShell = await captureRoleShell(customer, 'customer', '/admin', 'customer', 'Tìm dịch vụ');
    check('customer is denied Admin content', Boolean(customerShell.accessLabel?.includes('Không đủ quyền') && customerShell.accessHeading?.includes('Quản trị')) && !/Duyệt hồ sơ|Admin/.test(customerShell.nav), JSON.stringify(customerShell));
    await customerContext.close();
  }

  const tutorContext = await browser.newContext({ viewport: { width: 1536, height: 1000 }, deviceScaleFactor: 1 });
  const tutor = await tutorContext.newPage();
  const tutorEmail = await createVerifiedAccount(tutor, 'tutor', 'Issue 22 Visual Tutor');
  await login(tutor, tutorEmail, '/profile');
  await tutor.getByRole('heading', { name: 'Thông tin cơ bản' }).waitFor({ timeout: 10000 });
  for (let stepIndex = 1; stepIndex <= 5; stepIndex++) {
    if (stepIndex === 2) {
      await tutor.locator('#tutor-headline').fill('Visual QA tutor headline');
      await tutor.locator('#tutor-bio').fill('A short synthetic tutor introduction for the refreshed onboarding layout.');
      await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
      await tutor.getByRole('heading', { name: 'Chuyên môn giảng dạy' }).waitFor();
    } else if (stepIndex === 3) {
      const specialization = await tutor.locator('#tutor-specialization_id option').evaluateAll((options) => options.find((option) => option.value)?.value ?? '');
      check('visual Tutor fixture has an active specialization', Boolean(specialization));
      await tutor.locator('#tutor-specialization_id').selectOption(specialization);
      await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
      await tutor.getByRole('heading', { name: 'Kinh nghiệm giảng dạy' }).waitFor();
    } else if (stepIndex === 4) {
      await tutor.locator('#tutor-experience_years').fill('4');
      await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
      await tutor.getByRole('heading', { name: 'Tài liệu xác minh' }).waitFor();
    } else if (stepIndex === 5) {
      await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
      await tutor.getByRole('heading', { name: 'Xem lại hồ sơ' }).waitFor();
    }
    for (const viewport of viewports) {
      const captureViewport = viewport.name === 'reference-shell' ? { ...viewport, height: references[stepIndex - 1].innerHeight } : viewport;
      await captureOnboarding(tutor, stepIndex, captureViewport);
    }
  }
  if (!onboardingOnly) {
    const tutorShell = await captureRoleShell(tutor, 'tutor', '/profile', 'tutor', 'Hồ sơ Tutor');
    check('Tutor has its own protected profile shell', Boolean(tutorShell.frameClass?.includes('authenticated-frame')) && !tutorShell.accessHeading, JSON.stringify(tutorShell));
  }
  await tutorContext.close();

  const adminEmail = process.env.ISSUE22_ADMIN_EMAIL;
  const adminPassword = process.env.ISSUE22_ADMIN_PASSWORD;
  if (!onboardingOnly) check('seeded Admin credentials supplied privately', Boolean(adminEmail && adminPassword), 'values are not recorded');
  if (!onboardingOnly && adminEmail && adminPassword) {
    const adminContext = await browser.newContext({ viewport: { width: 1536, height: 1000 }, deviceScaleFactor: 1 });
    const admin = await adminContext.newPage();
    await admin.goto(`${base}/auth/login`, { waitUntil: 'networkidle' });
    await admin.locator('#email').fill(adminEmail);
    await admin.locator('#password').fill(adminPassword);
    const adminLogin = admin.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/login'));
    await admin.getByRole('button', { name: 'Đăng nhập' }).click();
    const loginResponse = await adminLogin;
    check('seeded Admin login succeeds', loginResponse.status() === 200, String(loginResponse.status()));
    await admin.waitForURL(/\/admin(?:$|[?#])/, { timeout: 10000 });
    const adminShell = await captureRoleShell(admin, 'admin', '/admin', 'admin', 'Duyệt hồ sơ Tutor');
    check('Admin route uses its authenticated account shell', Boolean(adminShell.frameClass?.includes('authenticated-frame')) && adminShell.sidebarLabel === 'Quản trị', JSON.stringify(adminShell));
    await adminContext.close();
  }

  const report = {
    generatedAt: new Date().toISOString(),
    phase: onboardingOnly ? 'onboarding-only' : 'full',
    base,
    checks,
    errors,
    limitations,
    auth: authRuns,
    onboarding: onboardingRuns,
    roles: roleRuns,
  };
  const prefix = onboardingOnly ? 'early-onboarding' : 'visual';
  await fs.writeFile(path.join(out, `${prefix}-report.json`), `${JSON.stringify(report, null, 2)}\n`);
  const authPairs = authRuns.map((run) => {
    const [sourceWidth] = run.refSize;
    const [left, bottom] = run.margins;
    const [targetWidth, targetHeight] = run.viewport;
    const cropped = left || bottom;
    const source = `<img${cropped ? ' class="cropped"' : ''} src="references/${encodeURIComponent(run.ref)}" alt="Reference: ${run.ref}"${cropped ? ` style="width:${(sourceWidth / targetWidth * 100).toFixed(4)}%;left:-${(left / targetWidth * 100).toFixed(4)}%;"` : ''}>`;
    const reference = cropped ? `<div class="crop-frame" style="aspect-ratio:${targetWidth}/${targetHeight}">${source}</div>` : source;
    const cardNote = run.intentionalHeightAdjustment ? `Reference card height ${run.referenceCard.height}px plus documented ${run.intentionalHeightAdjustment}px for registration confirmation fields; measured ${run.metrics.card.height.toFixed(1)}px (${run.adjustedHeightDelta}px beyond adjusted height).` : `Card delta from reference ${JSON.stringify(run.delta)}.`;
    return `<section class="pair"><h2>${run.route.split('?')[0]}</h2><p>Target inner viewport ${targetWidth} × ${targetHeight}; browser PNG ${run.dimensions.width} × ${run.dimensions.height}; ${cardNote}</p><div class="images"><figure><figcaption>Refreshed PNG reference${cropped ? ' (export margins cropped)' : ''}</figcaption>${reference}</figure><figure><figcaption>Browser capture</figcaption><img src="${run.filename}" alt="${run.route.split('?')[0]} browser capture"></figure></div></section>`;
  }).join('');
  const onboardingPairs = references.map((ref, index) => {
    const run = onboardingRuns.find((item) => item.step === index + 1 && item.viewport.name === 'reference-shell');
    const targetWidth = 2034;
    const sourceWidth = ref.size[0];
    const left = 31;
    const top = 23;
    const reference = `<div class="crop-frame" style="aspect-ratio:${targetWidth}/${ref.innerHeight}"><img class="cropped" src="references/${encodeURIComponent(ref.file)}" alt="Reference: ${ref.file}" style="width:${(sourceWidth / targetWidth * 100).toFixed(4)}%;left:-${(left / targetWidth * 100).toFixed(4)}%;top:-${(top / ref.innerHeight * 100).toFixed(4)}%;"></div>`;
    return `<section class="pair"><h2>Tutor onboarding · step ${index + 1}</h2><p>Inner shell target 2034 × ${ref.innerHeight}; browser PNG ${run?.dimensions.width ?? '?'} × ${run?.dimensions.height ?? '?'}.</p><div class="images"><figure><figcaption>Refreshed PNG reference (export border cropped)</figcaption>${reference}</figure><figure><figcaption>Browser capture</figcaption><img src="${run?.filename ?? ''}" alt="Tutor step ${index + 1} browser capture"></figure></div></section>`;
  }).join('');
  const status = errors.length ? `<p class="fail">${errors.length} checks failed. See ${prefix}-report.json for measurements.</p>` : '<p class="pass">All recorded visual and role checks passed.</p>';
  await fs.writeFile(path.join(out, `${prefix}-comparison.html`), `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Issue 22 refreshed visual comparison</title><style>body{font:14px system-ui,sans-serif;margin:24px;background:#f4f6f8;color:#17202a}h1{font-size:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.pair{background:#fff;padding:14px;border-radius:8px;box-shadow:0 1px 5px #0002;min-width:0}.pair h2{font-size:16px;margin:0}.pair p{color:#566174;font-size:12px}.images{display:grid;grid-template-columns:1fr 1fr;align-items:start;gap:10px}figure{margin:0;min-width:0}figcaption{font-weight:700;margin:8px 0}.images img{display:block;width:100%;height:auto;border:1px solid #ccd2d8;background:#fff}.crop-frame{background:#fff;border:1px solid #ccd2d8;overflow:hidden;position:relative;width:100%}.crop-frame img.cropped{border:0;max-width:none;position:absolute}.pass{color:#137a58}.fail{color:#b42318;font-weight:700}@media(max-width:900px){.grid{grid-template-columns:1fr}.images{grid-template-columns:1fr}}</style><h1>Issue 22 refreshed PNG / browser comparison</h1>${status}<p>Side-by-side comparisons crop decorative export margins from Reset, Verify, and Tutor shell references. Browser captures use the corresponding inner viewport bounds.</p><h2>Authentication</h2><div class="grid">${authPairs}</div><h2>Tutor onboarding at the inner shell viewport</h2><div class="grid">${onboardingPairs}</div><h2>Additional captures</h2><p>Each of five tutor steps also has exact viewport screenshots at 360, 768, 1536, and 2560 pixels. See the artifact folder and ${prefix}-report.json.</p><h2>Known visual limits</h2><ul>${limitations.map((item) => `<li>${item}</li>`).join('')}</ul>`);
  const failures = errors.length ? errors.map((error) => `- FAIL: ${error}`).join('\n') : 'All recorded visual and role checks passed.';
  const authSummary = authRuns.map((run) => `- ${run.route}: ${run.dimensions.width}×${run.dimensions.height}; card delta x/y/w/h = ${run.delta.x}/${run.delta.y}/${run.delta.width}/${run.delta.height}px${run.intentionalHeightAdjustment ? ` (expected +${run.intentionalHeightAdjustment}px registration fields; ${run.adjustedHeightDelta}px beyond that adjusted target)` : ''}.`).join('\n');
  const referenceHeights = references.map((item) => item.innerHeight).join(', ');
  await fs.writeFile(path.join(out, `${prefix}-qa.md`), `# Issue 22 refreshed visual QA${onboardingOnly ? ' — early onboarding pass' : ''}\n\nGenerated: ${report.generatedAt}\n\nChecks: ${checks.length}; passed: ${checks.length - errors.length}; failed: ${errors.length}.\n\n${onboardingOnly ? 'Auth and role checks are deferred until the Auth source is ready.\n\n' : `## Authentication captures\n\n${authSummary}\n\n`}## Tutor onboarding captures\n\nCaptured all five steps at 2034px width with inner-reference viewport heights ${referenceHeights}px, and at 360×800, 768×1000, 1536×1000, and 2560×1440. Each PNG header is checked against the requested viewport dimensions; each capture records shell, content, stepper, panel, aside, and control bounds.\n\n${onboardingOnly ? '' : `## Role checks\n\nChecked guest access to /admin, Customer denial at /admin, Tutor profile shell, and the seeded Admin's authenticated shell using real /me responses. Marketplace rendering is not used as evidence.\n\n`}## Results\n\n${failures}\n\n## Known visual limits\n\n${limitations.map((item) => `- ${item}`).join('\n')}\n\nSee [${prefix}-comparison.html](${prefix}-comparison.html) for side-by-side captures and [${prefix}-report.json](${prefix}-report.json) for raw measurements.\n`);
  console.log(JSON.stringify({ checks: checks.length, failures: errors.length, output: path.join(out, `${prefix}-qa.md`), errors }, null, 2));
  process.exitCode = errors.length ? 1 : 0;
} catch (error) {
  const failure = error instanceof Error ? error.stack ?? error.message : String(error);
  errors.push(failure.split('\n')[0]);
  await fs.writeFile(path.join(out, `${onboardingOnly ? 'early-onboarding' : 'visual'}-report.json`), `${JSON.stringify({ generatedAt: new Date().toISOString(), checks, errors }, null, 2)}\n`);
  console.error(failure);
  process.exitCode = 1;
} finally {
  await browser.close();
}
