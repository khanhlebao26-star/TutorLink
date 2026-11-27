import { Messaging } from "@/components/messaging/messaging";
import { InvitationDetail, Invitations } from "@/components/programs/invitations";
import { MessageAttachment } from "@/components/messaging/message-attachment";
import { ProgramDetail } from "@/components/programs/program-detail";
import { ProgramEditor } from "@/components/programs/program-editor";
import { ApiClientError } from "@/lib/api/client";
import type { Invitation, Program } from "@/lib/data/collaboration";
import * as collaboration from "@/lib/data/collaboration";
import * as messaging from "@/lib/data/messaging";
import * as services from "@/lib/data/services";
import * as apiClient from "@/lib/api/client";
import { act, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
const { push } = vi.hoisted(() => ({ push: vi.fn() }));
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  usePathname: () => "/programs",
}));
vi.mock("@/components/layout/app-shell", () => ({
  useActor:()=>({id:7,role:"customer"}),
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));
vi.mock("@/lib/data/collaboration", async (original) => ({
  ...(await original<typeof import("@/lib/data/collaboration")>()),
  getProgram: vi.fn(),
  getOverview: vi.fn(),
  editProgram: vi.fn(),
  getInvitation: vi.fn(),
  listInvitations: vi.fn(),
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
vi.mock("@/lib/data/services", async (original) => ({
  ...(await original<typeof import("@/lib/data/services")>()),
  getCurrentUser: vi.fn(),
  uploadFile: vi.fn(),
  completeFile: vi.fn(),
}));
vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  request: vi.fn(),
}));
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
  vi.mocked(messaging.conversations).mockResolvedValue({
    data: [], meta: { per_page: 20, has_more: false, next_cursor: null },
  });
  vi.mocked(collaboration.listInvitations).mockResolvedValue({
    data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
  });
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
it("retries a failed text message with its original UUID without attaching any files", async () => {
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
  render(<Messaging id={8} />);
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
  expect(vi.mocked(messaging.sendMessage).mock.calls[0][3]).toEqual([]);
  await waitFor(() => expect(input).toHaveValue(""));
});

it("does not show failed-message actions while a message is sending successfully", async () => {
  const c = {
    id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna" },
    context_listing_id: null, unread_count: 0, last_read_message_id: null, preview: null,
  };
  vi.mocked(messaging.conversations).mockResolvedValue({ data: [c], meta: { has_more: false, next_cursor: null, per_page: 20 } });
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages).mockResolvedValue({ data: [], meta: { per_page: 20, has_more: false, next_cursor: null } });
  let complete!: (message: Awaited<ReturnType<typeof messaging.sendMessage>>) => void;
  vi.mocked(messaging.sendMessage).mockImplementationOnce(() => new Promise((resolve) => { complete = resolve; }));

  render(<Messaging id={8}/>);
  const input = await screen.findByLabelText("Nội dung tin nhắn");
  await userEvent.type(input, "Hello without a warning");
  await userEvent.click(screen.getByRole("button", { name: "Gửi tin nhắn" }));

  expect(input).toBeDisabled();
  expect(screen.queryByRole("button", { name: "Thử gửi lại" })).not.toBeInTheDocument();
  expect(screen.queryByRole("button", { name: "Chỉnh sửa tin nhắn" })).not.toBeInTheDocument();

  await act(async () => complete({
    id: 10, conversation_id: 8, sender_id: 7, client_message_id: "key",
    message_type: "text", body: "Hello without a warning", created_at: "2026-11-12T00:00:00Z",
  }));
  expect(screen.getByText("Hello without a warning")).toBeVisible();
  expect(input).toHaveValue("");
  expect(input).toBeEnabled();
  expect(screen.queryByRole("button", { name: "Thử gửi lại" })).not.toBeInTheDocument();
});

it("shows an unselected conversation screen and inbox skeleton before the first list arrives", async () => {
  let finish!: (page: Awaited<ReturnType<typeof messaging.conversations>>) => void;
  vi.mocked(messaging.conversations).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  render(<Messaging/>);
  expect(screen.getByRole("heading", { name: "Chưa chọn cuộc trò chuyện nào" })).toBeVisible();
  expect(screen.getByRole("status", { name: "Đang tải danh sách cuộc trò chuyện" })).toBeVisible();
  expect(screen.queryByText("Chưa có cuộc trò chuyện")).not.toBeInTheDocument();
  expect(messaging.conversation).not.toHaveBeenCalled();
  expect(messaging.messages).not.toHaveBeenCalled();
  await act(async () => finish({ data: [], meta: { per_page: 20, has_more: false, next_cursor: null } }));
  expect(screen.getByText("Chưa có cuộc trò chuyện")).toBeVisible();
});

