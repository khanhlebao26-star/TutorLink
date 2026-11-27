import { getCurrentUser } from "@/lib/data/services";
import { ApiClientError, request, setAuthHint } from "@/lib/api/client";
import { getFavoriteTutors } from "@/lib/data/favorites";
import type { User } from "@/lib/data/types";
import { act, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";

const navigation = vi.hoisted(() => ({ pathname: "/programs" }));
vi.mock("next/navigation", () => ({
  usePathname: () => navigation.pathname,
  useSearchParams: () => new URLSearchParams(),
  useRouter: () => ({ push: vi.fn(), refresh: vi.fn() }),
}));
vi.mock("@/lib/data/services", () => ({ getCurrentUser: vi.fn() }));
vi.mock("@/lib/data/marketplace", () => ({ getMarketplaceCatalog: vi.fn().mockResolvedValue([]) }));
vi.mock("@/lib/data/favorites", () => ({
  favoriteUpdateEvent: "favorites-updated",
  getFavoriteTutors: vi.fn().mockResolvedValue([]),
}));
vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  request: vi.fn().mockResolvedValue({ data: { data: { count: 0 } } }),
  setAuthHint: vi.fn(),
}));

let AppShell: typeof import("@/components/layout/app-shell").AppShell;
let ProgramWorkspaceShell: typeof import("@/components/programs/program-workspace-shell").ProgramWorkspaceShell;

beforeEach(async () => {
  vi.resetModules();
  ({ AppShell } = await import("@/components/layout/app-shell"));
  ({ ProgramWorkspaceShell } = await import("@/components/programs/program-workspace-shell"));
  vi.clearAllMocks();
  navigation.pathname = "/programs";
});

it.each(["tutor", "customer"] as const)("waits for the session before showing the %s menu", async (role) => {
  let resolveSession!: (user: User) => void;
  vi.mocked(getCurrentUser).mockReturnValue(new Promise((resolve) => { resolveSession = resolve; }));
  render(<AppShell title="Program của tôi"><p>Nội dung Program</p></AppShell>);

  const sidebar = screen.getByRole("complementary", { name: "Tài khoản" });
  expect(within(sidebar).getByRole("status", { name: "Đang tải menu tài khoản" })).toBeVisible();
  expect(within(sidebar).queryByRole("link")).not.toBeInTheDocument();
  expect(screen.queryByText("Học viên · Program của tôi")).not.toBeInTheDocument();

  await act(async () => {
    resolveSession({ id: 62, full_name: "Tài khoản thử", role, status: "active" } as User);
  });

  expect(screen.getByText("Nội dung Program")).toBeVisible();
  expect(screen.queryByRole("status", { name: "Đang tải menu tài khoản" })).toBeNull();
  if (role === "tutor") {
    expect(sidebar).toHaveAttribute("aria-label", "Workspace gia sư");
    expect(within(sidebar).getByRole("link", { name: "Chương trình học" })).toHaveAttribute("aria-current", "page");
  } else {
    expect(sidebar).toHaveAttribute("aria-label", "Workspace học viên");
    expect(within(sidebar).getByRole("link", { name: "Hồ sơ học tập" })).toHaveAttribute("href", "/learning-profile");
  }
});

it.each(["customer", "tutor"] as const)("keeps the %s account menu and session when navigating workspace tabs", async (role) => {
  vi.mocked(getCurrentUser).mockResolvedValue({ id: 62, full_name: "Tài khoản thử", role, status: "active" } as User);
  navigation.pathname = "/programs/9";
  const view = render(<ProgramWorkspaceShell id={9}><p>Nội dung tổng quan</p></ProgramWorkspaceShell>);
  const sidebar = await screen.findByRole("complementary", { name: "Điều hướng Workspace" });
  const accountHeader = screen.getByRole("banner");
  const tabs = screen.getByRole("navigation", { name: "Các tab workspace" });
  expect(within(tabs).getByRole("link", { name: "Tổng quan" })).toHaveAttribute("aria-current", "page");

  navigation.pathname = "/programs/9/sessions";
  view.rerender(<ProgramWorkspaceShell id={9}><p>Nội dung buổi học</p></ProgramWorkspaceShell>);
  expect(screen.getByRole("complementary", { name: "Điều hướng Workspace" })).toBe(sidebar);
  expect(screen.getByRole("banner")).toBe(accountHeader);
  expect(screen.queryByRole("status", { name: "Đang tải menu tài khoản" })).not.toBeInTheDocument();
  expect(screen.getByText("Nội dung buổi học")).toBeVisible();
  expect(within(tabs).getByRole("link", { name: "Buổi học" })).toHaveAttribute("aria-current", "page");
  expect(getCurrentUser).toHaveBeenCalledTimes(1);

  navigation.pathname = "/programs/9/tasks";
  view.rerender(<ProgramWorkspaceShell id={9}><p>Nội dung bài tập</p></ProgramWorkspaceShell>);
  expect(screen.getByRole("complementary", { name: "Điều hướng Workspace" })).toBe(sidebar);
  expect(within(tabs).getByRole("link", { name: "Bài tập" })).toHaveAttribute("aria-current", "page");
  expect(getCurrentUser).toHaveBeenCalledTimes(1);
});

