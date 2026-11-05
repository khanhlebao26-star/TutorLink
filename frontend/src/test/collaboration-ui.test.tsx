import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
import type { Program, Invitation } from "@/lib/data/collaboration";
import { ProgramEditor, ProgramDetail } from "@/components/programs";
import { InvitationDetail } from "@/components/invitations";
import { Messaging } from "@/components/messaging";
import { ApiClientError } from "@/lib/api/client";
import * as collaboration from "@/lib/data/collaboration";
import * as messaging from "@/lib/data/messaging";
import * as services from "@/lib/data/services";
const { push } = vi.hoisted(() => ({ push: vi.fn() }));
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  usePathname: () => "/programs",
}));
vi.mock("@/components/app-shell", () => ({
  useActor:()=>({id:7,role:"customer"}),
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));
vi.mock("@/lib/data/collaboration", async (original) => ({
  ...(await original<typeof import("@/lib/data/collaboration")>()),
  getProgram: vi.fn(),
  getOverview: vi.fn(),
  editProgram: vi.fn(),
  getInvitation: vi.fn(),
  decideInvitation: vi.fn(),
}));
vi.mock("@/lib/data/messaging", async (original) => ({
  ...(await original<typeof import("@/lib/data/messaging")>()),
  conversations: vi.fn(),
  conversation: vi.fn(),
  messages: vi.fn(),
  markRead: vi.fn(),
  sendMessage: vi.fn(),
}));
vi.mock("@/lib/data/services", () => ({ getCurrentUser: vi.fn() }));
const program: Program = {
  id: 3,
  customer_id: 7,
  source_listing_id: null,
  title: "English",
  goal: "Confidence",
  level: "A2",
  delivery_mode: "online",
  agreed_fee_minor: 300000,
  currency: "VND",
  fee_unit: "session",
  timezone: "Asia/Ho_Chi_Minh",
  location_text: null,
  meeting_url: null,
  schedule_note: null,
  planned_session_count: 8,
  status: "draft",
  version: 4,
  allowed_actions: ["edit", "send_invitation"],
};
beforeEach(() => {
  vi.clearAllMocks();
  Object.defineProperty(document, "visibilityState", {
    configurable: true,
    value: "visible",
  });
  Object.defineProperty(navigator, "onLine", {
    configurable: true,
    value: true,
  });
  vi.mocked(services.getCurrentUser).mockResolvedValue({
    id: 7,
    role: "customer",
  } as never);
});
it("keeps the draft after a stale edit and sends the loaded version", async () => {
  vi.mocked(collaboration.getProgram).mockResolvedValue(program);
  vi.mocked(collaboration.editProgram).mockRejectedValue(
    new ApiClientError("Version conflict", { status: 409 }),
  );
  render(<ProgramEditor id={3} />);
  const input = await screen.findByLabelText("Tên chương trình học");
  await userEvent.clear(input);
  await userEvent.type(input, "My revised title");
  await userEvent.click(screen.getByRole("button", { name: "Lưu bản nháp" }));
  expect(await screen.findByText("Version conflict")).toBeVisible();
  expect(input).toHaveValue("My revised title");
  expect(collaboration.editProgram).toHaveBeenCalledWith(
    3,
    expect.objectContaining({ title: "My revised title" }),
    4,
  );
  expect(
    screen.getByRole("button", { name: "Gửi lời mời từ bản đã lưu" }),
  ).toBeDisabled();
});
it("does not fetch workspace summary for a draft", async () => {
  vi.mocked(collaboration.getProgram).mockResolvedValue(program);
  render(<ProgramDetail id={3} />);
  await screen.findByText(
    "Workspace sẽ mở sau khi học viên chấp nhận lời mời.",
  );
  expect(collaboration.getOverview).not.toHaveBeenCalled();
});
it("accepts the invitation snapshot and enters the resulting workspace", async () => {
  vi.mocked(collaboration.getInvitation).mockResolvedValue({
    id: 5,
    program_id: 3,
    status: "pending",
    expires_at: "2099-01-01T00:00:00Z",
    offer_snapshot: {
      target_customer_id: 7,
      program_id: 3,
      offer: program,
      source_listing: null,
    },
  } as Invitation);
  vi.mocked(collaboration.decideInvitation).mockResolvedValue();
  render(<InvitationDetail id={5} />);
  await userEvent.click(
    await screen.findByRole("button", { name: "Chấp nhận lời mời" }),
  );
  expect(collaboration.decideInvitation).toHaveBeenCalledWith(
    5,
    "accept",
    expect.any(String),
  );
  expect(push).toHaveBeenCalledWith("/programs/3");
});
it("retries a failed text message with its original UUID and has no attachment input", async () => {
  const c = {
    id: 8,
    status: "active",
    peer: { id: 2, display_name: "Tutor Anna" },
    context_listing_id: null,
    unread_count: 1,
    last_read_message_id: null,
    preview: null,
  };
  vi.mocked(messaging.conversations).mockResolvedValue({
    data: [c],
    meta: { has_more: false, next_cursor: null, per_page: 20 },
  });
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages).mockResolvedValue({
    data: [],
    meta: {
      per_page: 20,
      has_more: false,
      next_cursor: null,
      polling_cursor: "initial",
    },
  });
  vi.mocked(messaging.sendMessage)
    .mockRejectedValueOnce(new ApiClientError("Network failed"))
    .mockResolvedValueOnce({
      id: 10,
      conversation_id: 8,
      sender_id: 7,
      client_message_id: "key",
      message_type: "text",
      body: "Hello",
      created_at: "2026-11-05T00:00:00Z",
    });
  const { container } = render(<Messaging id={8} />);
  const input = await screen.findByLabelText("Nội dung tin nhắn");
  await userEvent.type(input, "Hello");
  await userEvent.click(screen.getByRole("button", { name: "Gửi tin nhắn" }));
  await userEvent.click(
    await screen.findByRole("button", { name: "Thử gửi lại" }),
  );
  await waitFor(() => expect(messaging.sendMessage).toHaveBeenCalledTimes(2));
  expect(vi.mocked(messaging.sendMessage).mock.calls[0]).toEqual(
    vi.mocked(messaging.sendMessage).mock.calls[1],
  );
  expect(container.querySelector('input[type="file"]')).toBeNull();
  await waitFor(() => expect(input).toHaveValue(""));
});
