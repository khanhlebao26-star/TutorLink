import assert from "node:assert/strict";
import fs from "node:fs/promises";
import path from "node:path";
const { chromium } = await import(
  process.env.PLAYWRIGHT_MODULE ?? "playwright"
);
const base = process.env.TUTORLINK_URL ?? "http://localhost:3000";
const output = path.resolve(
  process.env.ISSUE15_OUTPUT_DIR ?? "../outputs/issue-15-2026-10-13",
);
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({
  headless: true,
  executablePath:
    process.env.TUTORLINK_CHROME_PATH ?? "/opt/google/chrome/chrome",
  args: ["--no-sandbox"],
});
const report = {
  scope:
    "Frontend integration proposal; intercepted responses are synthetic fixtures, never live A3 acceptance.",
  checks: [],
  captures: [],
  realApi: [],
  errors: [],
};
const record = (name, scope = "intercepted fixture") =>
  report.checks.push({ name, result: "PASS", scope });
const catalog = [
  {
    id: 1,
    name: "Giáo dục",
    slug: "education",
    status: "active",
    sort_order: 1,
    subcategories: [
      {
        id: 11,
        category_id: 1,
        name: "Toán học",
        slug: "math",
        status: "active",
        specializations: [
          {
            id: 111,
            subcategory_id: 11,
            name: "Toán lớp 9",
            slug: "math-9",
            status: "active",
          },
        ],
      },
    ],
  },
  {
    id: 2,
    name: "Fitness",
    slug: "fitness",
    status: "active",
    sort_order: 2,
    subcategories: [
      {
        id: 22,
        category_id: 2,
        name: "Thể lực",
        slug: "strength",
        status: "active",
        specializations: [
          {
            id: 222,
            subcategory_id: 22,
            name: "Sức bền",
            slug: "endurance",
            status: "active",
          },
        ],
      },
    ],
  },
  {
    id: 3,
    name: "Thể thao",
    slug: "sports",
    status: "active",
    sort_order: 3,
    subcategories: [],
  },
];
const availability = {
  timezone: "Asia/Ho_Chi_Minh",
  rules: [
    { weekday: 1, start_time: "09:00", end_time: "12:00" },
    { weekday: 1, start_time: "14:00", end_time: "17:00" },
    { weekday: 3, start_time: "08:00", end_time: "11:00" },
  ],
};
const tutor = {
  slug: "tutor-fixture",
  full_name: "Gia sư kiểm thử",
  headline: "Hướng dẫn học tập theo mục tiêu",
  experience_years: 3,
  rating: { average: 4.8, count: 83 },
};
const listing = {
  id: 501,
  tutor_profile_id: 201,
  specialization_id: 111,
  slug: "math-fixture",
  title: "Gia sư Toán lớp 9 online",
  description:
    "Củng cố nền tảng Toán lớp 9 theo mục tiêu của học viên.\nNội dung và lộ trình được trao đổi trực tiếp trước khi học.",
  delivery_mode: "online",
  city: null,
  district: null,
  price_from_minor: 200000,
  currency: "VND",
  price_unit: "session",
  status: "active",
  created_at: "2026-10-01T00:00:00Z",
  updated_at: "2026-10-01T00:00:00Z",
  category_name: "Giáo dục",
  specialization_name: "Toán lớp 9",
  tutor,
  rating: { average: 4.9, count: 58 },
  availability,
};
const statuses = ["active", "draft", "paused", "hidden", "archived"];
const actions = {
  draft: ["edit", "publish", "archive"],
  active: ["edit", "pause", "archive"],
  paused: ["edit", "publish", "archive"],
  hidden: [],
  archived: [],
};
let owners = statuses.map((status, index) => ({
  ...listing,
  id: 501 + index,
  status,
  title: [
    "Gia sư Toán lớp 9 online",
    "Luyện giao tiếp tiếng Anh",
    "Rèn thể lực cơ bản",
    "Dịch vụ đã ẩn",
    "Dịch vụ đã lưu trữ",
  ][index],
  allowed_actions: actions[status],
}));
let patchFailure = null;
let availabilityFailure = null;
let lastPatch;
let latestSearch;
let includeAggregate = true;
let includeAvailability = true;
let meRole = "tutor";
let meFailure;
let searchFailure;
const aggregate = {
  average: 4.8,
  count: 83,
  histogram: { 5: 78, 4: 5, 3: 0, 2: 0, 1: 0 },
};
const context = await browser.newContext({
  viewport: { width: 1536, height: 1000 },
  locale: "vi-VN",
  timezoneId: "Asia/Ho_Chi_Minh",
});
const page = await context.newPage();
page.on("pageerror", (error) => report.errors.push(error.message));
const headers = {
  "access-control-allow-origin": new URL(base).origin,
  "access-control-allow-credentials": "true",
  "access-control-allow-methods": "GET,POST,PATCH,PUT,DELETE,OPTIONS",
  "access-control-allow-headers": "Content-Type,Accept,X-XSRF-TOKEN",
};
const fulfill = (route, body, status = 200) =>
  route.fulfill({
    status,
    contentType: "application/json",
    headers,
    ...(status === 204 ? {} : { body: JSON.stringify(body) }),
  });