it("loads a thread independently of a slow inbox and keeps the inbox when switching threads", async () => {
  const c = { id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna" }, context_listing_id: null, unread_count: 0, last_read_message_id: null, preview: null };
  let finish!: (page: Awaited<ReturnType<typeof messaging.conversations>>) => void;
  vi.mocked(messaging.conversations).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  vi.mocked(messaging.conversation).mockImplementation(async (id) => ({ ...c, id }));
  vi.mocked(messaging.messages).mockResolvedValue({ data: [], meta: { per_page: 20, has_more: false, next_cursor: null } });
  const view = render(<Messaging id={8}/>);
  await screen.findByLabelText("Nội dung tin nhắn");
  expect(screen.getByRole("status", { name: "Đang tải danh sách cuộc trò chuyện" })).toBeVisible();
  await act(async () => finish({ data: [c], meta: { per_page: 20, has_more: false, next_cursor: null } }));
  expect(screen.getByRole("link", { name: /Tutor Anna/ })).toBeVisible();
  view.rerender(<Messaging id={9}/>);
  expect(screen.getByRole("link", { name: /Tutor Anna/ })).toBeVisible();
  expect(screen.queryByRole("status", { name: "Đang tải danh sách cuộc trò chuyện" })).not.toBeInTheDocument();
  expect(messaging.conversations).toHaveBeenCalledTimes(1);
  await waitFor(() => expect(messaging.conversation).toHaveBeenCalledWith(9, expect.any(AbortSignal)));
});

it("filters an invitation inbox by status and opens an accepted program", async () => {
  const offer = {
    ...program,
    title: "English conversation",
    status: "active" as const,
  };
  vi.mocked(collaboration.listInvitations).mockResolvedValue({
    data: [
      {
        id: 5, program_id: 3, status: "pending", expires_at: "2099-01-01T00:00:00Z",
        offer_snapshot: { target_customer_id: 7, program_id: 3, offer, source_listing: null },
      },
      {
        id: 6, program_id: 4, status: "accepted", expires_at: "2099-01-01T00:00:00Z",
        offer_snapshot: { target_customer_id: 7, program_id: 4, offer: { ...offer, title: "Accepted program" }, source_listing: null },
      },
    ],
    meta: { current_page: 1, per_page: 20, total: 2, last_page: 1 },
  });

  render(<Invitations embedded />);
  expect(await screen.findByRole("heading", { name: "Lời mời Program" })).toBeVisible();
  expect(screen.getByRole("button", { name: /English conversation/ })).toBeVisible();
  await userEvent.click(within(screen.getByRole("navigation", { name: "Lọc trạng thái lời mời" })).getByRole("button", { name: /Đã chấp nhận/ }));
  expect(screen.getByRole("article")).toHaveTextContent("Accepted program");
  expect(screen.getByRole("link", { name: /Mở workspace/ })).toHaveAttribute("href", "/programs/4");
  expect(screen.queryByText("Hiện chưa có lời mời đã chấp nhận.")).not.toBeInTheDocument();
});

it("accepts an invitation from the inbox and routes to its program", async () => {
  const offer = { ...program, title: "English conversation" };
  vi.mocked(collaboration.listInvitations).mockResolvedValue({
    data: [{
      id: 5, program_id: 3, status: "pending", expires_at: "2099-01-01T00:00:00Z",
      offer_snapshot: { target_customer_id: 7, program_id: 3, offer, source_listing: null },
    }],
    meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
  });
  vi.mocked(collaboration.decideInvitation).mockResolvedValue();

  render(<Invitations />);
  await userEvent.click(await screen.findByRole("button", { name: /Chấp nhận lời mời/ }));

  expect(collaboration.decideInvitation).toHaveBeenCalledWith(5, "accept", expect.any(String));
  expect(push).toHaveBeenCalledWith("/programs/3");
});

it("uploads an attachment, sends its file id, and removes it from the composer", async () => {
  const c = {
    id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna", tutor_slug: "anna", tutor_headline: "English tutor" },
    context_listing_id: 4, unread_count: 0, last_read_message_id: null, preview: null,
  };
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages).mockResolvedValue({
    data: [], meta: { per_page: 20, has_more: false, next_cursor: null },
  });
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 18, original_name: "lesson.pdf", complete: false } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 18, original_name: "lesson.pdf", complete: true } as never);
  vi.mocked(messaging.sendMessage).mockResolvedValue({
    id: 22, conversation_id: 8, sender_id: 7, client_message_id: "key",
    message_type: "text", body: "Here is the lesson", created_at: "2026-11-05T00:00:00Z",
    attachments: [{ id: 18, name: "lesson.pdf", mime_type: "application/pdf", size_bytes: 1200 }],
  });

  render(<Messaging id={8} />);
  await screen.findByLabelText("Nội dung tin nhắn");
  await userEvent.upload(screen.getByLabelText("Chọn tệp đính kèm"), new File(["lesson"], "lesson.pdf", { type: "application/pdf" }));
  expect(await screen.findByText("lesson.pdf")).toBeVisible();
  await userEvent.type(screen.getByLabelText("Nội dung tin nhắn"), "Here is the lesson");
  await userEvent.click(screen.getByRole("button", { name: "Gửi tin nhắn" }));
  await waitFor(() => expect(messaging.sendMessage).toHaveBeenCalledWith(8, "Here is the lesson", expect.any(String), [18]));
  expect(screen.getByRole("link", { name: /Xem hồ sơ gia sư/ })).toHaveAttribute("href", "/tutors/anna");
  expect(screen.getByText("DỊCH VỤ ĐANG TRAO ĐỔI")).toBeVisible();
});

