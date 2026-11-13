import AdminDashboardPage from "@/app/admin/dashboard/page";
import { ApiClientError, request } from "@/lib/api/client";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/components/layout/app-shell", () => ({ AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock("@/lib/api/client", async original => ({ ...await original<typeof import("@/lib/api/client")>(), request: vi.fn() }));
beforeEach(() => vi.mocked(request).mockReset());

it("shows assigned and unassigned counts from the dashboard API without combining scopes", async () => {
  vi.mocked(request).mockResolvedValue({ data: { data: { resources: {
    tutor_profiles: { unassigned: { submitted: 12 }, mine: { submitted: 3, approved: 8 } },
    reports: { unassigned: { open: 5 }, mine: { open: 2, resolved: 4 } },
  }, permissions: [] } } } as Awaited<ReturnType<typeof request>>);
  render(<AdminDashboardPage />);
  const queue = await screen.findByRole("link", { name: /Hồ sơ chờ phân công/ });
  expect(within(queue).getByText("12")).toBeInTheDocument();
  expect(within(screen.getByRole("link", { name: /Hồ sơ tôi chờ duyệt/ })).getByText("3")).toBeInTheDocument();
  expect(within(screen.getByRole("link", { name: /Báo cáo tôi cần xử lý/ })).getByText("2")).toBeInTheDocument();
  expect(request).toHaveBeenCalledWith({ url: "/admin/dashboard" });
});

it("does not invent zero counts for resources or scopes omitted by backend permissions", async () => {
  vi.mocked(request).mockResolvedValue({ data: { data: { resources: { reports: { mine: {} } }, permissions: [] } } } as Awaited<ReturnType<typeof request>>);
  render(<AdminDashboardPage />);
  await screen.findByRole("link", { name: /Báo cáo tôi cần xử lý/ });
  expect(screen.queryByRole("link", { name: /Hồ sơ chờ phân công/ })).not.toBeInTheDocument();
  expect(screen.queryByRole("link", { name: /Báo cáo chờ phân công/ })).not.toBeInTheDocument();
  expect(screen.queryByRole("columnheader", { name: "Chưa phân công" })).not.toBeInTheDocument();
  expect(screen.queryByRole("heading", { name: "Thông báo hệ thống" })).not.toBeInTheDocument();
});

it("shows a retryable permission error and loads the successful response on retry", async () => {
  vi.mocked(request).mockRejectedValueOnce(new ApiClientError("Permission denied.", { status: 403 }))
    .mockResolvedValueOnce({ data: { data: { resources: {}, permissions: [] } } } as Awaited<ReturnType<typeof request>>);
  render(<AdminDashboardPage />);
  expect(await screen.findByRole("alert")).toHaveTextContent("Bạn không có quyền");
  await userEvent.click(screen.getByRole("button", { name: "Thử lại" }));
  await screen.findByText("Tài khoản chưa được cấp quyền xem thống kê các khu vực quản trị.");
  expect(screen.queryByRole("alert")).not.toBeInTheDocument();
});
