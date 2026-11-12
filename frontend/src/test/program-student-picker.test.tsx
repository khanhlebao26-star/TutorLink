import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
import { ProgramStudentPicker } from "@/components/program-student-picker";
import { OpenChatButton } from "@/components/open-chat-button";
import { ApiClientError } from "@/lib/api/client";
import * as messaging from "@/lib/data/messaging";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/lib/data/messaging", () => ({ conversations: vi.fn(), openConversation: vi.fn(), sendMessage: vi.fn() }));
const makeConversation = (id: number, name: string, listingId: number | null = null) => ({
  id, status: "active", peer: { id, display_name: name }, context_listing_id: listingId,
  unread_count: 0, last_read_message_id: null, preview: null,
});
beforeEach(() => vi.resetAllMocks());

it("loads later conversation pages, removes duplicate students, and passes the selected student and service", async () => {
  vi.mocked(messaging.conversations)
    .mockResolvedValueOnce({ data: [makeConversation(12, "An")], meta: { has_more: true, next_cursor: "next-page", per_page: 1 } })
    .mockResolvedValueOnce({ data: [makeConversation(12, "An"), makeConversation(25, "Bình", 9)], meta: { has_more: false, next_cursor: null, per_page: 2 } });
  const change = vi.fn();
  render(<ProgramStudentPicker value={0} disabled={false} onChange={change}/>);
  await screen.findByRole("option", { name: "Bình" });
  expect(screen.getAllByRole("option", { name: "An" })).toHaveLength(1);
  expect(messaging.conversations).toHaveBeenLastCalledWith("next-page");
  await userEvent.selectOptions(screen.getByRole("combobox"), "25");
  expect(change).toHaveBeenCalledWith(25, 9);
});

it("lets the tutor retry when loading students fails", async () => {
  vi.mocked(messaging.conversations).mockRejectedValueOnce(new ApiClientError("Không tải được học viên"))
    .mockResolvedValueOnce({ data: [makeConversation(12, "An")], meta: { has_more: false, next_cursor: null, per_page: 20 } });
  render(<ProgramStudentPicker value={0} disabled={false} onChange={vi.fn()}/>);
  await screen.findByRole("alert");
  await userEvent.click(screen.getByRole("button", { name: "Tải lại danh sách" }));
  await screen.findByRole("option", { name: "An" });
  expect(screen.getByRole("combobox")).toBeEnabled();
});

it("explains tutor requirements without blaming the student's account", async () => {
  vi.mocked(messaging.openConversation).mockRejectedValue(new ApiClientError("Gia sư chưa đủ điều kiện", {
    status: 403, code: "tutor_ineligible", fieldErrors: { eligibility: ["Gia sư cần xác minh email tài khoản."] },
  }));
  render(<OpenChatButton peerId={27}/>);
  await userEvent.click(screen.getByRole("button", { name: "Nhắn tin với gia sư" }));
  await userEvent.click(screen.getByRole("button", { name: "Gửi tin nhắn" }));
  await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("Gia sư cần xác minh email tài khoản."));
  expect(screen.getByRole("alert")).toHaveTextContent("Gia sư chưa sẵn sàng nhận tin nhắn");
  expect(messaging.sendMessage).not.toHaveBeenCalled();
});
