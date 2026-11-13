import { AppShell } from "@/components/layout/app-shell";
import { ProgramWorkspaceShell } from "@/components/programs/program-workspace-shell";
import { getCurrentUser } from "@/lib/data/services";
import type { User } from "@/lib/data/types";
import { act, render, screen, within } from "@testing-library/react";
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

beforeEach(() => {
  vi.clearAllMocks();
  navigation.pathname = "/programs";
});

it.each(["tutor", "customer"] as const)("waits for the session before showing the %s menu", async (role) => {
  let resolveSession!: (user: User) => void;
  vi.mocked(getCurrentUser).mockReturnValue(new Promise((resolve) => { resolveSession = resolve; }));
  render(<AppShell title="Program của tôi"><p>Nội dung Program</p></AppShell>);

  const sidebar = screen.getByRole("complementary", { name: "Menu tài khoản" });
  expect(within(sidebar).getByRole("status", { name: "Đang tải menu tài khoản" })).toBeVisible();
  expect(within(sidebar).queryByRole("link")).not.toBeInTheDocument();
  expect(screen.queryByText("Học viên · Program của tôi")).not.toBeInTheDocument();

  await act(async () => {
    resolveSession({ id: 62, full_name: "Tài khoản thử", role, status: "active" } as User);
  });

  const label = role === "tutor" ? "Gia sư" : "Học viên";
  const resolvedSidebar = screen.getByRole("complementary", { name: `Menu ${label}` });
  expect(screen.getByText(`${label} · ${role === "tutor" ? "Quản lý giảng dạy" : "Program của tôi"}`)).toBeVisible();
  expect(screen.getByText("Nội dung Program")).toBeVisible();
  expect(within(resolvedSidebar).queryByRole("status")).not.toBeInTheDocument();
  if (role === "tutor") {
    expect(within(resolvedSidebar).getByRole("link", { name: "Hồ sơ Tutor" })).toBeVisible();
    expect(within(resolvedSidebar).queryByRole("link", { name: "Lời mời học" })).not.toBeInTheDocument();
  } else {
    expect(within(resolvedSidebar).getByRole("link", { name: "Lời mời học" })).toBeVisible();
  }
});

it.each(["customer", "tutor"] as const)("keeps the %s account menu and session when navigating workspace tabs", async (role) => {
  vi.mocked(getCurrentUser).mockResolvedValue({ id: 62, full_name: "Tài khoản thử", role, status: "active" } as User);
  navigation.pathname = "/programs/9";
  const view = render(<ProgramWorkspaceShell id={9}><p>Nội dung tổng quan</p></ProgramWorkspaceShell>);
  const sidebar = await screen.findByRole("complementary", { name: `Menu ${role === "tutor" ? "Gia sư" : "Học viên"}` });
  const accountHeader = screen.getByRole("banner");
  const tabs = screen.getByRole("navigation", { name: "Các tab workspace" });
  expect(within(tabs).getByRole("link", { name: "Tổng quan" })).toHaveAttribute("aria-current", "page");

  navigation.pathname = "/programs/9/sessions";
  view.rerender(<ProgramWorkspaceShell id={9}><p>Nội dung buổi học</p></ProgramWorkspaceShell>);
  expect(screen.getByRole("complementary", { name: `Menu ${role === "tutor" ? "Gia sư" : "Học viên"}` })).toBe(sidebar);
  expect(screen.getByRole("banner")).toBe(accountHeader);
  expect(screen.queryByRole("status", { name: "Đang tải menu tài khoản" })).not.toBeInTheDocument();
  expect(screen.getByText("Nội dung buổi học")).toBeVisible();
  expect(within(tabs).getByRole("link", { name: "Buổi học" })).toHaveAttribute("aria-current", "page");
  expect(getCurrentUser).toHaveBeenCalledTimes(1);

  navigation.pathname = "/programs/9/tasks";
  view.rerender(<ProgramWorkspaceShell id={9}><p>Nội dung bài tập</p></ProgramWorkspaceShell>);
  expect(screen.getByRole("complementary", { name: `Menu ${role === "tutor" ? "Gia sư" : "Học viên"}` })).toBe(sidebar);
  expect(within(tabs).getByRole("link", { name: "Bài tập" })).toHaveAttribute("aria-current", "page");
  expect(getCurrentUser).toHaveBeenCalledTimes(1);
});
