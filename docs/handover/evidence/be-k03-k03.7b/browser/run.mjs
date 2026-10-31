import assert from 'node:assert/strict';
import { randomUUID, createHash } from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'file:///C:/Users/khanh/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const out = path.dirname(fileURLToPath(import.meta.url));
const results = { started_at: new Date().toISOString(), checks: [], ui: [], api: [], screenshots: [], created_file_ids: [], cleaned_file_ids: [] };
const stamp = randomUUID();
const email = `k037b.${stamp}@example.test`;
const password = `K037b!${randomUUID()}`;
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jAZkAAAAASUVORK5CYII=', 'base64');
const pdf = Buffer.from('%PDF-1.4\nSynthetic K03.7b size fixture.\n%%EOF');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
const page = await context.newPage();
page.setDefaultTimeout(20000);
let uploadRequests = 0;
page.on('request', req => {
  if (req.method() === 'POST' && new URL(req.url()).pathname === '/api/v1/files') uploadRequests++;
});
function check(name, condition, details = undefined) {
  results.checks.push({ name, passed: Boolean(condition), ...(details === undefined ? {} : { details }) });
  console.log(`${condition ? 'PASS' : 'FAIL'} ${name}`);
  assert.ok(condition, name);
}
function bytesFor(purpose, size) {
  const bytes = Buffer.alloc(size, ' ');
  const prefix = purpose === 'avatar' ? png : pdf;
  prefix.copy(bytes, 0, 0, Math.min(prefix.length, size));
  return bytes;
}
async function shot(name) {
  await page.screenshot({ path: path.join(out, name), fullPage: true });
  results.screenshots.push(name);
}
async function request(endpoint, method = 'GET', data = undefined) {
  return page.evaluate(async ({ endpoint, method, data }) => {
    const token = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
    const res = await fetch(`http://localhost:8000${endpoint}`, {
      method, credentials: 'include',
      headers: { Accept: 'application/json', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}), ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
      ...(data === undefined ? {} : { body: JSON.stringify(data) }),
    });
    const text = await res.text();
    return { status: res.status, body: text ? JSON.parse(text) : null };
  }, { endpoint, method, data });
}
async function uiCase(purpose, size) {
  const extension = purpose === 'avatar' ? 'png' : 'pdf';
  const mimeType = purpose === 'avatar' ? 'image/png' : 'application/pdf';
  const input = page.locator(`#tutor-${purpose}-file`);
  const before = uploadRequests;
  const valid = size > 0 && size <= 5_000_000;
  const name = `${purpose}-${size}.${extension}`;
  const attachmentPath = purpose === 'avatar' ? '/api/v1/tutor/profile/avatar' : '/api/v1/tutor/profile/verification-documents';
  const responsePromises = valid ? [
    page.waitForResponse(res => res.request().method() === 'POST' && new URL(res.url()).pathname === '/api/v1/files'),
    page.waitForResponse(res => res.request().method() === 'POST' && /\/api\/v1\/files\/\d+\/complete$/.test(new URL(res.url()).pathname)),
    page.waitForResponse(res => new URL(res.url()).pathname === attachmentPath && ['POST', 'PUT'].includes(res.request().method())),
  ] : [];
  await input.setInputFiles({ name, mimeType, buffer: bytesFor(purpose, size) });
  if (valid) {
    const [upload, complete, attachment] = await Promise.all(responsePromises);
    const uploadBody = await upload.json();
    const completeBody = await complete.json();
    results.created_file_ids.push(uploadBody.data?.id);
    results.ui.push({ purpose, size, upload: upload.status(), complete: complete.status(), attachment: attachment.status(), file_id: uploadBody.data?.id, antivirus_scanned: completeBody.data?.antivirus_scanned, scanned_at: completeBody.data?.scanned_at });
    check(`UI ${purpose} ${size}: upload/complete/attach`, upload.status() === 201 && complete.status() === 200 && attachment.ok());
    check(`UI ${purpose} ${size}: exact bytes and no fabricated scan`, uploadBody.data.size_bytes === size && completeBody.data.complete === true && completeBody.data.antivirus_scanned === false && completeBody.data.scanned_at === null);
    await page.waitForFunction(id => !document.getElementById(id)?.disabled, `tutor-${purpose}-file`);
    await page.getByRole('status').filter({ hasText: purpose === 'avatar' ? 'Đã tải ảnh đại diện lên.' : 'Đã tải tài liệu xác minh lên.' }).first().waitFor();
  } else {
    const error = page.locator(`#tutor-${purpose}-error`);
    await error.waitFor({ state: 'visible' });
    await page.waitForTimeout(250);
    const message = await error.innerText();
    results.ui.push({ purpose, size, blocked_in_browser: uploadRequests === before, error: message });
    check(`UI ${purpose} ${size}: rejected before upload`, uploadRequests === before && message.includes('5 MB'));
  }
  const uploadText = (await page.locator('.onboarding-upload-card').allTextContents()).join('\n');
  check(`UI ${purpose} ${size}: no antivirus-success label`, !/đã\s+(?:antivirus|quét\s+(?:virus|malware))/i.test(uploadText));
  await shot(`ui-${purpose}-${size}.png`);
}

