import { test, expect, type Page } from "@playwright/test";
const password = process.env.E2E_PASSWORD;
const api = process.env.E2E_API_URL;
if (!password || !api)
  throw new Error(
    "Set E2E_PASSWORD and E2E_API_URL; seed an isolated PostgreSQL database with FrontendDemoSeeder.",
  );
async function login(page: Page, role: "customer" | "tutor" | "admin") {
  await page.goto("/auth/login");
  await page
    .getByLabel("Email", { exact: true })
    .fill(`fe-${role}@example.test`);
  await page.getByLabel("Mật khẩu", { exact: true }).fill(password!);
  await page.getByRole("button", { name: "Đăng nhập", exact: true }).click();
  await expect(page).not.toHaveURL(/auth\/login/);
}
async function data(page: Page, path: string) {
  const response = await page.request.get(
    `${api!.replace(/\/$/, "")}/api/v1${path}`,
    { headers: { Origin: new URL(page.url()).origin, Referer: page.url() } },
  );
  expect(response.ok()).toBeTruthy();
  return response.json();
}
test("real recipient invitation -> sessions -> submission -> feedback -> completed review", async ({
  browser,
}) => {
  const customerContext = await browser.newContext({
      baseURL: process.env.E2E_BASE_URL,
    }),
    tutorContext = await browser.newContext({
      baseURL: process.env.E2E_BASE_URL,
    });
  const customer = await customerContext.newPage(),
    tutor = await tutorContext.newPage();
  try {
    await login(customer, "customer");
    await customer.goto("/marketplace");
    await expect(
      customer.getByRole("heading", { name: /dịch vụ/i }).first(),
    ).toBeVisible();
    await customer.goto("/messages");
    await expect(
      customer.getByRole("heading", { name: "Tin nhắn", exact: true }),
    ).toBeVisible();
    const invitations = await data(customer, "/invitations");
    const pending = invitations.data.find(
      (i: { status: string }) => i.status === "pending",
    );
    expect(pending, "Seed a fresh pending invitation").toBeTruthy();
    await customer.goto(`/invitations/${pending.id}`);
    await customer.getByRole("button", { name: "Chấp nhận lời mời" }).click();
    await expect(customer).toHaveURL(
      new RegExp(`/programs/${pending.program_id}$`),
    );
    await login(tutor, "tutor");
    const id = pending.program_id;
    await tutor.goto(`/programs/${id}/sessions`);
    await tutor.getByText("Tạo mới", { exact: true }).click();
    const schedule = tutor.locator("details[open]");
    await schedule
      .getByLabel("Tiêu đề", { exact: true })
      .fill("E2E planned session");
    await schedule
      .getByLabel("Bắt đầu dự kiến (giờ máy của bạn)")
      .fill("2027-01-15T09:00");
    await schedule
      .getByLabel("Kết thúc dự kiến (giờ máy của bạn)")
      .fill("2027-01-15T10:00");
    await schedule
      .getByLabel("Hình thức", { exact: true })
      .selectOption("online");
    await schedule.getByRole("button", { name: "Lưu", exact: true }).click();
    await expect(
      tutor.getByRole("heading", { name: "E2E planned session" }),
    ).toBeVisible();
    await tutor
      .getByRole("button", { name: "Hoàn thành buổi học", exact: true })
      .click();
    await expect(tutor.getByText(/Actual #/)).toBeVisible();
    await tutor.goto(`/programs/${id}/tasks`);
    await tutor.getByText("Tạo mới", { exact: true }).click();
    const taskForm = tutor.locator("details[open]");
    await taskForm
      .getByLabel("Tiêu đề", { exact: true })
      .fill("E2E speaking task");
    await taskForm.getByRole("button", { name: "Lưu", exact: true }).click();
    await expect(
      tutor.getByRole("heading", { name: "E2E speaking task", exact: true }),
    ).toBeVisible();
    const tasks = await data(tutor, `/programs/${id}/tasks`);
    const task = tasks.data.find(
      (t: { title: string }) => t.title === "E2E speaking task",
    );
    expect(task).toBeTruthy();
    await customer.goto(`/programs/${id}/tasks/${task.id}`);
    await customer
      .getByLabel("Nội dung bài nộp", { exact: true })
      .fill("My real browser submission.");
    await customer.getByRole("button", { name: "Nộp phiên bản mới" }).click();
    await expect(customer.getByText("Bài nộp phiên bản 1")).toBeVisible();
    await tutor.goto(`/programs/${id}/tasks/${task.id}`);
    const review = tutor
      .locator("form")
      .filter({
        has: tutor.getByRole("button", { name: "Duyệt bài nộp", exact: true }),
      });
    await review.getByLabel("Phản hồi", { exact: true }).fill("Good work.");
    await review
      .getByRole("button", { name: "Duyệt bài nộp", exact: true })
      .click();
    await expect(review).toHaveCount(0);
    await tutor.goto(`/programs/${id}`);
    await tutor
      .getByRole("button", { name: "Hoàn thành", exact: true })
      .click();
    await expect(
      tutor.getByText("Hoàn thành", { exact: true }).first(),
    ).toBeVisible();
    await customer.goto(`/programs/${id}`);
    await customer.getByLabel("Điểm đánh giá (1–5)").fill("5");
    await customer
      .getByLabel("Nhận xét", { exact: true })
      .fill("Helpful lessons.");
    await customer.getByRole("button", { name: "Gửi đánh giá" }).click();
    await expect(customer.getByText("Helpful lessons.")).toBeVisible();
    await customer.goto("/notifications");
    await expect(
      customer.getByRole("heading", { name: "Thông báo", exact: true }),
    ).toBeVisible();
  } finally {
    await customerContext.close();
    await tutorContext.close();
  }
});
test("guest cannot open a workspace", async ({ page }) => {
  await page.goto("/programs/1/sessions");
  await expect(page.getByText("Vui lòng đăng nhập để tiếp tục")).toBeVisible();
});

test("admin approves the assigned complete fixture through shared Filament actions", async ({
  page,
}) => {
  await login(page, "admin");
  const result = await data(page, "/admin/tutor-profiles?status=submitted");
  const pending = result.data.find(
    (row: { user: { full_name: string } }) =>
      row.user.full_name === "Hồ sơ demo cần xét duyệt",
  );
  expect(
    pending,
    "Fresh FrontendDemoSeeder must contain an assigned complete pending profile",
  ).toBeTruthy();
  await page.goto(
    `${api!.replace(/\/$/, "")}/admin/operations?section=profile&recordId=${pending.profile.id}`,
  );
  await page
    .getByLabel("Lý do / Kết quả xử lý")
    .fill("E2E approval after checking required documents");
  await page.getByRole("button", { name: "Phê duyệt", exact: true }).click();
  await expect(page.getByText("Đã duyệt", { exact: true })).toBeVisible();
});
