import { defineConfig, devices } from "@playwright/test";
const baseURL = process.env.E2E_BASE_URL;
const artifacts = process.env.E2E_ARTIFACT_DIR;
if (!baseURL)
  throw new Error("Set E2E_BASE_URL to your isolated frontend runtime.");
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90000,
  outputDir: artifacts ? `${artifacts}/test-results` : "test-results",
  reporter: [
    ["list"],
    ["html", { outputFolder: artifacts ? `${artifacts}/html` : "playwright-report", open: "never" }],
    ["junit", { outputFile: artifacts ? `${artifacts}/e2e.xml` : "test-results/e2e.xml" }],
  ],
  use: {
    baseURL,
    channel: process.env.E2E_BROWSER_CHANNEL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
