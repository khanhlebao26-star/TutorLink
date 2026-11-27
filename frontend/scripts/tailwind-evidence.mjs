import { chromium } from "playwright";
import { mkdir } from "node:fs/promises";
import path from "node:path";

const mode = process.argv[2];
if (mode !== "Before" && mode !== "After") {
  throw new Error("Usage: node scripts/tailwind-evidence.mjs Before|After");
}
const baseUrl = process.env.TAILWIND_EVIDENCE_URL ?? "http://localhost:3000";
const authBaseUrl = process.env.TAILWIND_AUTH_EVIDENCE_URL ?? baseUrl;
const outputDir = process.env.TAILWIND_EVIDENCE_OUTPUT
  ? path.resolve(process.env.TAILWIND_EVIDENCE_OUTPUT, mode)
  : path.resolve(`../docs/handover/evidence/3a-tailwind-migration/${mode}`);
const routes = [
  ["home", "/"],
  ["marketplace", "/marketplace"],
  ["tutor-profile", "/tutors/vo-minh-quan-24"],
  ["about", "/about"],
  ["support", "/support"],
  ["become-a-tutor", "/become-a-tutor"],
  ["login", "/auth/login"],
];
const viewports = [["mobile", 390], ["tablet", 768], ["desktop", 1440]];
const browserPath = process.env.TUTORLINK_CHROME_PATH ?? "/usr/bin/google-chrome";
const demoEmail = process.env.TAILWIND_DEMO_EMAIL;
const demoPassword = process.env.TAILWIND_DEMO_PASSWORD;
const apiOrigin = process.env.TAILWIND_API_ORIGIN;
await mkdir(outputDir, { recursive: true });
const browserArgs = ["--no-sandbox"];
if (process.env.TAILWIND_DISABLE_WEB_SECURITY === "true") browserArgs.push("--disable-web-security");
const browser = await chromium.launch({ headless: true, executablePath: browserPath, args: browserArgs });
const failures = [];
try {
  for (const [name, route] of routes) {
    for (const [size, width] of viewports) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, deviceScaleFactor: 1, reducedMotion: "reduce" });
      const pageErrors = [];
      if (apiOrigin) await page.setExtraHTTPHeaders({ origin: apiOrigin, referer: `${apiOrigin}/` });
      page.on("pageerror", (error) => pageErrors.push(error.message));
      const response = await page.goto(new URL(route, baseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      if (route === "/marketplace") {
        await page.waitForFunction(() => document.querySelector(".market-results")?.getAttribute("aria-busy") === "false", { timeout: 15_000 });
      }
      await page.waitForTimeout(400);
      const layout = await page.evaluate(() => {
        const text = document.body.innerText;
        return {
          viewport: innerWidth,
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth,
          apiError: text.includes("Network Error") || text.includes("Không thể tải dữ liệu"),
        };
      });
      const screenshot = path.join(outputDir, `${name}-${size}.png`);
      await page.screenshot({ path: screenshot, fullPage: true, animations: "disabled" });
      const overflow = layout.document > width || layout.body > width;
      console.log(`${name}-${size}: status=${response.status()} overflow=${overflow} apiError=${layout.apiError} pageErrors=${pageErrors.length} file=${screenshot}`);
      if (response.status() !== 200 || overflow || layout.apiError || pageErrors.length) failures.push(`${name}-${size}`);
      if (route.startsWith("/tutors/")) {
        await page.getByRole("tab", { name: "Lịch học" }).click();
        await page.waitForTimeout(300);
        const scheduleLayout = await page.evaluate(() => ({
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth,
          apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
        }));
        const scheduleScreenshot = path.join(outputDir, `tutor-schedule-${size}.png`);
        await page.screenshot({ path: scheduleScreenshot, fullPage: true, animations: "disabled" });
        const scheduleOverflow = scheduleLayout.document > width || scheduleLayout.body > width;
        console.log(`tutor-schedule-${size}: status=${response.status()} overflow=${scheduleOverflow} apiError=${scheduleLayout.apiError} pageErrors=${pageErrors.length} file=${scheduleScreenshot}`);
        if (response.status() !== 200 || scheduleOverflow || scheduleLayout.apiError || pageErrors.length) failures.push(`tutor-schedule-${size}`);
      }
      await page.close();
    }
  }
  if (demoEmail && demoPassword) {
    for (const [size, width] of viewports) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, deviceScaleFactor: 1, reducedMotion: "reduce" });
      const pageErrors = [];
      if (apiOrigin) await page.setExtraHTTPHeaders({ origin: apiOrigin, referer: `${apiOrigin}/` });
      page.on("pageerror", (error) => pageErrors.push(error.message));
      await page.goto(new URL("/auth/login", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.locator("#email").fill(demoEmail);
      await page.locator("#password").fill(demoPassword);
      const loginResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/auth/login"), { timeout: 15_000 });
      await page.getByRole("button", { name: "Đăng nhập" }).click();
      const login = await loginResponse;
      if (login.status() !== 200) failures.push(`tutor-login-${size}`);
      const response = await page.goto(new URL("/profile", authBaseUrl).toString(), { waitUntil: "domcontentloaded", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".onboarding-stepper").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(400);
      const layout = await page.evaluate(() => {
        const text = document.body.innerText;
        return {
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth,
          apiError: text.includes("Network Error") || text.includes("Không thể tải dữ liệu"),
          onboarding: Boolean(document.querySelector(".onboarding-stepper")),
        };
      });
      const screenshot = path.join(outputDir, `tutor-onboarding-${size}.png`);
      await page.screenshot({ path: screenshot, fullPage: true, animations: "disabled" });
      const overflow = layout.document > width || layout.body > width;
      console.log(`tutor-onboarding-${size}: status=${response.status()} overflow=${overflow} apiError=${layout.apiError} pageErrors=${pageErrors.length} file=${screenshot}`);
      if (response.status() !== 200 || overflow || layout.apiError || !layout.onboarding || pageErrors.length) failures.push(`tutor-onboarding-${size}`);

      const availabilityResponse = await page.goto(new URL("/me/availability", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".availability-page").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(300);
      const availabilityLayout = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
        contentWidth: document.querySelector(".availability-page")?.getBoundingClientRect().width ?? 0,
        apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
      }));
      const availabilityScreenshot = path.join(outputDir, `availability-${size}.png`);
      await page.screenshot({ path: availabilityScreenshot, fullPage: true, animations: "disabled" });
      const availabilityOverflow = availabilityLayout.document > width || availabilityLayout.body > width || availabilityLayout.contentWidth > width;
      console.log(`availability-${size}: status=${availabilityResponse.status()} overflow=${availabilityOverflow} apiError=${availabilityLayout.apiError} pageErrors=${pageErrors.length} file=${availabilityScreenshot}`);
      if (availabilityResponse.status() !== 200 || availabilityOverflow || availabilityLayout.apiError || pageErrors.length) failures.push(`availability-${size}`);

      const messagesResponse = await page.goto(new URL("/messages", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".messaging-page").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(300);
      const messagesLayout = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
        apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
      }));
      const messagesScreenshot = path.join(outputDir, `messages-${size}.png`);
      await page.screenshot({ path: messagesScreenshot, fullPage: true, animations: "disabled" });
      const messagesOverflow = messagesLayout.document > width || messagesLayout.body > width;
      console.log(`messages-${size}: status=${messagesResponse.status()} overflow=${messagesOverflow} apiError=${messagesLayout.apiError} pageErrors=${pageErrors.length} file=${messagesScreenshot}`);
      if (messagesResponse.status() !== 200 || messagesOverflow || messagesLayout.apiError || pageErrors.length) failures.push(`messages-${size}`);

      const listingsResponse = await page.goto(new URL("/me/listings", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".owner-service-dashboard").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(300);
      const listingsLayout = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
        apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
      }));
      const listingsScreenshot = path.join(outputDir, `owner-listings-${size}.png`);
      await page.screenshot({ path: listingsScreenshot, fullPage: true, animations: "disabled" });
      const listingsOverflow = listingsLayout.document > width || listingsLayout.body > width;
      console.log(`owner-listings-${size}: status=${listingsResponse.status()} overflow=${listingsOverflow} apiError=${listingsLayout.apiError} pageErrors=${pageErrors.length} file=${listingsScreenshot}`);
      if (listingsResponse.status() !== 200 || listingsOverflow || listingsLayout.apiError || pageErrors.length) failures.push(`owner-listings-${size}`);

      const programResponse = await page.goto(new URL("/programs/new", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".program-builder").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(300);
      const programLayout = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
        apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
      }));
      const programScreenshot = path.join(outputDir, `program-builder-${size}.png`);
      await page.screenshot({ path: programScreenshot, fullPage: true, animations: "disabled" });
      const programOverflow = programLayout.document > width || programLayout.body > width;
      console.log(`program-builder-${size}: status=${programResponse.status()} overflow=${programOverflow} apiError=${programLayout.apiError} pageErrors=${pageErrors.length} file=${programScreenshot}`);
      if (programResponse.status() !== 200 || programOverflow || programLayout.apiError || pageErrors.length) failures.push(`program-builder-${size}`);

      const reportsResponse = await page.goto(new URL("/reports", authBaseUrl).toString(), { waitUntil: "networkidle", timeout: 60_000 });
      await page.evaluate(() => document.fonts.ready);
      await page.locator(".reports-page").waitFor({ timeout: 15_000 });
      await page.waitForTimeout(300);
      const reportsLayout = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
        apiError: document.body.innerText.includes("Network Error") || document.body.innerText.includes("Không thể tải dữ liệu"),
      }));
      const reportsScreenshot = path.join(outputDir, `reports-${size}.png`);
      await page.screenshot({ path: reportsScreenshot, fullPage: true, animations: "disabled" });
      const reportsOverflow = reportsLayout.document > width || reportsLayout.body > width;
      console.log(`reports-${size}: status=${reportsResponse.status()} overflow=${reportsOverflow} apiError=${reportsLayout.apiError} pageErrors=${pageErrors.length} file=${reportsScreenshot}`);
      if (reportsResponse.status() !== 200 || reportsOverflow || reportsLayout.apiError || pageErrors.length) failures.push(`reports-${size}`);
      await page.close();
    }
  }
} finally {
  await browser.close();
}
if (failures.length) {
  console.error(`Evidence checks failed: ${failures.join(", ")}`);
  process.exitCode = 1;
}