const wait = () => page.waitForLoadState("networkidle");
async function capture(name, scope = "intercepted fixture") {
  await wait();
  await page.evaluate(() => document.fonts.ready);
  const file = `${name}.png`;
  await page.screenshot({
    path: path.join(output, file),
    fullPage: true,
    style: "nextjs-portal { display: none !important; }",
  });
  const metrics = await page.evaluate(() => ({
    viewport: innerWidth,
    document: document.documentElement.scrollWidth,
    main: document.querySelector("main")?.getBoundingClientRect().width,
  }));
  assert.ok(
    metrics.document <= metrics.viewport + 1,
    `${name} overflow ${JSON.stringify(metrics)}`,
  );
  report.captures.push({
    file,
    scope,
    route: new URL(page.url()).pathname,
    metrics,
  });
}
try {
  // Real GETs establish dependency state without reading secrets or changing backend data.
  for (const endpoint of [
    "catalog/categories",
    "listings",
    "listings/math-fixture",
    "tutors/tutor-fixture",
    "tutors/tutor-fixture/reviews",
    "me/listings",
    "me/availability-rules",
  ]) {
    const response = await context.request.get(
      `http://localhost:8000/api/v1/${endpoint}`,
      { headers: { Accept: "application/json" } },
    );
    report.realApi.push({ path: endpoint, status: response.status() });
  }
  await page.goto(`${base}/marketplace`);
  await wait();
  await page
    .getByRole("alert")
    .filter({ hasText: "Dịch vụ tìm kiếm chưa sẵn sàng" })
    .waitFor();
  record(
    "Missing live Listing endpoint shows recoverable error, no mock Listing rows",
    "live local API",
  );
  assert.equal(await page.locator(".market-row").count(), 0);
  await capture("marketplace-live-unavailable", "live local API");
  await page.goto(`${base}/me/listings`);
  await wait();
  await page
    .getByRole("heading", { name: "Vui lòng đăng nhập để tiếp tục" })
    .waitFor();
  assert.equal(await page.locator(".owner-listings").count(), 0);
  record(
    "Anonymous owner deep link blocked before mounting owner content",
    "live local /me",
  );
  await capture("owner-live-guest", "live local /me");

  await page.route("**/sanctum/csrf-cookie", (route) =>
    fulfill(route, null, 204),
  );
  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const url = new URL(req.url());
    const endpoint = url.pathname.replace("/api/v1", "");
    if (req.method() === "OPTIONS") return fulfill(route, null, 204);
    if (endpoint === "/me")
      return meFailure
        ? fulfill(route, { message: "Session unavailable" }, meFailure)
        : fulfill(route, {
            data: {
              id: 301,
              full_name: tutor.full_name,
              email: "synthetic@example.test",
              role: meRole,
              status: "active",
              email_verified_at: "2026-10-01T00:00:00Z",
            },
          });
    if (endpoint === "/catalog/categories")
      return fulfill(route, { data: catalog });
    if (endpoint === "/listings") {
      latestSearch = Object.fromEntries(url.searchParams);
      const q = url.searchParams.get("q");
      if (q === "slow")
        await new Promise((resolve) => setTimeout(resolve, 750));
      if (searchFailure)
        return fulfill(route, { message: "Request failed" }, searchFailure);
      const found =
        q === "empty"
          ? []
          : [
              {
                ...listing,
                title:
                  q === "fast"
                    ? "Kết quả mới nhất"
                    : q === "slow"
                      ? "Kết quả cũ"
                      : listing.title,
              },
            ];
      return fulfill(route, {
        data: found,
        meta: {
          total: q === "empty" ? 0 : 24,
          per_page: Number(url.searchParams.get("per_page") ?? 6),
          current_page: Number(url.searchParams.get("page") ?? 1),
        },
      });
    }
    if (endpoint.startsWith("/listings/"))
      return fulfill(route, {
        data: {
          ...listing,
          availability: includeAvailability ? availability : undefined,
        },
      });
    if (endpoint.endsWith("/reviews"))
      return fulfill(route, {
        data: Array.from({ length: 5 }, (_, i) => ({
          id: i + 1,
          author_name: `Học viên kiểm thử ${i + 1}`,
          rating: i ? 5 : 4,
          comment: "Nội dung rõ ràng, phù hợp với mục tiêu học tập.",
          created_at: "2026-10-12T00:00:00Z",
        })),
        meta: {
          total: 83,
          per_page: 5,
          current_page: Number(url.searchParams.get("page") ?? 1),
        },
        aggregate: includeAggregate ? aggregate : undefined,
      });
    if (endpoint.startsWith("/tutors/"))
      return fulfill(route, {
        data: {
          ...tutor,
          bio: "Giới thiệu từ dữ liệu công khai.\nKhông dùng hồ sơ xác minh riêng.",
          listings: [listing],
          availability: includeAvailability ? availability : undefined,
          email: "DO_NOT_RENDER_PRIVATE",
          verification_document_file_id: "DO_NOT_RENDER_PRIVATE",
        },
      });
    if (endpoint === "/me/availability-rules") {
      if (req.method() === "PUT" && availabilityFailure)
        return fulfill(
          route,
          {
            message: "Thử lại lịch khả dụng",
            errors:
              availabilityFailure === 422
                ? { "rules.0.start_time": ["Khung giờ cần được điều chỉnh."] }
                : undefined,
          },
          availabilityFailure,
        );
      return fulfill(route, { data: availability });
    }
    if (endpoint === "/me/listings" && req.method() === "GET") {
      const status = url.searchParams.get("status");
      const filtered = status
        ? owners.filter((item) => item.status === status)
        : owners;
      const counts = Object.fromEntries(
        statuses.map((item) => [
          item,
          owners.filter((owner) => owner.status === item).length,
        ]),
      );
      return fulfill(route, {
        data: filtered,
        meta: {
          total: filtered.length,
          current_page: 1,
          per_page: 20,
          status_counts: { all: owners.length, ...counts },
          allowed_actions: ["create"],
        },
      });
    }
    if (endpoint === "/me/listings" && req.method() === "POST") {
      lastPatch = req.postDataJSON();
      return fulfill(
        route,
        {
          data: {
            ...listing,
            ...lastPatch,
            id: 510,
            status: "draft",
            allowed_actions: actions.draft,
          },
        },
        201,
      );
    }
    if (endpoint.startsWith("/me/listings/")) {
      const id = Number(endpoint.split("/").at(-1));
      let owner = owners.find((item) => item.id === id) ?? {
        ...listing,
        id,
        status: "draft",
        allowed_actions: actions.draft,
      };
      if (req.method() === "PATCH") {
        lastPatch = req.postDataJSON();
        if (patchFailure === "network") return route.abort("failed");
        if (patchFailure)
          return fulfill(
            route,
            {
              message: "Không thể lưu thay đổi",
              errors:
                patchFailure === 422
                  ? { title: ["Tiêu đề cần điều chỉnh."] }
                  : undefined,
            },
            patchFailure,
          );
        owner = {
          ...owner,
          ...lastPatch,
          allowed_actions: actions[lastPatch.status ?? owner.status],
        };
        owners = owners.map((item) => (item.id === id ? owner : item));
      }
      if (req.method() === "DELETE") {
        owners = owners.map((item) =>
          item.id === id
            ? { ...item, status: "archived", allowed_actions: [] }
            : item,
        );
        return fulfill(route, null, 204);
      }
      return fulfill(route, { data: owner });
    }
    return fulfill(route, { message: "Fixture endpoint unavailable" }, 404);
  });

  await page.goto(
    `${base}/marketplace?category=1&subcategory=11&specialization=111&price_unit=session&sort=price_asc&page=2`,
  );
  await wait();
  assert.equal(latestSearch.price_unit, "session");
  assert.equal(latestSearch.sort, "price_asc");
  assert.equal(latestSearch.specialization_id, "111");
  assert.match(await page.locator(".market-count").innerText(), /24/);
  assert.match(
    await page.locator(".market-row-actions strong").innerText(),
    /200\.000 VND \/ buổi/,
  );
  await page.getByRole("button", { name: "Fitness", exact: true }).click();
  await page.waitForURL((url) => url.searchParams.get("category") === "2");
  await wait();
  let url = new URL(page.url());
  assert.equal(url.searchParams.get("category"), "2");
  assert.equal(url.searchParams.has("specialization"), false);
  assert.equal(url.searchParams.has("page"), false);
  await page.goBack();
  await page.waitForURL(
    (url) =>
      url.searchParams.get("category") === "1" &&
      url.searchParams.get("page") === "2",
  );
  await wait();
  assert.equal(
    await page.locator("#market-specialization").inputValue(),
    "111",
  );
  assert.equal(new URL(page.url()).searchParams.get("page"), "2");
  await page.goForward();
  await page.waitForURL((url) => url.searchParams.get("category") === "2");
  await wait();
  assert.equal(new URL(page.url()).searchParams.get("category"), "2");
  record(
    "URL back/forward restores category children/page; category change resets children/page; API price sort includes unit; total uses server",
  );
  const removedUnit = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname.endsWith("/listings") &&
      !new URL(response.url()).searchParams.has("price_unit"),
  );
  await page.locator("#market-unit").selectOption("");
  await page.waitForURL((url) => !url.searchParams.has("price_unit"));
  await removedUnit;
  await wait();
  assert.equal(latestSearch.sort, "newest");
  assert.equal(latestSearch.min_price, undefined);
  record("Removing price unit removes fee/sort comparison");
  await page.locator("#market-q").fill("draft retained");
  await page.locator("#market-city").fill("Hà Nội");
  await page.locator("#market-mode").selectOption("offline");
  await page.waitForURL(
    (url) => url.searchParams.get("q") === "draft retained",
  );
  await wait();
  assert.equal(await page.locator("#market-q").inputValue(), "draft retained");
  assert.equal(await page.locator("#market-city").inputValue(), "Hà Nội");
  assert.equal(new URL(page.url()).searchParams.get("city"), "Hà Nội");
  record("Typed query/city survive filter selection in visible inputs and URL");
  await page.locator("#market-unit").selectOption("session");
  await page.waitForURL(
    (url) => url.searchParams.get("price_unit") === "session",
  );
  await wait();
  await page.locator("#market-min").fill("150000");
  const feeApplied = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname.endsWith("/listings") &&
      new URL(response.url()).searchParams.get("min_price") === "150000",
  );
  await page.locator("#market-sort").selectOption("price_desc");
  await page.waitForURL(
    (url) => url.searchParams.get("min_price") === "150000",
  );
  await feeApplied;
  await wait();
  assert.equal(await page.locator("#market-min").inputValue(), "150000");
  assert.equal(latestSearch.min_price, "150000");
  record("Typed fee survives sort selection and includes API price unit");
  const lateRequest = page.waitForRequest(
    (req) =>
      new URL(req.url()).pathname.endsWith("/listings") &&
      new URL(req.url()).searchParams.get("q") === "slow",
  );
  await page.locator("#market-q").fill("slow");
  await page.getByRole("button", { name: "Tìm kiếm", exact: true }).click();
  await page.waitForURL(/q=slow/);
  await lateRequest;
  await page.locator("#market-q").fill("fast");
  await page.getByRole("button", { name: "Tìm kiếm", exact: true }).click();
  await page.waitForURL(/q=fast/);
  await wait();
  assert.match(
    await page.locator(".market-row h2").innerText(),
    /Kết quả mới nhất/,
  );
  assert.equal(await page.getByText("Kết quả cũ", { exact: true }).count(), 0);
  record("Late response cannot replace current query results");
  await page.locator("#market-q").fill("empty");
  await page.getByRole("button", { name: "Tìm kiếm", exact: true }).click();
  await page.waitForURL(/q=empty/);
  await wait();
  assert.equal(await page.locator(".market-row").count(), 0);
  assert.match(await page.locator(".market-count").innerText(), /0/);
  record("Server empty/count displayed without local page filter");

  for (const status of [401, 419, 429]) {
    searchFailure = status;
    await page.reload();
    await wait();
    assert.equal(await page.locator(".market-row").count(), 0);
    assert.equal(await page.locator(".market-results .state-error").count(), 1);
    record(`Search error ${status} has retry and hides old results`);
  }
  searchFailure = null;
  meRole = "customer";
  await page.goto(`${base}/me/listings`);
  await wait();
  assert.equal(await page.locator(".owner-listings").count(), 0);
  await page
    .getByRole("heading", { name: "Trang này dành cho Gia sư" })
    .waitFor();
  record("Customer cannot mount Tutor owner route");
  meRole = "admin";
  await page.reload();
  await wait();
  assert.equal(await page.locator(".owner-listings").count(), 0);
  record("Admin does not gain owner Listing route by default");
  meRole = "tutor";
  await page.goto(`${base}/me/listings`);
  await wait();
  await page.getByRole("button", { name: "Tất cả (5)", exact: true }).click();
  await wait();
  assert.equal(await page.locator(".data-table tbody tr").count(), 5);
  record("Clicking active owner status tab does not leave loading stuck");
  const hidden = page.locator("tbody tr").filter({ hasText: "Dịch vụ đã ẩn" });
  assert.equal(await hidden.locator("button").count(), 0);
  assert.equal(await hidden.getByText("Chỉnh sửa", { exact: true }).count(), 0);
  record(
    "Hidden/Archived owner records have no mutation action in server permissions",
  );
  const draft = page
    .locator("tbody tr")
    .filter({ hasText: "Luyện giao tiếp tiếng Anh" });
  const published = page.waitForResponse(
    (response) =>
      response.request().method() === "PATCH" &&
      response.url().endsWith("/me/listings/502"),
  );
  await draft.getByRole("button", { name: "Công khai" }).click();
  await published;
  await wait();
  assert.deepEqual(lastPatch, { status: "active" });
  record("Owner publish sends explicit lifecycle PATCH from allowed_actions");

  await page.goto(`${base}/me/listings/501/edit`);
  await wait();
  await page.locator("#listing-title").fill("Bản đang sửa được giữ lại");
  for (const status of [422, 409, 419, 401, "network"]) {
    patchFailure = status;
    const saved =
      status === "network"
        ? page.waitForEvent("requestfailed", {
            predicate: (request) =>
              request.method() === "PATCH" &&
              request.url().endsWith("/me/listings/501"),
          })
        : page.waitForResponse(
            (response) =>
              response.request().method() === "PATCH" &&
              response.url().endsWith("/me/listings/501"),
          );
    await page
      .getByRole("button", { name: "Lưu thay đổi", exact: true })
      .click();
    await saved;
    await wait();
    assert.equal(
      await page.locator("#listing-title").inputValue(),
      "Bản đang sửa được giữ lại",
    );
    if (status === 422)
      assert.match(
        await page.locator("#listing-title-error").innerText(),
        /điều chỉnh/,
      );
    if (status === 401 || status === 419)
      assert.equal(
        await page
          .getByRole("link", { name: "Đăng nhập trong tab mới" })
          .getAttribute("target"),
        "_blank",
      );
    record(
      `Owner save ${status} retains draft; field/re-auth/conflict recovery available`,
    );
  }
  patchFailure = null;
  await page.goto(`${base}/me/listings/new`);
  await wait();
  await page.locator("#listing-title").fill("Dịch vụ mới kiểm thử");
  await page
    .locator("#listing-description")
    .fill("Mô tả dịch vụ mới từ kiểm thử.");
  await page.locator("#listing-category").selectOption("1");
  await page.locator("#listing-subcategory").selectOption("11");
  await page.locator("#listing-specialization").selectOption("111");
  await page.locator("#listing-fee").fill("200000");
  const created = page.waitForResponse(
    (response) =>
      response.request().method() === "POST" &&
      response.url().endsWith("/me/listings"),
  );
  await page.getByRole("button", { name: "Lưu bản nháp", exact: true }).click();
  await created;
  await page.waitForURL(/me\/listings\/510/);
  await wait();
  assert.equal(lastPatch.status, undefined);
  assert.equal(lastPatch.price_from_minor, 200000);
  record("Draft create sends VND integer and no arbitrary status");

  await page.goto(`${base}/me/availability`);
  await wait();
  await page.locator("#start-0").fill("10:00");
  for (const status of [422, 409]) {
    availabilityFailure = status;
    const saved = page.waitForResponse(
      (response) =>
        response.request().method() === "PUT" &&
        response.url().endsWith("/me/availability-rules"),
    );
    await page
      .getByRole("button", { name: "Lưu lịch khả dụng", exact: true })
      .click();
    await saved;
    await wait();
    assert.equal(await page.locator("#start-0").inputValue(), "10:00");
    record(`Availability save ${status} retains local hours/timezone`);
  }
  availabilityFailure = null;
  await page.locator("#end-0").fill("15:00");
  await page
    .getByRole("button", { name: "Lưu lịch khả dụng", exact: true })
    .click();
  await wait();
  await page
    .getByText("Khung giờ trùng với một khung giờ khác trong ngày.")
    .first()
    .waitFor();
  record("Weekly overlapping slots blocked; timezone explicitly displayed");

  await page.goto(`${base}/tutors/tutor-fixture/reviews`);
  await wait();
  assert.equal(await page.locator(".rating-histogram").count(), 1);
  assert.match(await page.locator(".public-rating-summary").innerText(), /83/);
  assert.equal(
    await page
      .locator(".rating-histogram li")
      .nth(2)
      .locator(".rating-bar > span")
      .evaluate((el) => el.style.width),
    "0%",
  );
  record("Histogram uses full server aggregate; zero count bars remain zero");
  includeAggregate = false;
  await page.reload();
  await wait();
  assert.equal(await page.locator(".rating-histogram").count(), 0);
  assert.equal(await page.locator(".public-rating-summary").count(), 0);
  record(
    "Missing aggregate hides histogram/summary; no page-derived statistics",
  );
  await page.goto(`${base}/tutors/tutor-fixture`);
  await wait();
  assert.doesNotMatch(
    await page.locator("body").innerText(),
    /DO_NOT_RENDER_PRIVATE/,
  );
  record("Public UI ignores unexpected private DTO fields");
  includeAvailability = false;
  await page.reload();
  await wait();
  assert.equal(await page.locator(".public-availability").count(), 0);
  record("Absent availability DTO hides unsupported block");
  includeAvailability = true;
  includeAggregate = true;

  const captures = [
    ["home", "/"],
    ["become-tutor", "/become-a-tutor"],
    ["marketplace", "/marketplace"],
    ["service-detail", "/marketplace/math-fixture"],
    ["tutor-profile", "/tutors/tutor-fixture"],
    ["tutor-reviews", "/tutors/tutor-fixture/reviews"],
    ["owner-list", "/me/listings"],
    ["owner-form", "/me/listings/501/edit"],
    ["availability", "/me/availability"],
    ["auth-baseline-login", "/auth/login"],
  ];
  for (const width of [360, 768, 1536]) {
    await page.setViewportSize({ width, height: width === 360 ? 800 : 1000 });
    for (const [name, route] of captures) {
      await page.goto(`${base}${route}`);
      await wait();
      await capture(`${name}-${width}`);
      if (name === "owner-list" && width === 360) {
        const action = page.locator(".owner-row-actions button").first();
        await action.focus();
        const bounds = await action.boundingBox();
        assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
        record(
          "Owner mobile mutation action is visible without horizontal scrolling and keyboard focusable",
        );
      }
    }
    record(
      `No document overflow on ten routes including Auth shell baseline at ${width}px`,
    );
  }
  await page.goto(`${base}/`);
  await wait();
  const categoryApplied = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname.endsWith("/listings") &&
      new URL(response.url()).searchParams.get("category_id") === "2",
  );
  await page
    .locator(".landing-category")
    .filter({ hasText: "Fitness" })
    .click();
  await page.waitForURL((url) => url.searchParams.get("category") === "2");
  await categoryApplied;
  await wait();
  assert.equal(latestSearch.category_id, "2");
  record(
    "Landing category CTA uses catalog ID and applies real Marketplace URL filter",
  );
  await page.locator("#public-search-q").fill("header search");
  const headerApplied = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname.endsWith("/listings") &&
      new URL(response.url()).searchParams.get("q") === "header search",
  );
  await page.locator(".public-search button").click();
  await page.waitForURL((url) => url.searchParams.get("q") === "header search");
  await headerApplied;
  await wait();
  assert.equal(latestSearch.q, "header search");
  record("Header submits service-only search to Marketplace");
  await page.goto(`${base}/`);
  await wait();
  const faq = page.locator(".landing-faq-item").first();
  await faq.locator("summary").focus();
  await page.keyboard.press("Enter");
  assert.equal(await faq.getAttribute("open"), "");
  record("Marketing FAQ works with keyboard via native details");
  assert.equal(
    await page
      .locator('.public-footer form, .public-footer a[href="#"]')
      .count(),
    0,
  );
  record("Footer has no unwired newsletter or placeholder link");
  await page.goto(`${base}/marketplace`);
  await wait();
  await page.locator("#market-q").focus();
  await page.keyboard.type("keyboard");
  await page.keyboard.press("Enter");
  await page.waitForURL(/q=keyboard/);
  await wait();
  assert.equal(new URL(page.url()).searchParams.get("q"), "keyboard");
  record("Keyboard search submits service query");
  await page.goto(`${base}/me/availability`);
  await wait();
  await page
    .getByRole("button", { name: "+ Thêm khung giờ Thứ Ba", exact: true })
    .focus();
  await page.keyboard.press("Enter");
  assert.equal(await page.locator("#start-3").count(), 1);
  record("Keyboard can add weekly availability slot");
} catch (error) {
  report.checks.push({
    name: "Browser suite",
    result: "FAIL",
    detail: error.message,
  });
  await page
    .screenshot({ path: path.join(output, "failure.png"), fullPage: true })
    .catch(() => {});
  process.exitCode = 1;
} finally {
  await fs.writeFile(
    path.join(output, "browser-report.json"),
    JSON.stringify(report, null, 2),
  );
  console.log(
    JSON.stringify({
      checks: report.checks.length,
      fail: report.checks.filter((item) => item.result === "FAIL"),
      captures: report.captures.length,
      pageErrors: report.errors,
      report: path.join(output, "browser-report.json"),
    }),
  );
  await browser.close();
}
