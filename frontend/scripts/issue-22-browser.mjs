import fs from 'node:fs/promises';
import path from 'node:path';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');

const base = process.env.TUTORLINK_URL ?? 'http://localhost:3000';
const out = path.resolve('outputs/issue-22-figma-layout');
const original = path.resolve('docs/FE-figmaDesign');
const routes = [
  ['/auth/login', 'Login page.png'],
  ['/auth/register', 'Register page.png'],
  ['/auth/forgot-password', 'Forgot password page.png'],
  ['/auth/reset-password', 'Reset password.png'],
  ['/auth/verify-email', 'Verify Password.png'],
];
const onboardingReferences = [
  [1, 'Tutor Dashboard-thongtincoban.png'],
  [2, 'Tutor Dashboard-chuyenmon.png'],
  [3, 'Tutor Dashboard-kinhnghiem.png'],
  [4, 'Tutor Dashboard-tailieuxacminh.png'],
  [5, 'Tutor Dashboard-xemlai.png'],
];
const findings = [];
const requests = [];
const checks = [];
await fs.mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.TUTORLINK_CHROME_PATH ? { executablePath: process.env.TUTORLINK_CHROME_PATH } : {}), args: ['--no-sandbox'] });

function record(name, pass, detail = '') {
  checks.push({ name, result: pass ? 'PASS' : 'FAIL', detail });
  if (!pass) findings.push(`${name}${detail ? `: ${detail}` : ''}`);
}

try {
  for (const viewport of [{ width: 1536, height: 1000, label: 'desktop' }, { width: 768, height: 1000, label: 'tablet' }, { width: 360, height: 800, label: 'mobile' }]) {
    const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    page.on('request', (req) => {
      if (new URL(req.url()).origin === base) requests.push({ method: req.method(), path: new URL(req.url()).pathname, status: null });
    });
    page.on('response', (res) => {
      const req = res.request();
      if (new URL(req.url()).origin === base) {
        const found = [...requests].reverse().find((item) => item.path === new URL(req.url()).pathname && item.method === req.method() && item.status === null);
        if (found) found.status = res.status();
      }
    });
    for (const [route, figmaFile] of routes) {
      const response = await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      const font = await page.evaluate(() => ({ family: getComputedStyle(document.body).fontFamily, loaded: document.fonts.check('16px Inter') }));
      record(`${viewport.label} ${route} Inter font loaded`, font.loaded && font.family.startsWith('Inter'), font.family);
      await page.screenshot({ path: path.join(out, `${route.split('/').at(-1)}-${viewport.label}.png`), fullPage: true });
      record(`${viewport.label} ${route} HTTP`, Boolean(response && response.status() < 400), String(response?.status() ?? 'no response'));
      const metrics = await page.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        title: document.querySelector('main h1')?.textContent?.trim() ?? '',
        duplicateIds: [...document.querySelectorAll('[id]')].map((el) => el.id).filter((id, index, ids) => ids.indexOf(id) !== index),
        unnamedInputs: [...document.querySelectorAll('input:not([type="hidden"]),textarea,select')].filter((el) => !(el.labels?.length) && !el.getAttribute('aria-label')).length,
      }));
      record(`${viewport.label} ${route} no horizontal overflow`, metrics.scrollWidth <= metrics.width, `${metrics.scrollWidth}/${metrics.width}`);
      record(`${viewport.label} ${route} unique IDs`, metrics.duplicateIds.length === 0, metrics.duplicateIds.join(', '));
      record(`${viewport.label} ${route} controls labeled`, metrics.unnamedInputs === 0, `${metrics.unnamedInputs} unlabeled`);
      if (viewport.label === 'desktop') {
        const expectedImage = route === '/auth/login' ? 'student-man' : route === '/auth/register' ? 'student-woman' : route === '/auth/verify-email' ? 'verification-envelope' : null;
        if (expectedImage) {
          const image = await page.locator('.auth-illustration-image').evaluate((element) => {
            const rect = element.getBoundingClientRect();
            const url = new URL(element.currentSrc);
            return { complete: element.complete, naturalWidth: element.naturalWidth, naturalHeight: element.naturalHeight, width: Math.round(rect.width), height: Math.round(rect.height), src: url.searchParams.get('url') ?? url.pathname };
          });
          record(`${route} expected illustration loaded in its slot`, image.complete && image.naturalWidth > 0 && image.naturalHeight > 0 && image.width > 0 && image.height > 0 && image.src.includes(expectedImage), JSON.stringify(image));
        }
      }
      if (viewport.label === 'desktop') {
        const originalPath = path.join(original, figmaFile);
        const exists = await fs.access(originalPath).then(() => true).catch(() => false);
        record(`Figma reference exists ${figmaFile}`, exists);
      }
      if (viewport.label === 'mobile') {
        const menu = page.getByRole('button', { name: /Mở điều hướng/ });
        if (await menu.count()) {
          await menu.click();
          record(`mobile ${route} menu expands`, await page.locator('.menu-toggle').getAttribute('aria-expanded') === 'true');
          await page.screenshot({ path: path.join(out, `${route.split('/').at(-1)}-mobile-menu.png`), fullPage: true });
        }
        await page.locator('body').click({ position: { x: 8, y: 8 } });
        await page.keyboard.press('Tab');
        const focused = await page.evaluate(() => {
          const el = document.activeElement;
          return Boolean(el && (el.matches(':focus-visible') || getComputedStyle(el).outlineStyle !== 'none' || getComputedStyle(el).boxShadow !== 'none'));
        });
        record(`mobile ${route} visible keyboard focus`, focused);
      }
    }
    await context.close();
  }
  for (const asset of ['/figma/auth/eye.svg', '/figma/auth/globe.svg', '/figma/auth/verification-mail.svg']) {
    const statuses = requests.filter((request) => request.path === asset).map((request) => request.status);
    record(`${asset} local UI asset loaded`, statuses.length > 0 && statuses.every((status) => status === 200 || status === 304), JSON.stringify(statuses));
  }
} finally {
  await browser.close();
}

