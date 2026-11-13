import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { expect, it, vi } from "vitest";
import { AppShell } from "@/components/app-shell";

vi.mock("next/navigation", () => ({ usePathname: () => "/admin/reports" }));
vi.mock("@/lib/data/services", () => ({ getCurrentUser: vi.fn().mockResolvedValue({ id: 1, full_name: "Local Admin", email: "admin@example.test", role: "admin", status: "active" }) }));
vi.mock("@/lib/data/marketplace", () => ({ getMarketplaceCatalog: vi.fn().mockResolvedValue([]) }));
vi.mock("@/lib/data/favorites", () => ({ favoriteUpdateEvent: "favorites-updated", getFavoriteTutors: vi.fn() }));
vi.mock("@/lib/api/client", async original => ({ ...await original<typeof import("@/lib/api/client")>(), request: vi.fn().mockResolvedValue({ data: { data: { count: 0 } } }), setAuthHint: vi.fn() }));
vi.mock("@/components/logout-button", () => ({ LogoutButton: () => <button>Đăng xuất</button> }));

it("links admin password settings and marks only report management active in the sidebar", async () => {
  render(<AppShell role="admin" title=""><p>Nội dung báo cáo</p></AppShell>);
  const sidebar = await screen.findByRole("complementary", { name: "Menu Quản trị" });
  expect(within(sidebar).getAllByRole("link")[0]).toHaveTextContent("Dashboard quản trị");
  expect(within(sidebar).getByRole("link", { name: "Dashboard quản trị" })).toHaveAttribute("href", "/admin/dashboard");
  expect(within(sidebar).getByRole("link", { name: "Dashboard quản trị" })).not.toHaveAttribute("aria-current");
  expect(within(sidebar).getByRole("link", { name: "Quản lý báo cáo" })).toHaveAttribute("href", "/admin/reports");
  expect(within(sidebar).getByRole("link", { name: "Quản lý báo cáo" })).toHaveAttribute("aria-current", "page");
  expect(within(sidebar).getByRole("link", { name: "Duyệt hồ sơ Tutor" })).not.toHaveAttribute("aria-current");
  expect(within(sidebar).getByRole("link", { name: "Cài đặt tài khoản" })).toHaveAttribute("href", "/settings");
  await userEvent.click(screen.getByRole("button", { name: "Tài khoản Local Admin" }));
  const menu = screen.getByRole("navigation", { name: "Điều hướng tài khoản quản trị" });
  expect(within(menu).getByRole("link", { name: "Đổi mật khẩu" })).toHaveAttribute("href", "/settings/password");
  expect(within(menu).getByRole("link", { name: "Cài đặt tài khoản" })).toHaveAttribute("href", "/settings");
});
