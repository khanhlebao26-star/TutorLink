import fs from 'node:fs/promises';
import path from 'node:path';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');

const base = process.env.TUTORLINK_URL ?? 'http://localhost:3000';
const mailpit = process.env.MAILPIT_URL ?? 'http://localhost:8025';
const out = path.resolve('outputs/issue-22-figma-layout');
await fs.mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.TUTORLINK_CHROME_PATH ? { executablePath: process.env.TUTORLINK_CHROME_PATH } : {}), args: ['--no-sandbox'] });
const checks = [];
const requests = [];
const failures = [];
const stamp = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
const password = `Issue22!${stamp}`;
const customerEmail = `issue22.customer.${stamp}@example.test`;
const tutorEmail = `issue22.tutor.${stamp}@example.test`;
const adminEmail = process.env.ISSUE22_ADMIN_EMAIL ?? `issue22.admin.${stamp}@example.test`;

function check(name, pass, detail = '') {
  checks.push({ name, result: pass ? 'PASS' : 'FAIL', detail });
  if (!pass) failures.push(`${name}${detail ? `: ${detail}` : ''}`);
}
function safePath(pathname) {
  return pathname.replace(/\/auth\/email\/verify\/\d+\/[a-f0-9]+/i, '/auth/email/verify/:id/:hash');
}
function watch(context) {
  context.on('request', (req) => {
    const url = new URL(req.url());
    if (url.pathname.startsWith('/api/v1/')) requests.push({ method: req.method(), path: safePath(url.pathname), status: null });
  });
  context.on('response', (res) => {
    const url = new URL(res.url());
    if (url.pathname.startsWith('/api/v1/')) {
      const item = [...requests].reverse().find((entry) => entry.path === safePath(url.pathname) && entry.method === res.request().method() && entry.status === null);
      if (item) item.status = res.status();
    }
  });
}
async function waitForMail(email, subject, context, excludeIds = new Set()) {
  for (let i = 0; i < 30; i++) {
    const listResponse = await context.request.get(`${mailpit}/api/v1/messages?start=0&limit=50`);
    if (listResponse.ok()) {
      const payload = await listResponse.json();
      const messages = payload.messages ?? payload.data ?? [];
      for (const message of messages) {
        const recipients = (message.To ?? message.to ?? []).map((to) => typeof to === 'string' ? to : (to.Address ?? to.address ?? ''));
        const subjectValue = message.Subject ?? message.subject ?? '';
        if (!recipients.includes(email) || !subjectValue.toLowerCase().includes(subject.toLowerCase())) continue;
        const id = message.ID ?? message.id;
        if (excludeIds.has(id)) continue;
        const detailResponse = await context.request.get(`${mailpit}/api/v1/message/${encodeURIComponent(id)}`);
        if (!detailResponse.ok()) continue;
        const detail = await detailResponse.json();
        const content = `${detail.Text ?? ''}\n${detail.HTML ?? ''}`;
        return { id, content };
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 400));
  }
  throw new Error(`Mailpit message timed out for fixture ${email.split('@')[0].split('.').at(-1)} (${subject})`);
}
function getVerifyQuery(mailContent) {
  const candidates = [...mailContent.matchAll(/https?:[^\s"'<>]+/g)].map((match) => match[0].replaceAll('&amp;', '&'));
  const candidate = candidates.find((value) => value.includes('/auth/verify-email?'));
  if (!candidate) throw new Error('Verification link not found in delivered message');
  const url = new URL(candidate);
  return { id: url.searchParams.get('id'), hash: url.searchParams.get('hash'), expires: url.searchParams.get('expires'), signature: url.searchParams.get('signature') };
}
function getResetQuery(mailContent) {
  const candidates = [...mailContent.matchAll(/https?:[^\s"'<>]+/g)].map((match) => match[0].replaceAll('&amp;', '&'));
  const candidate = candidates.find((value) => value.includes('/auth/reset-password?'));
  if (!candidate) throw new Error('Password reset link not found in delivered message');
  const url = new URL(candidate);
  return { email: url.searchParams.get('email'), token: url.searchParams.get('token') };
}
async function mailIds(context, email, subject) {
  const response = await context.request.get(`${mailpit}/api/v1/messages?start=0&limit=50`);
  if (!response.ok()) return new Set();
  const payload = await response.json();
  return new Set((payload.messages ?? []).filter((message) => {
    const recipients = (message.To ?? []).map((to) => to.Address ?? '');
    return recipients.includes(email) && (message.Subject ?? '').toLowerCase().includes(subject.toLowerCase());
  }).map((message) => message.ID));
}
async function register(page, { name, email, role }) {
  await page.goto(`${base}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('#full-name').fill(name);
  await page.locator('#date-of-birth').fill('1990-01-02');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password-confirmation').fill(password);
  await page.locator(`input[name="role"][value="${role}"]`).check();
  const responsePromise = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/register'));
  await page.getByRole('button', { name: 'Tạo tài khoản' }).click();
  const response = await responsePromise;
  check(`${role} register POST status`, response.status() === 201, String(response.status()));
  await page.getByRole('status').filter({ hasText: 'Đăng ký thành công' }).waitFor({ state: 'visible' });
  check(`${role} register success notice`, true);
  const cookies = await page.context().cookies();
  check(`${role} session cookie HttpOnly`, cookies.some((cookie) => cookie.name.toLowerCase().includes('session') && cookie.httpOnly));
  check(`${role} CSRF cookie present`, cookies.some((cookie) => cookie.name === 'XSRF-TOKEN'));
  const mail = await waitForMail(email, 'Verify', page.context());
  return mail;
}
async function verifyThroughUi(page, query) {
  const qs = new URLSearchParams(Object.entries(query).filter(([, value]) => value));
  await page.goto(`${base}/auth/verify-email?${qs}`, { waitUntil: 'networkidle' });
  await page.getByRole('status').filter({ hasText: 'Email đã được xác minh' }).waitFor();
  check('signed email link verified through UI', true);
  await page.getByRole('link', { name: 'Tiếp tục đăng nhập' }).waitFor();
}
async function resendAndVerify(page, email, initialMail) {
  const existing = await mailIds(page.context(), email, 'verify');
  if (initialMail?.id) existing.add(initialMail.id);
  await page.goto(`${base}/auth/verify-email`, { waitUntil: 'networkidle' });
  const responsePromise = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/email/verification-notification'));
  await page.getByRole('button', { name: 'Gửi lại email xác minh' }).click();
  const response = await responsePromise;
  check('resend verification API returns 200', response.status() === 200, String(response.status()));
  const resent = await waitForMail(email, 'verify', page.context(), existing);
  check('resend delivered a real verification email', true);
  await verifyThroughUi(page, getVerifyQuery(resent.content));
}
async function login(page, email, pass = password) {
  await page.goto(`${base}/auth/login`, { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(pass);
  const responsePromise = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/login'));
  await page.getByRole('button', { name: 'Đăng nhập' }).click();
  return responsePromise;
}
async function takeScreenshot(page, name) {
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: path.join(out, name), fullPage: true });
}
async function apiStatus(page, url, options = {}) {
  return page.evaluate(async ({ url, options }) => {
    const response = await fetch(url, { credentials: 'include', ...options, headers: { Accept: 'application/json', ...(options.headers ?? {}) } });
    return response.status;
  }, { url, options });
}

try {
  const customerContext = await browser.newContext({ viewport: { width: 1536, height: 1000 } });
  watch(customerContext);
  const customer = await customerContext.newPage();
  const customerMail = await register(customer, { name: 'Issue 22 Customer', email: customerEmail, role: 'customer' });
  // A new context proves the rejected password does not inherit the registration session.
  const wrongContext = await browser.newContext();
  watch(wrongContext);
  const wrongPage = await wrongContext.newPage();
  const wrongResponse = await login(wrongPage, customerEmail, 'WrongIssue22!');
  check('wrong password returns 401', (await wrongResponse).status() === 401);
  check('wrong password message visible', await wrongPage.getByRole('alert').count() > 0);
  await wrongContext.close();
  await resendAndVerify(customer, customerEmail, customerMail);
  await login(customer, customerEmail);
  await customer.waitForURL(/\/marketplace/);
  await customer.getByRole('navigation', { name: 'Điều hướng tài khoản' }).waitFor();
  const customerNav = await customer.getByRole('navigation', { name: 'Điều hướng tài khoản' }).innerText();
  check('customer nav has customer destination', customerNav.includes('Tìm dịch vụ'));
  check('customer nav omits Admin and tutor links', !/Admin|Duyệt hồ sơ|Hồ sơ Tutor/.test(customerNav));
  const customerMeStatus = await apiStatus(customer, 'http://localhost:8000/api/v1/me');
  check('authenticated me returns 200', customerMeStatus === 200, String(customerMeStatus));
  const cookies = await customerContext.cookies();
  check('session and CSRF cookies after login', cookies.some((cookie) => cookie.name.toLowerCase().includes('session') && cookie.httpOnly) && cookies.some((cookie) => cookie.name === 'XSRF-TOKEN'));
  const csrfToken = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value;
  const missingCsrf = await apiStatus(customer, 'http://localhost:8000/api/v1/auth/logout', { method: 'POST' });
  check('mutation without CSRF returns 419', missingCsrf === 419, String(missingCsrf));
  const validCsrf = await apiStatus(customer, 'http://localhost:8000/api/v1/auth/logout', { method: 'POST', headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrfToken ?? '') } });
  check('logout API returns 204', validCsrf === 204, String(validCsrf));
  check('me after logout returns 401', (await apiStatus(customer, 'http://localhost:8000/api/v1/me')) === 401);
  await takeScreenshot(customer, 'customer-authenticated-desktop.png');
  await customer.goto(`${base}/auth/forgot-password`, { waitUntil: 'networkidle' });
  await customer.locator('#support-email').fill(customerEmail);
  const forgotResponsePromise = customer.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/forgot-password'));
  await customer.getByRole('button', { name: 'Gửi liên kết đặt lại' }).click();
  const forgotResponse = await forgotResponsePromise;
  check('forgot-password API returns 200', forgotResponse.status() === 200, String(forgotResponse.status()));
  const resetMail = await waitForMail(customerEmail, 'reset', customerContext);
  check('password reset delivered a real email', true);
  const resetQuery = getResetQuery(resetMail.content);
  await customer.goto(`${base}/auth/reset-password?${new URLSearchParams(resetQuery)}`, { waitUntil: 'networkidle' });
  await customer.locator('#new-password').fill(`${password}Reset`);
  await customer.locator('#new-password-confirmation').fill(`${password}Reset`);
  const resetResponsePromise = customer.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/auth/reset-password'));
  await customer.getByRole('button', { name: 'Cập nhật mật khẩu' }).click();
  const resetResponse = await resetResponsePromise;
  check('reset-password API returns 200', resetResponse.status() === 200, String(resetResponse.status()));
  const newPasswordResponse = await login(customer, customerEmail, `${password}Reset`);
  check('new password logs in successfully', newPasswordResponse.status() === 200, String(newPasswordResponse.status()));
  await customerContext.close();

  const tutorContext = await browser.newContext({ viewport: { width: 1536, height: 1100 } });
  watch(tutorContext);
  const tutor = await tutorContext.newPage();
  const tutorMail = await register(tutor, { name: 'Issue 22 Tutor', email: tutorEmail, role: 'tutor' });
  await resendAndVerify(tutor, tutorEmail, tutorMail);
  await login(tutor, tutorEmail);
  await tutor.waitForURL(/\/profile/);
  await tutor.waitForLoadState('networkidle');
  await tutor.waitForTimeout(500);
  await takeScreenshot(tutor, 'tutor-onboarding-initial.png');
  const initialHeadings = await tutor.locator('h1,h2,h3').allTextContents();
  const panelReady = await tutor.locator('.onboarding-stepper').count() > 0;
  check('tutor onboarding initial panel loaded', panelReady, JSON.stringify({ headings: initialHeadings, path: new URL(tutor.url()).pathname, alertVisible: await tutor.getByRole('alert').count() > 0 }));
  await tutor.getByRole('heading', { name: 'Thông tin cơ bản' }).waitFor({ timeout: 5000 });
  check('tutor navigation active profile', await tutor.getByRole('link', { name: 'Hồ sơ Tutor' }).getAttribute('aria-current') === 'page');
  check('tutor navigation omits Admin link', await tutor.getByRole('navigation', { name: 'Điều hướng tài khoản' }).getByText('Duyệt hồ sơ Tutor').count() === 0);
  await takeScreenshot(tutor, 'tutor-step-1-desktop.png');

  // Empty first-step validation keeps the user at the missing headline.
  await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
  check('step 1 required validation shown', await tutor.getByRole('alert').count() > 0);
  const fileInput = tutor.locator('#tutor-avatar-file');
  await fileInput.setInputFiles({ name: 'too-large.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(2 * 1024 * 1024 + 1) });
  check('oversized avatar rejected in browser', await tutor.getByRole('alert').filter({ hasText: 'tối đa 2 MiB' }).count() > 0);
  await tutor.locator('#tutor-headline').fill('Issue 22 synthetic tutor headline');
  await tutor.locator('#tutor-bio').fill('I teach a synthetic demonstration lesson and help learners practice clearly.');
  await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
  await tutor.getByRole('heading', { name: 'Chuyên môn giảng dạy' }).waitFor();
  await takeScreenshot(tutor, 'tutor-step-2-desktop.png');
  const specialization = await tutor.locator('#tutor-specialization_id option').evaluateAll((options) => options.find((option) => option.value)?.value ?? '');
  check('active specialization available', specialization !== '');
  await tutor.locator('#tutor-specialization_id').selectOption(specialization);
  await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
  await tutor.getByRole('heading', { name: 'Kinh nghiệm giảng dạy' }).waitFor();
  await takeScreenshot(tutor, 'tutor-step-3-desktop.png');
  await tutor.locator('#tutor-experience_years').fill('4');
  await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
  await tutor.getByRole('heading', { name: 'Tài liệu xác minh' }).waitFor();
  await takeScreenshot(tutor, 'tutor-step-4-desktop.png');
  await tutor.locator('.onboarding-stepper').getByRole('button', { name: /Thông tin cơ bản/ }).click();
  await tutor.locator('#tutor-headline').fill('Issue 22 unsaved headline before simulated recovery');
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aTsAAAAASUVORK5CYII=', 'base64');
  let simulatedCompleteFailure = false;
  await tutor.route('**/api/v1/files/*/complete', async (route) => {
    if (!simulatedCompleteFailure) {
      simulatedCompleteFailure = true;
      await route.abort('failed');
      return;
    }
    await route.continue();
  });
  await tutor.locator('#tutor-avatar-file').setInputFiles({ name: 'issue22-avatar.png', mimeType: 'image/png', buffer: png });
  await tutor.getByRole('button', { name: 'Thử lại' }).waitFor({ state: 'visible' });
  check('one simulated avatar completion network failure shown', simulatedCompleteFailure);
  check('unsaved headline survives simulated avatar upload failure', await tutor.locator('#tutor-headline').inputValue() === 'Issue 22 unsaved headline before simulated recovery');
  await tutor.getByRole('button', { name: 'Thử lại' }).click();
  await tutor.locator('.onboarding-upload-status.is-complete').filter({ hasText: 'Đã tải ảnh đại diện lên.' }).waitFor();
  check('actual avatar upload completed and attached', true);
  check('simulated avatar upload recovered without duplicate file creation', requests.filter((item) => item.method === 'POST' && item.path === '/api/v1/files' && item.status === 201).length === 1);
  await tutor.unroute('**/api/v1/files/*/complete');
  const documentStep = tutor.locator('.onboarding-stepper').getByRole('button', { name: /Tài liệu xác minh/ });
  if (await documentStep.isEnabled()) await documentStep.click();
  else {
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('heading', { name: 'Tài liệu xác minh' }).waitFor();
  }
  const pdf = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF');
  await tutor.locator('#tutor-verification_document-file').setInputFiles({ name: 'issue22-certificate.pdf', mimeType: 'application/pdf', buffer: pdf });
  await tutor.locator('.onboarding-upload-status.is-complete').filter({ hasText: 'Đã tải tài liệu xác minh lên.' }).waitFor();
  check('actual PDF upload completed and attached', true);
  const uploadPaths = requests.filter((item) => item.path.includes('/files') || item.path.includes('verification-documents') || item.path.includes('/avatar'));
  check('upload calls reached real API with expected successes', uploadPaths.some((item) => item.path.endsWith('/files') && item.status === 201) && uploadPaths.some((item) => item.path.endsWith('/complete') && item.status === 200) && uploadPaths.some((item) => item.path.endsWith('/avatar') && item.status === 200) && uploadPaths.some((item) => item.path.endsWith('/verification-documents') && item.status === 201), JSON.stringify(uploadPaths));
  await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
  await tutor.getByRole('heading', { name: 'Xem lại hồ sơ' }).waitFor();
  const submitsBeforeExplicitAction = requests.filter((item) => item.method === 'POST' && item.path.endsWith('/tutor/profile/submit'));
  const profileBeforeExplicitAction = await tutor.evaluate(async () => {
    const response = await fetch('http://localhost:8000/api/v1/tutor/profile', { credentials: 'include', headers: { Accept: 'application/json' } });
    const payload = await response.json();
    const profile = payload.data?.profile ?? payload.data ?? payload.profile ?? payload;
    return { status: profile.status ?? null, submittedAt: Boolean(profile.submitted_at) };
  });
  check('step 4 to review does not submit profile', submitsBeforeExplicitAction.length === 0 && profileBeforeExplicitAction.status === 'draft' && !profileBeforeExplicitAction.submittedAt, JSON.stringify({ requestCount: submitsBeforeExplicitAction.length, profileBeforeExplicitAction }));
  if (submitsBeforeExplicitAction.length || profileBeforeExplicitAction.status !== 'draft' || profileBeforeExplicitAction.submittedAt) throw new Error('Advancing to the review step submitted the profile before explicit submit');
  await takeScreenshot(tutor, 'tutor-step-5-desktop.png');
  const submitBtn = tutor.locator('.onboarding-actions button[type="submit"]');
  const submitEnabledBefore = await submitBtn.isEnabled();
  const submitStateBefore = await submitBtn.evaluate((button) => ({ disabled: button.disabled, label: button.innerText.trim() }));
  const verificationDiagnostic = await tutor.evaluate(async () => {
    const response = await fetch('http://localhost:8000/api/v1/me', { credentials: 'include', headers: { Accept: 'application/json' } });
    const payload = await response.json();
    const user = payload.data?.user ?? payload.data ?? payload.user ?? payload;
    const profileResponse = await fetch('http://localhost:8000/api/v1/tutor/profile', { credentials: 'include', headers: { Accept: 'application/json' } });
    const profilePayload = await profileResponse.json();
    const profile = profilePayload.data?.profile ?? profilePayload.data ?? profilePayload.profile ?? profilePayload;
    return { meStatus: response.status, emailVerified: Boolean(user.email_verified_at), verificationNoteVisible: Boolean(document.querySelector('.onboarding-verification-note')), profileStatus: profile.status ?? null, profileHasSubmittedAt: Boolean(profile.submitted_at), profileHasSuspendedAt: Boolean(profile.suspended_at), profileHasAvatar: Boolean(profile.avatar_file_id), profileHasDocument: Boolean(profile.verification_document_file_id), document: { status: profileResponse.status, payloadKeys: Object.keys(profilePayload) }, headlineDisabled: document.querySelector('#tutor-headline')?.disabled ?? null, uploadStatuses: [...document.querySelectorAll('.onboarding-upload-status')].map((node) => ({ text: node.textContent?.trim(), className: node.className })), alerts: [...document.querySelectorAll('[role="alert"]')].map((node) => node.textContent?.trim()) };
  });
  check('submit enabled before first submit', submitEnabledBefore, JSON.stringify({ ...submitStateBefore, ...verificationDiagnostic }));
  if (!submitEnabledBefore) throw new Error(`Review submit button disabled before first submission (${JSON.stringify({ ...submitStateBefore, ...verificationDiagnostic })})`);
  let allowSubmit;
  let markSubmitStarted;
  const submitStarted = new Promise((resolve) => { markSubmitStarted = resolve; });
  const submitGate = new Promise((resolve) => { allowSubmit = resolve; });
  await tutor.route('**/api/v1/tutor/profile/submit', async (route) => {
    markSubmitStarted();
    await submitGate;
    await route.continue();
  });
  await submitBtn.evaluate((button) => { button.click(); button.click(); });
  const submitReachedBackend = await Promise.race([
    submitStarted.then(() => true),
    new Promise((resolve) => setTimeout(() => resolve(false), 15000)),
  ]);
  check('submit reached real backend once', submitReachedBackend);
  if (!submitReachedBackend) throw new Error(`Submit request did not start; state=${JSON.stringify(await submitBtn.evaluate((button) => ({ disabled: button.disabled, label: button.innerText.trim() })))}; headings=${JSON.stringify(await tutor.locator('h1,h2,h3').allTextContents())}`);
  await tutor.waitForTimeout(50);
  check('submit button disabled while real API request is pending', await submitBtn.isDisabled());
  allowSubmit();
  await tutor.getByRole('status').filter({ hasText: 'Hồ sơ đã được gửi để xét duyệt' }).waitFor();
  await tutor.unroute('**/api/v1/tutor/profile/submit');
  check('tutor profile submitted', true);
  const profileSubmitCount = requests.filter((item) => item.method === 'POST' && item.path.endsWith('/tutor/profile/submit') && item.status === 200).length;
  check('double click produced one profile submit request', profileSubmitCount === 1, `count=${profileSubmitCount}`);
  await tutor.locator('.onboarding-stepper').getByRole('button', { name: /Thông tin cơ bản/ }).click();
  check('submitted headline locked', await tutor.locator('#tutor-headline').isDisabled());
  check('submitted avatar input locked', await tutor.locator('#tutor-avatar-file').isDisabled());
  await tutor.reload({ waitUntil: 'networkidle' });
  check('submitted profile remains locked after refresh', await tutor.locator('#tutor-headline').isDisabled());

  // Read profile identity from the authenticated API for the isolated admin review request.
  const profilePayload = await tutor.evaluate(async () => { const response = await fetch('http://localhost:8000/api/v1/tutor/profile', { credentials: 'include', headers: { Accept: 'application/json' } }); return { status: response.status, body: await response.json() }; });
  const profileId = profilePayload.body.data?.id ?? profilePayload.body.id;
  check('submitted tutor profile API visible', profilePayload.status === 200 && Number.isInteger(profileId), String(profilePayload.status));

  // Admin fixture is created by the caller through the dedicated, documented CLI setup below.
  if (process.env.ISSUE22_ADMIN_PASSWORD) {
    const adminContext = await browser.newContext();
    watch(adminContext);
    const admin = await adminContext.newPage();
    await login(admin, adminEmail, process.env.ISSUE22_ADMIN_PASSWORD);
    await admin.waitForURL(/\/admin/);
    const adminCookies = await adminContext.cookies();
    const adminCsrf = adminCookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value;
    const reviewStatus = await admin.evaluate(async ({ profileId, csrf }) => {
      const response = await fetch(`http://localhost:8000/api/v1/admin/tutor-profiles/${profileId}/request-changes`, { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf ?? '') }, body: JSON.stringify({ reason: 'Synthetic issue #22 review: please refine the profile headline.' }) });
      return response.status;
    }, { profileId, csrf: adminCsrf });
    check('synthetic admin request-changes API returned 200', reviewStatus === 200, String(reviewStatus));
    await tutor.reload({ waitUntil: 'networkidle' });
    check('changes-requested review reason visible', await tutor.getByRole('status').filter({ hasText: 'Synthetic issue #22 review' }).count() === 1);
    check('changes-requested profile unlocked', !(await tutor.locator('#tutor-headline').isDisabled()));
    await tutor.locator('#tutor-headline').fill('Issue 22 revised headline after review');
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('heading', { name: 'Tài liệu xác minh' }).waitFor();
    await tutor.getByRole('button', { name: 'Tiếp theo' }).click();
    await tutor.getByRole('heading', { name: 'Xem lại hồ sơ' }).waitFor();
    await tutor.getByRole('button', { name: /Gửi lại xét duyệt/ }).click();
    await tutor.getByRole('status').filter({ hasText: 'Hồ sơ đã được gửi để xét duyệt' }).waitFor();
    check('changes-requested tutor can edit and resubmit', true);
    await adminContext.close();
  } else {
    check('changes-requested lifecycle', false, 'ISSUE22_ADMIN_PASSWORD fixture not configured');
  }
  for (const viewport of [
    { width: 360, height: 800, label: 'mobile' },
    { width: 768, height: 1000, label: 'tablet' },
    { width: 1536, height: 1000, label: 'desktop-final' },
  ]) {
    await tutor.setViewportSize({ width: viewport.width, height: viewport.height });
    await tutor.goto(`${base}/profile`, { waitUntil: 'networkidle' });
    await tutor.locator('.onboarding-stepper').waitFor();
    if (viewport.label === 'mobile') {
      const toggle = tutor.locator('.menu-toggle');
      await toggle.click();
      const expanded = await toggle.getAttribute('aria-expanded');
      check('mobile authenticated menu expands', expanded === 'true', expanded ?? 'missing');
      check('mobile sidebar becomes visible', await tutor.locator('#app-sidebar').isVisible());
      await toggle.click();
    }
    for (const [number, stepLabel] of [[1, /Thông tin cơ bản/], [2, /Chuyên môn/], [3, /Kinh nghiệm/], [4, /Tài liệu xác minh/], [5, /Xem lại/]]) {
      await tutor.locator('.onboarding-stepper').getByRole('button', { name: stepLabel }).click();
      await tutor.locator(`.onboarding-step[aria-current="step"], .onboarding-step.is-current`).waitFor();
      await takeScreenshot(tutor, `tutor-step-${number}-${viewport.label}.png`);
      const metrics = await tutor.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index),
        unlabeledControls: [...document.querySelectorAll('input:not([type="hidden"]),textarea,select')].filter((element) => !(element.labels?.length) && !element.getAttribute('aria-label')).length,
      }));
      check(`${viewport.label} onboarding step ${number} no horizontal overflow`, metrics.scrollWidth <= metrics.width, `${metrics.scrollWidth}/${metrics.width}`);
      check(`${viewport.label} onboarding step ${number} unique IDs`, metrics.duplicateIds.length === 0, metrics.duplicateIds.join(', '));
      check(`${viewport.label} onboarding step ${number} controls labeled`, metrics.unlabeledControls === 0, `${metrics.unlabeledControls} unlabeled`);
    }
  }
  await tutorContext.close();
} catch (error) {
  failures.push(`fatal: ${error instanceof Error ? error.message : String(error)}`);
} finally {
  await browser.close();
}

const report = {
  generatedAt: new Date().toISOString(),
  checks,
  requestSummary: requests,
  failures,
};
await fs.writeFile(path.join(out, 'api-browser-report.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify({ checks: checks.length, failures: failures.length, output: path.join(out, 'api-browser-report.json'), failures }, null, 2));
process.exitCode = failures.length ? 1 : 0;