it("shows older messages and keeps a closed conversation read-only", async () => {
  const c = {
    id: 8, status: "closed", peer: { id: 2, display_name: "Tutor Anna", tutor_headline: "English tutor" },
    context_listing_id: null, unread_count: 0, last_read_message_id: null, preview: null,
  };
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages)
    .mockResolvedValueOnce({ data: [{ id: 2, conversation_id: 8, sender_id: 2, body: "Latest", created_at: "2026-11-27T00:00:00Z" }], meta: { per_page: 20, has_more: true, next_cursor: "older", polling_cursor: "latest" } } as never)
    .mockResolvedValueOnce({ data: [{ id: 1, conversation_id: 8, sender_id: 7, body: "Earlier", created_at: "2026-11-26T00:00:00Z" }], meta: { per_page: 20, has_more: false, next_cursor: null } } as never);

  render(<Messaging id={8} />);
  expect(await screen.findByText("Latest")).toBeVisible();
  expect(screen.getByRole("button", { name: "Tải tin cũ hơn" })).toBeEnabled();
  await userEvent.click(screen.getByRole("button", { name: "Tải tin cũ hơn" }));
  expect(await screen.findByText("Earlier")).toBeVisible();
  expect(screen.getByLabelText("Nội dung tin nhắn")).toBeDisabled();
  expect(screen.getByRole("button", { name: "Đính kèm tệp" })).toBeDisabled();
  expect(screen.getByText("Cuộc trò chuyện đã đóng")).toBeVisible();
});

it("downloads a message attachment and reports a failed download", async () => {
  const file = { id: 77, name: "notes.pdf", mime_type: "application/pdf", size_bytes: 2048 };
  const createUrl = vi.spyOn(URL, "createObjectURL").mockReturnValue("blob:test-file");
  const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
  vi.mocked(apiClient.request).mockResolvedValue({ data: new Blob(["notes"]) } as never);
  const view = render(<MessageAttachment file={file} />);
  await userEvent.click(screen.getByRole("button", { name: /notes.pdf/ }));
  await waitFor(() => expect(apiClient.request).toHaveBeenCalledWith({ url: "/files/77/download", responseType: "blob" }));
  expect(createUrl).toHaveBeenCalled();
  expect(click).toHaveBeenCalledTimes(1);

  vi.mocked(apiClient.request).mockRejectedValueOnce(new ApiClientError("Download denied", { status: 403 }));
  view.rerender(<MessageAttachment file={{ ...file, id: 78 }} />);
  await userEvent.click(screen.getByRole("button", { name: /notes.pdf/ }));
  expect(await screen.findByRole("alert")).toHaveTextContent("Download denied");
});

