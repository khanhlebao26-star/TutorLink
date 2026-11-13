import { render, screen } from "@testing-library/react";
import { expect, it, vi } from "vitest";
import type { ReactNode } from "react";
import SettingsPage from "@/app/settings/page";
import { request } from "@/lib/api/client";
import { AxiosHeaders } from "axios";
vi.mock("@/components/app-shell", () => ({ AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock("@/components/logout-button", () => ({ LogoutButton: () => <button>Đăng xuất</button> }));
vi.mock("@/lib/api/client", async original => ({ ...await original<typeof import("@/lib/api/client")>(), request: vi.fn() }));
it("shows admin identity without editable fields or a save action", async () => {
  vi.mocked(request).mockResolvedValue({ status: 200, statusText: "OK", headers: {}, config: { headers: new AxiosHeaders() }, data: { data: { id: 1, full_name: "Local Admin", email: "admin@example.test", role: "admin", status: "active" } } });
  render(<SettingsPage />);
  expect(await screen.findByText("Local Admin")).toBeVisible();
  expect(screen.getByText("admin@example.test")).toBeVisible();
  expect(screen.getByText("Quản trị viên")).toBeVisible();
  expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
  expect(screen.queryByRole("button", { name: "Lưu thay đổi" })).not.toBeInTheDocument();
  expect(screen.getByRole("link", { name: "Đổi mật khẩu" })).toHaveAttribute("href", "/settings/password");
});
it("keeps name editing available for tutor accounts", async () => {
  vi.mocked(request).mockResolvedValue({ status: 200, statusText: "OK", headers: {}, config: { headers: new AxiosHeaders() }, data: { data: { id: 2, full_name: "Tutor", email: "tutor@example.test", role: "tutor", status: "active" } } });
  render(<SettingsPage />);
  expect(await screen.findByLabelText("Họ và tên")).toHaveValue("Tutor");
  expect(screen.getByRole("button", { name: "Lưu thay đổi" })).toBeVisible();
});