try {
  await page.goto('http://localhost:3000', { waitUntil: 'networkidle' });
  const csrf = await request('/sanctum/csrf-cookie');
  check('CSRF bootstrap', csrf.status === 204);
  const register = await request('/api/v1/auth/register', 'POST', { full_name: 'K03.7b Synthetic Tutor', date_of_birth: '1990-01-02', email, password, password_confirmation: password, role: 'tutor' });
  check('Create isolated local test tutor', register.status === 201, register.status);
  results.test_user_id = register.body?.data?.id;
  let verifyUrl;
  for (let attempt = 0; attempt < 35 && !verifyUrl; attempt++) {
    const response = await context.request.get('http://localhost:8025/api/v1/messages?start=0&limit=100');
    const messages = (await response.json()).messages ?? [];
    const message = messages.find(item => (item.To ?? []).some(to => to.Address === email));
    if (message) {
      const detailResponse = await context.request.get(`http://localhost:8025/api/v1/message/${encodeURIComponent(message.ID)}`);
      const detail = await detailResponse.json();
      const content = `${detail.Text ?? ''}\n${detail.HTML ?? ''}`;
      verifyUrl = [...content.matchAll(/https?:[^\s"'<>]+/g)].map(match => match[0].replaceAll('&amp;', '&')).find(url => url.includes('/auth/verify-email?'));
    }
    if (!verifyUrl) await page.waitForTimeout(400);
  }
  check('Local Mailpit verification received', Boolean(verifyUrl));
  check('Verification link stays on local frontend', new URL(verifyUrl).origin === 'http://localhost:3000');
  await page.goto(verifyUrl, { waitUntil: 'networkidle' });
  await page.getByRole('status').filter({ hasText: 'Email đã được xác minh' }).waitFor();
  await page.goto('http://localhost:3000/auth/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  const loginResponse = page.waitForResponse(res => new URL(res.url()).pathname === '/api/v1/auth/login');
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
  check('Browser session login', (await loginResponse).status() === 200);
  await page.waitForURL(/\/profile/);
  const specs = await request('/api/v1/catalog/specializations');
  const specializations = specs.body?.data;
  const spec = Array.isArray(specializations) ? specializations.find(item => item.status === undefined || item.status === 'active') : undefined;
  check('Active specialization available', Boolean(spec));
  const profile = await request('/api/v1/tutor/profile', 'PUT', { headline: 'K03.7b synthetic file-size test', bio: 'Synthetic local test account for upload boundary checks.', experience_years: 1, specialization_id: spec.id });
  check('Create editable draft for local test', [200, 201].includes(profile.status), profile.status);
  await page.reload({ waitUntil: 'networkidle' });
  await page.locator('#tutor-avatar-file').waitFor({ state: 'attached' });
  check('Browser serves current 5 MB label after frontend restart', (await page.locator('.onboarding-upload-card').innerText()).includes('5.000.000 byte'));
  for (const size of [4_999_999, 5_000_000, 5_000_001, 0]) await uiCase('avatar', size);
  for (let step = 0; step < 3; step++) await page.getByRole('button', { name: 'Tiếp theo', exact: true }).click();
  await page.locator('#tutor-verification_document-file').waitFor({ state: 'attached' });
  await page.getByLabel('Loại tài liệu', { exact: true }).fill('other');
  for (const size of [4_999_999, 5_000_000, 5_000_001, 0]) await uiCase('verification_document', size);

  for (const purpose of ['avatar', 'verification_document', 'task_attachment', 'profile_document']) {
    for (const size of [4_999_999, 5_000_000, 5_000_001, 0]) {
      const bytes = bytesFor(purpose, size);
      const result = await page.evaluate(async ({ purpose, data, name, type }) => {
        const form = new FormData();
        form.append('purpose', purpose);
        form.append('file', new File([Uint8Array.from(atob(data), char => char.charCodeAt(0))], name, { type }));
        const token = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
        const res = await fetch('http://localhost:8000/api/v1/files', { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token) }, body: form });
        return { status: res.status, body: await res.json() };
      }, { purpose, data: bytes.toString('base64'), name: `api-${purpose}-${size}.${purpose === 'avatar' ? 'png' : 'pdf'}`, type: purpose === 'avatar' ? 'image/png' : 'application/pdf' });
      const record = { purpose, size, upload: result.status };
      results.api.push(record);
      if (size === 0 || size > 5_000_000) {
        check(`API ${purpose} ${size}: rejects direct multipart`, result.status === 422 && Boolean(result.body.errors?.file), { status: result.status });
      } else {
        check(`API ${purpose} ${size}: accepts exact boundary`, result.status === 201 && result.body.data?.size_bytes === size, { status: result.status });
        const id = result.body.data.id;
        results.created_file_ids.push(id);
        record.file_id = id;
        const complete = await request(`/api/v1/files/${id}/complete`, 'POST');
        record.complete = complete.status;
        record.antivirus_scanned = complete.body?.data?.antivirus_scanned;
        record.scanned_at = complete.body?.data?.scanned_at;
        check(`API ${purpose} ${size}: complete without fabricated scan`, complete.status === 200 && complete.body.data.complete === true && record.antivirus_scanned === false && record.scanned_at === null);
        const downloaded = await context.request.get(`http://localhost:8000/api/v1/files/${id}/download`, { headers: { Origin: 'http://localhost:3000', Referer: 'http://localhost:3000/profile' } });
        record.download = downloaded.status();
        record.download_matches = downloaded.status() === 200 && createHash('sha256').update(await downloaded.body()).digest('hex') === createHash('sha256').update(bytes).digest('hex');
        check(`API ${purpose} ${size}: owner downloads same bytes`, record.download_matches, { status: record.download });
        const removed = await request(`/api/v1/files/${id}`, 'DELETE');
        check(`API ${purpose} ${size}: cleanup unlinked fixture`, removed.status === 204);
        if (removed.status === 204) results.cleaned_file_ids.push(id);
      }
    }
  }
  await request('/api/v1/auth/logout', 'POST');
  results.status = 'PASS';
} catch (error) {
  results.status = 'FAIL';
  results.error = error.message;
  await shot('failure.png').catch(() => {});
  process.exitCode = 1;
} finally {
  results.finished_at = new Date().toISOString();
  results.summary = { passed: results.checks.filter(item => item.passed).length, failed: results.checks.filter(item => !item.passed).length, ui_cases: results.ui.length, api_cases: results.api.length };
  results.fixture_note = 'Dedicated synthetic local tutor and linked UI uploads remain as test evidence; direct API uploads were removed after successful checks. No personal account used. Credentials and verification URLs are not recorded.';
  await fs.writeFile(path.join(out, 'results.json'), JSON.stringify(results, null, 2));
  console.log(JSON.stringify({ status: results.status, summary: results.summary, error: results.error, test_user_id: results.test_user_id, output: out }, null, 2));
  await browser.close();
}
