import { test, expect, type Page } from "@playwright/test";
const mailpit = process.env.E2E_MAILPIT_URL;
if (!mailpit)
  throw new Error(
    "Set E2E_MAILPIT_URL to your isolated Mailpit HTTP server. Never use a real mailbox for E2E.",
  );
async function emailLink(page: Page, email: string, path: string) {
  let text = "";
  await expect
    .poll(
      async () => {
        const response = await page.request.get(`${mailpit}/view/latest.txt`, {
          params: { query: `to:${email}` },
        });
        text = response.ok() ? await response.text() : "";
        return text.includes(path);
      },
      { timeout: 20000 },
    )
    .toBe(true);
  const links = text.match(/https?:\/\/[^\s<>\")]+/g) ?? [];
  const url = links
    .map((value) => new URL(value.replaceAll("&amp;", "&")))
    .find((value) => value.pathname === path);
  expect(url, "Mailpit must contain the real signed/reset URL").toBeTruthy();
  expect(url!.origin).toBe(new URL(process.env.E2E_BASE_URL!).origin);
  return url!.href;
}
test("register -> signed email verification -> login -> password reset", async ({
  page,
}) => {
  const email = `browser-${Date.now()}@example.test`,
    password = "Demo-only-password-2026",
    nextPassword = "Demo-only-new-password-2026";
  await page.goto("/auth/register");
  await page.getByLabel("Họ và tên", { exact: true }).fill("Browser customer");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Ngày sinh", { exact: true }).fill("1995-01-15");
  await page.getByLabel("Mật khẩu", { exact: true }).fill(password);
  await page.getByLabel("Xác nhận mật khẩu", { exact: true }).fill(password);
  await page
    .getByRole("button", { name: "Tạo tài khoản", exact: true })
    .click();
  await expect(page.getByText(/Đăng ký thành công/)).toBeVisible();
  await page.goto(await emailLink(page, email, "/auth/verify-email"));
  await expect(
    page.getByText("Email đã được xác minh. Bạn có thể đăng nhập."),
  ).toBeVisible();
  await page.goto("/auth/login");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Mật khẩu", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Đăng nhập", exact: true }).click();
  await expect(page).not.toHaveURL(/auth\/login/);
  await page.goto("/auth/forgot-password");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByRole("button", { name: /Gửi/ }).click();
  await page.goto(await emailLink(page, email, "/auth/reset-password"));
  await page.getByLabel("Mật khẩu mới", { exact: true }).fill(nextPassword);
  await page
    .getByLabel("Xác nhận mật khẩu", { exact: true })
    .fill(nextPassword);
  await page.getByRole("button", { name: /Đặt lại|Cập nhật/ }).click();
  await expect(page).toHaveURL(/auth\/login/);
});