const safeRequests = requests.map(({ method, path: requestPath, status }) => ({ method, path: requestPath, status }));
const report = {
  generatedAt: new Date().toISOString(),
  base,
  checks,
  requestSummary: safeRequests,
  findings,
};
await fs.writeFile(path.join(out, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
const authPairs = routes.map(([route, ref]) => {
  const shot = `${route.split('/').at(-1)}-desktop.png`;
  return `<section class="pair"><h2>${route}</h2><div class="imgs"><div><div class="label">Figma reference</div><img src="../../docs/FE-figmaDesign/${encodeURIComponent(ref)}" alt="${ref}"></div><div><div class="label">Browser capture</div><img src="${shot}" alt="${route} screenshot"></div></div></section>`;
});
const onboardingPairs = onboardingReferences.map(([step, ref]) => {
  const shot = `tutor-step-${step}-desktop.png`;
  return `<section class="pair"><h2>Onboarding step ${step}</h2><div class="imgs"><div><div class="label">Figma reference</div><img src="../../docs/FE-figmaDesign/${encodeURIComponent(ref)}" alt="${ref}"></div><div><div class="label">Browser capture</div><img src="${shot}" alt="Onboarding step ${step}"></div></div></section>`;
});
await fs.writeFile(path.join(out, 'comparison.html'), `<!doctype html><meta charset="utf-8"><title>Issue 22 visual comparison</title><style>body{font:14px system-ui;margin:24px;background:#f4f6f8;color:#17202a}h1{font-size:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.pair{background:white;padding:12px;border-radius:8px;box-shadow:0 1px 5px #0002}.pair h2{font-size:16px}.imgs{display:grid;grid-template-columns:1fr 1fr;gap:8px;align-items:start}.imgs img{width:100%;height:auto;border:1px solid #ccd2d8}.label{font-weight:600;margin:8px 0}</style><h1>Issue #22 Figma / browser comparison</h1><p>Figma reference beside the browser capture. Mobile and tablet captures are also in the artifact directory.</p><h2>Authentication</h2><div class="grid">${authPairs.join('')}</div><h2>Tutor onboarding</h2><div class="grid">${onboardingPairs.join('')}</div>`);
console.log(JSON.stringify({ checks: checks.length, failures: findings.length, output: out, findings }, null, 2));
process.exitCode = findings.length ? 1 : 0;