it("loads the next inbox page, replaces duplicate conversations, and filters by peer name", async () => {
  const today = new Date().toISOString();
  const yesterdayDate = new Date();
  yesterdayDate.setDate(yesterdayDate.getDate() - 1);
  const firstPage = [
    { id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna" }, unread_count: 1, preview: { text: "Hello", created_at: today } },
    { id: 9, status: "active", peer: { id: 3, display_name: "Tutor Ben" }, unread_count: 0, preview: { text: "Yesterday", created_at: yesterdayDate.toISOString() } },
  ];
  vi.mocked(messaging.conversations)
    .mockResolvedValueOnce({ data: firstPage, meta: { per_page: 20, has_more: true, next_cursor: "next" } } as never)
    .mockResolvedValueOnce({ data: [{ ...firstPage[0], preview: { text: "Updated preview", created_at: today } }, { ...firstPage[1], id: 10, peer: { id: 4, display_name: "Tutor Chi" } }], meta: { per_page: 20, has_more: false, next_cursor: null } } as never);
  render(<Messaging />);

  expect(await screen.findByRole("link", { name: /Tutor Anna/ })).toBeVisible();
  expect(screen.getByText("Hôm qua")).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Tải thêm cuộc trò chuyện" }));
  expect(await screen.findByText("Updated preview")).toBeVisible();
  expect(screen.getAllByRole("link", { name: /Tutor Anna/ })).toHaveLength(1);
  await userEvent.type(screen.getByLabelText("Tìm cuộc trò chuyện"), "nobody");
  expect(screen.getByText("Không tìm thấy cuộc trò chuyện")).toBeVisible();
});

it("marks an incoming message as read and restores a failed message for editing", async () => {
  const c = { id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna" }, context_listing_id: null, unread_count: 1, last_read_message_id: null, preview: null };
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages).mockResolvedValue({ data: [{ id: 19, conversation_id: 8, sender_id: 2, body: "Hello from Anna", created_at: new Date().toISOString() }], meta: { per_page: 20, has_more: false, next_cursor: null } } as never);
  vi.mocked(messaging.markRead).mockResolvedValue(undefined);
  vi.mocked(messaging.sendMessage).mockRejectedValueOnce(new ApiClientError("Send failed"));
  render(<Messaging id={8} />);

  expect(await screen.findByText("Hello from Anna")).toBeVisible();
  await waitFor(() => expect(messaging.markRead).toHaveBeenCalledWith(8, 19));
  const input = screen.getByLabelText("Nội dung tin nhắn");
  await userEvent.type(input, "Please edit this");
  await userEvent.click(screen.getByRole("button", { name: "Gửi tin nhắn" }));
  await userEvent.click(await screen.findByRole("button", { name: "Chỉnh sửa tin nhắn" }));
  expect(input).toHaveValue("Please edit this");
  expect(screen.queryByRole("button", { name: "Thử gửi lại" })).not.toBeInTheDocument();
  expect(screen.queryByText("Send failed")).not.toBeInTheDocument();
});

it("removes an uploaded attachment from the composer even if private-file cleanup fails", async () => {
  const c = { id: 8, status: "active", peer: { id: 2, display_name: "Tutor Anna" }, context_listing_id: null, unread_count: 0, last_read_message_id: null, preview: null };
  vi.mocked(messaging.conversation).mockResolvedValue(c);
  vi.mocked(messaging.messages).mockResolvedValue({ data: [], meta: { per_page: 20, has_more: false, next_cursor: null } });
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 19, original_name: "draft.pdf", complete: false } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 19, original_name: "draft.pdf", complete: true } as never);
  vi.mocked(apiClient.request).mockRejectedValue(new ApiClientError("Cleanup unavailable"));
  render(<Messaging id={8} />);
  await screen.findByLabelText("Nội dung tin nhắn");
  await userEvent.upload(screen.getByLabelText("Chọn tệp đính kèm"), new File(["draft"], "draft.pdf", { type: "application/pdf" }));
  expect(await screen.findByText("draft.pdf")).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Gỡ tệp draft.pdf" }));
  await waitFor(() => expect(apiClient.request).toHaveBeenCalledWith({ method: "DELETE", url: "/files/19" }, true));
  expect(screen.queryByText("draft.pdf")).not.toBeInTheDocument();
  expect(screen.queryByRole("alert")).not.toBeInTheDocument();
});
