import { SessionCalendar } from "@/components/programs/sessions/session-calendar";
import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/components/programs/sessions/session-dialogs", () => ({
  SessionCreateDialog: ({ start, end, onSaved, onClose }: { start: Date; end: Date; onSaved: () => void; onClose: () => void }) => <div role="dialog" aria-label="Tạo buổi học">
    <span>{start.toISOString()} / {end.toISOString()}</span>
    <button onClick={onSaved}>Lưu lịch mới</button><button onClick={onClose}>Đóng lịch mới</button>
  </div>,
  SessionDetailDialog: ({ row, active, writable, onSaved, onClose }: { row: { title: string }; active: boolean; writable: boolean; onSaved: () => void; onClose: () => void }) => <div role="dialog" aria-label="Chi tiết buổi học">
    <span>{row.title} · active={String(active)} · writable={String(writable)}</span>
    <button onClick={onSaved}>Lưu chi tiết</button><button onClick={onClose}>Đóng chi tiết</button>
  </div>,
}));

function sundayOfThisWeek() {
  const date = new Date();
  date.setDate(date.getDate() - date.getDay());
  date.setHours(0, 0, 0, 0);
  return date;
}

beforeEach(() => vi.restoreAllMocks());

it("navigates weeks, returns to today, and displays events, invalid dates, and unscheduled sessions", async () => {
  const user = userEvent.setup();
  const sunday = sundayOfThisWeek();
  const monday = new Date(sunday); monday.setDate(monday.getDate() + 1);
  const inWeek = new Date(monday); inWeek.setHours(9, 30, 0, 0);
  const offHours = new Date(monday); offHours.setHours(5, 0, 0, 0);
  const onSaved = vi.fn();
  const { container } = render(<SessionCalendar id={7} active writable canCreate={false} onSaved={onSaved} rows={[
    { id: 1, title: "Math session", planned_starts_at: inWeek.toISOString(), planned_ends_at: null, status: "completed" },
    { id: 2, title: "Outside calendar", planned_starts_at: offHours.toISOString(), planned_ends_at: null },
    { id: 3, title: "Broken date", planned_starts_at: "not-a-date", planned_ends_at: null },
    { id: 4, title: "Unscheduled", planned_starts_at: null },
  ] as never} />);

  expect(screen.getByRole("button", { name: /Math session/ })).toBeVisible();
  expect(screen.queryByRole("button", { name: /Outside calendar/ })).toBeNull();
  expect(container.querySelectorAll(".session-calendar-slot")).toHaveLength(0);
  await user.click(screen.getByRole("button", { name: /Unscheduled/ }));
  expect(screen.getByRole("dialog", { name: "Chi tiết buổi học" })).toHaveTextContent("Unscheduled · active=true · writable=true");
  await user.click(screen.getByRole("button", { name: "Lưu chi tiết" }));
  expect(onSaved).toHaveBeenCalledOnce();
  await user.click(screen.getByRole("button", { name: "Đóng chi tiết" }));
  await user.click(screen.getByRole("button", { name: "Tuần sau" }));
  expect(screen.queryByRole("button", { name: /Math session/ })).toBeNull();
  await user.click(screen.getByRole("button", { name: "Hôm nay" }));
  expect(screen.getByRole("button", { name: /Math session/ })).toBeVisible();
});

it("opens a new session at a bounded half-hour slot and closes or saves it", async () => {
  const user = userEvent.setup();
  const onSaved = vi.fn();
  const { container } = render(<SessionCalendar id={17} active={false} writable={false} canCreate onSaved={onSaved} rows={[
    { id: 5, title: "Invalid end", planned_starts_at: new Date(sundayOfThisWeek().setHours(9, 0, 0, 0)).toISOString(), planned_ends_at: "invalid" },
  ] as never} />);
  const slot = container.querySelector<HTMLButtonElement>(".session-calendar-slot");
  expect(slot).not.toBeNull();
  vi.spyOn(slot!, "getBoundingClientRect").mockReturnValue({ top: 100, height: 56, bottom: 156, left: 0, right: 100, width: 100, x: 0, y: 100, toJSON: () => ({}) });
  fireEvent.click(slot!, { clientY: 156 });
  const dialog = screen.getByRole("dialog", { name: "Tạo buổi học" });
  const times = dialog.querySelector("span")!.textContent!.match(/\d{4}-\d\d-\d\dT[^ ]+/g)!;
  expect(new Date(times[1]).getTime() - new Date(times[0]).getTime()).toBe(60 * 60 * 1000);
  await user.click(screen.getByRole("button", { name: "Đóng lịch mới" }));
  expect(screen.queryByRole("dialog", { name: "Tạo buổi học" })).toBeNull();
  await user.click(slot!);
  await user.click(screen.getByRole("button", { name: "Lưu lịch mới" }));
  expect(onSaved).toHaveBeenCalledOnce();
  expect(screen.queryByRole("dialog", { name: "Tạo buổi học" })).toBeNull();
});
