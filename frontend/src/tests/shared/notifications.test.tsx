import { Notifications } from "@/components/notifications/notifications";
import { request } from "@/lib/api/client";
import { workspaceRead } from "@/lib/data/workspace";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/components/layout/app-shell", () => ({
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));
vi.mock("@/components/programs/workspace-form", () => ({
  WorkspaceForm: ({ label, onSaved }: { label: string; onSaved?: () => void }) => (
    <button type="button" onClick={onSaved}>{label}</button>
  ),
}));
vi.mock("@/lib/data/workspace", async (original) => ({
  ...(await original<typeof import("@/lib/data/workspace")>()),
  workspaceRead: vi.fn(),
}));
vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  request: vi.fn(),
}));

type Notice = {
  id: string;
  type: string;
  read_at: string | null;
  created_at: string;
  deep_link: string | null;
};

function notice(id: string, type: string, read: boolean, createdAt: string, deepLink: string | null): Notice {
  return { id, type, read_at: read ? "2026-11-26T08:00:00Z" : null, created_at: createdAt, deep_link: deepLink };
}

function respondWith(items: Notice[], lastPage = 1) {
  vi.mocked(workspaceRead).mockImplementation(async (path, params) => {
    if (path === "/notifications/unread-count") return { data: { count: 2 } } as never;
    const page = Number(params?.page ?? 1);
    const selected = page === 1 ? items : [
      notice("older-1", "program_paused", false, "2026-11-20T08:00:00Z", "/programs/9"),
      ...items,
    ];
    return { data: selected, meta: { last_page: lastPage } } as never;
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(request).mockResolvedValue({} as never);
});

it("filters real notification categories and only renders safe internal links", async () => {
  const today = new Date().toISOString();
  const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString();
  respondWith([
    notice("invite-1", "invitation_received", false, today, "/programs/3"),
    notice("profile-1", "tutor_profile_approved", true, today, "https://outside.example"),
    notice("report-1", "report_resolved", true, yesterday, "//outside.example"),
    notice("unknown-1", "unknown_event", false, yesterday, null),
  ]);

  render(<Notifications />);

  expect(await screen.findByRole("heading", { name: "Thông báo" })).toBeVisible();
  expect(await screen.findByRole("link", { name: "Xem nội dung" })).toHaveAttribute("href", "/programs/3");
  expect(screen.getByText("Thông báo mới")).toBeVisible();
  expect(screen.getAllByRole("link", { name: "Xem nội dung" })).toHaveLength(1);
  expect(screen.getByRole("region", { name: "Hôm nay (2)" })).toBeVisible();
  expect(screen.getByRole("region", { name: "Trước đó" })).toBeVisible();

  await userEvent.click(screen.getByRole("button", { name: /Chưa đọc/ }));
  expect(screen.getByText("Bạn vừa nhận được lời mời tham gia một chương trình học.")).toBeVisible();
  expect(screen.queryByText("Hồ sơ đã được duyệt")).not.toBeInTheDocument();

  await userEvent.click(screen.getByRole("button", { name: "Hồ sơ & Tài khoản" }));
  expect(screen.getByText("Hồ sơ đã được duyệt")).toBeVisible();
  expect(screen.queryByText("Bạn vừa nhận được lời mời tham gia một chương trình học.")).not.toBeInTheDocument();

  await userEvent.click(screen.getByRole("button", { name: "Hệ thống" }));
  expect(screen.getByRole("heading", { name: "Báo cáo đã được giải quyết" })).toBeVisible();
  expect(screen.getByText("Bạn có cập nhật mới trong tài khoản TutorLink.")).toBeVisible();
  expect(screen.queryByText("Hồ sơ đã được duyệt")).not.toBeInTheDocument();
});

it("loads an older page and de-duplicates notices already shown", async () => {
  respondWith([
    notice("duplicate", "program_paused", false, "2026-11-25T08:00:00Z", "/programs/3"),
  ], 2);

  render(<Notifications />);

  expect(await screen.findByRole("heading", { name: "Thông báo" })).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: /Tải thêm thông báo cũ/ }));

  expect(await screen.findByRole("region", { name: "Trước đó" })).toBeVisible();
  expect(screen.getAllByText("Chương trình học của bạn hiện đang tạm dừng.")).toHaveLength(2);
  expect(workspaceRead).toHaveBeenCalledWith("/notifications", { page: 2, unread: "0" });
});

it("does not delete a notice when confirmation is declined", async () => {
  respondWith([notice("notice/with space", "listing_moderation_hidden", false, new Date().toISOString(), null)]);
  vi.spyOn(window, "confirm").mockReturnValue(false);

  render(<Notifications />);

  const title = "Dịch vụ đã được ẩn";
  await screen.findByRole("heading", { name: title });
  await userEvent.click(screen.getByRole("button", { name: `Thao tác với thông báo: ${title}` }));
  await userEvent.click(screen.getByRole("menuitem", { name: "Xóa thông báo" }));
  expect(request).not.toHaveBeenCalled();
  expect(screen.getByRole("heading", { name: title })).toBeVisible();
});

it("deletes a confirmed notice", async () => {
  respondWith([notice("notice/with space", "listing_moderation_hidden", false, new Date().toISOString(), null)]);
  vi.spyOn(window, "confirm").mockReturnValue(true);
  render(<Notifications />);

  const title = "Dịch vụ đã được ẩn";
  await screen.findByRole("heading", { name: title });
  await userEvent.click(screen.getByRole("button", { name: `Thao tác với thông báo: ${title}` }));
  await userEvent.click(screen.getByRole("menuitem", { name: "Xóa thông báo" }));
  expect(screen.queryByRole("heading", { name: title })).not.toBeInTheDocument();
  expect(request).toHaveBeenCalledWith({ method: "DELETE", url: "/notifications/notice%2Fwith%20space" }, true);
});

it("keeps a notice visible and alerts when deletion fails", async () => {
  respondWith([notice("notice/with space", "listing_moderation_hidden", false, new Date().toISOString(), null)]);
  vi.spyOn(window, "confirm").mockReturnValue(true);
  vi.spyOn(window, "alert").mockImplementation(() => undefined);
  vi.mocked(request).mockRejectedValueOnce(new Error("network"));
  render(<Notifications />);

  const title = "Dịch vụ đã được ẩn";
  await screen.findByRole("heading", { name: title });
  await userEvent.click(screen.getByRole("button", { name: `Thao tác với thông báo: ${title}` }));
  await userEvent.click(screen.getByRole("menuitem", { name: "Xóa thông báo" }));
  expect(window.alert).toHaveBeenCalledWith("Không thể xóa thông báo. Vui lòng thử lại.");
  expect(screen.getByRole("heading", { name: title })).toBeVisible();
});