it("keeps protected content hidden for a signed-out session", async () => {
  vi.mocked(getCurrentUser).mockRejectedValue(new ApiClientError("Unauthenticated", { status: 401 }));
  render(<AppShell title="Private program"><p>Private content</p></AppShell>);

  expect(await screen.findByRole("heading", { name: "Vui lòng đăng nhập để tiếp tục" })).toBeVisible();
  expect(screen.getByRole("link", { name: "Đăng nhập" })).toHaveAttribute("href", "/auth/login");
  expect(screen.queryByText("Private content")).not.toBeInTheDocument();
});

it("shows a retry action after a session error and renders the protected page after retry", async () => {
  vi.mocked(getCurrentUser)
    .mockRejectedValueOnce(new ApiClientError("Backend unavailable", { status: 503 }))
    .mockResolvedValueOnce({ id: 62, full_name: "Tutor Anna", role: "tutor", status: "active" } as User);
  render(<AppShell title="Program"><p>Program content</p></AppShell>);

  expect(await screen.findByRole("heading", { name: "Không thể kiểm tra tài khoản" })).toBeVisible();
  expect(screen.getByText("Backend unavailable")).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Thử lại" }));

  expect(await screen.findByText("Program content")).toBeVisible();
  expect(getCurrentUser).toHaveBeenCalledTimes(2);
});

it("routes a signed-in user to their own workspace when the requested role does not match", async () => {
  navigation.pathname = "/admin/reports";
  vi.mocked(getCurrentUser).mockResolvedValue({ id: 62, full_name: "Tutor Anna", role: "tutor", status: "active" } as User);
  render(<AppShell role="admin" title="Reports"><p>Admin report data</p></AppShell>);

  expect(await screen.findByRole("heading", { name: "Trang này dành cho Quản trị" })).toBeVisible();
  expect(screen.getByRole("link", { name: "Tiếp tục đến khu vực của bạn" })).toHaveAttribute("href", "/profile");
  expect(screen.queryByText("Admin report data")).not.toBeInTheDocument();
});

it("renders public navigation for a guest and closes the mobile menu after navigation", async () => {
  vi.mocked(getCurrentUser).mockRejectedValue(new ApiClientError("Unauthenticated", { status: 401 }));
  navigation.pathname = "/about";
  render(<AppShell><p>About page</p></AppShell>);
  expect(await screen.findByRole("link", { name: "Đăng ký" })).toHaveAttribute("href", "/auth/register");
  expect(screen.getByRole("search")).toBeVisible();
  expect(screen.getByText("About page")).toBeVisible();
  const toggle = screen.getByRole("button", { name: "Mở điều hướng" });
  await userEvent.click(toggle);
  expect(screen.getByRole("navigation", { name: "Điều hướng chính" })).toHaveClass("is-open");
  await userEvent.click(screen.getByRole("button", { name: "Đóng điều hướng" }));
  expect(screen.getByRole("button", { name: "Mở điều hướng" })).toHaveAttribute("aria-expanded", "false");
});

it("uses the fresh account endpoint on workspace routes and refreshes when the account changes", async () => {
  const tutor = { id: 74, full_name: "Tutor Fresh", role: "tutor", status: "active" } as User;
  vi.mocked(request).mockResolvedValue({ data: { data: tutor } } as never);
  vi.mocked(getFavoriteTutors).mockResolvedValue([{ id: 1 }] as never);
  navigation.pathname = "/me/listings";
  render(<AppShell title="Dịch vụ"><p>Listing workspace</p></AppShell>);
  expect(await screen.findByText("Listing workspace")).toBeVisible();
  expect(request).toHaveBeenCalledWith({ method: "GET", url: "/me" });
  expect(setAuthHint).toHaveBeenCalledWith(true);
  expect(await screen.findByRole("link", { name: /Yêu thích, 1 hồ sơ đã lưu/ })).toBeVisible();
  await act(async () => { window.dispatchEvent(new Event("tutorlink-account-updated")); });
  await waitFor(() => expect(request).toHaveBeenCalledWith({ method: "GET", url: "/me" }));
  expect(vi.mocked(request).mock.calls.filter(([config]) => config.url === "/me")).toHaveLength(2);
  expect(getFavoriteTutors).toHaveBeenCalled();
});

it("does not request a session for the authentication frame", () => {
  navigation.pathname = "/auth/login";
  render(<AppShell mode="auth"><p>Sign in form</p></AppShell>);
  expect(screen.getByText("Sign in form")).toBeVisible();
  expect(screen.getByRole("link", { name: "Đăng nhập" })).toHaveAttribute("href", "/auth/login");
  expect(screen.queryByRole("contentinfo")).toBeNull();
  expect(getCurrentUser).not.toHaveBeenCalled();
});
