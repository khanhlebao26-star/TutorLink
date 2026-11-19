"use client";

import { SessionCreateDialog, SessionDetailDialog } from "@/components/programs/sessions/session-dialogs";
import {
  type WorkspaceRow
} from "@/lib/data/workspace";
import "@/styles/programs.css";
import { useState, type MouseEvent } from "react";

const calendarHours = Array.from({ length: 17 }, (_, index) => index + 6);
const calendarHourHeight = 56;
const weekdayLabels = ["CN", "T2", "T3", "T4", "T5", "T6", "T7"];

function weekStartOf(date: Date) {
  const start = new Date(date);
  start.setDate(start.getDate() - start.getDay());
  start.setHours(0, 0, 0, 0);
  return start;
}

function sameCalendarDay(left: Date, right: Date) {
  return left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate();
}

export function SessionCalendar({
  id,
  rows,
  active,
  writable,
  canCreate,
  onSaved,
}: {
  id: number;
  rows: WorkspaceRow[];
  active: boolean;
  writable: boolean;
  canCreate: boolean;
  onSaved: () => void;
}) {
  const [weekStart, setWeekStart] = useState(() => weekStartOf(new Date()));
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [newSlot, setNewSlot] = useState<{ start: Date; end: Date } | null>(null);
  const days = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(weekStart);
    date.setDate(date.getDate() + index);
    return date;
  });
  const selected = rows.find((row) => row.id === selectedId) ?? null;
  const weekEnd = days[6];
  const weekTitle = weekStart.getMonth() === weekEnd.getMonth()
    ? weekStart.toLocaleDateString("vi-VN", { month: "long", year: "numeric" })
    : `${weekStart.toLocaleDateString("vi-VN", { month: "short" })} – ${weekEnd.toLocaleDateString("vi-VN", { month: "short", year: "numeric" })}`;
  const notScheduled = rows.filter((row) => !row.planned_starts_at);

  function moveWeek(amount: number) {
    setWeekStart((current) => {
      const next = new Date(current);
      next.setDate(next.getDate() + amount * 7);
      return next;
    });
  }

  function openNewSession(day: Date, hour: number, event: MouseEvent<HTMLButtonElement>) {
    if (!canCreate) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    const pointerOffset = Math.max(0, Math.min(bounds.height, event.clientY - bounds.top));
    const minutes = Math.min(30, Math.round(pointerOffset / bounds.height * 2) * 30);
    const start = new Date(day);
    start.setHours(hour, minutes, 0, 0);
    const end = new Date(start.getTime() + 60 * 60 * 1000);
    setNewSlot({ start, end });
  }

  return <div className="session-calendar-wrap">
    <div className="session-calendar-toolbar">
      <div className="session-calendar-controls">
        <button type="button" className="session-calendar-today" onClick={() => setWeekStart(weekStartOf(new Date()))}>Hôm nay</button>
        <button type="button" className="session-calendar-step" aria-label="Tuần trước" onClick={() => moveWeek(-1)}>‹</button>
        <button type="button" className="session-calendar-step" aria-label="Tuần sau" onClick={() => moveWeek(1)}>›</button>
        <h2>{weekTitle.charAt(0).toLocaleUpperCase("vi-VN") + weekTitle.slice(1)}</h2>
      </div>
      <span className="session-calendar-view-label">Tuần</span>
    </div>
    <div className="session-calendar-scroll">
      <div className="session-calendar-grid" style={{ gridTemplateColumns: "58px repeat(7, minmax(96px, 1fr))" }}>
        <div className="session-calendar-day-head session-calendar-time-spacer" />
        {days.map((day, index) => <div key={day.toISOString()} className={`session-calendar-day-head${sameCalendarDay(day, new Date()) ? " is-today" : ""}`}>
          <span>{weekdayLabels[index]}</span><strong>{day.getDate()}</strong>
        </div>)}
        <div className="session-calendar-time-rail" style={{ height: calendarHours.length * calendarHourHeight }}>
          {calendarHours.map((hour) => <span key={hour} style={{ height: calendarHourHeight }}>{`${String(hour).padStart(2, "0")}:00`}</span>)}
        </div>
        {days.map((day) => {
          const dayEvents = rows.flatMap((row) => {
            if (!row.planned_starts_at) return [];
            const start = new Date(row.planned_starts_at);
            if (Number.isNaN(start.getTime()) || !sameCalendarDay(start, day)) return [];
            const parsedEnd = row.planned_ends_at ? new Date(row.planned_ends_at) : null;
            const end = parsedEnd && !Number.isNaN(parsedEnd.getTime()) ? parsedEnd : new Date(start.getTime() + 60 * 60 * 1000);
            const startOffset = (start.getHours() - 6) * 60 + start.getMinutes();
            const endOffset = (end.getHours() - 6) * 60 + end.getMinutes();
            const top = Math.max(0, startOffset / 60 * calendarHourHeight);
            const bottom = Math.min(calendarHours.length * calendarHourHeight, endOffset / 60 * calendarHourHeight);
            if (bottom <= 0 || top >= calendarHours.length * calendarHourHeight) return [];
            return [{ row, start, end, top: Math.max(0, top), height: Math.max(36, bottom - Math.max(0, top)) }];
          });
          return <div key={day.toISOString()} className={`session-calendar-day-column${sameCalendarDay(day, new Date()) ? " is-today" : ""}`} style={{ height: calendarHours.length * calendarHourHeight }}>
            {canCreate ? calendarHours.map((hour) => <button key={`slot-${hour}`} type="button" className="session-calendar-slot" style={{ top: (hour - 6) * calendarHourHeight, height: calendarHourHeight }} aria-label={`Lên lịch vào ${day.toLocaleDateString("vi-VN", { weekday: "long", day: "numeric", month: "long" })} lúc ${String(hour).padStart(2, "0")}:00`} onClick={(event) => openNewSession(day, hour, event)} />) : null}
            {calendarHours.map((hour) => <div key={hour} className="session-calendar-hour-line" style={{ top: (hour - 6) * calendarHourHeight }} />)}
            {dayEvents.map(({ row, start, end, top, height }) => <button key={row.id} type="button" className={`session-calendar-event is-${row.status ?? "scheduled"}`} style={{ top, height }} onClick={(event) => { event.stopPropagation(); setSelectedId(row.id); }} aria-label={`Mở thông tin ${row.title || "buổi học"}, ${start.toLocaleString("vi-VN")}`}>
              <strong>{start.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })} {row.title || "Buổi học"}</strong>
              <span>{end.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })}</span>
            </button>)}
          </div>;
        })}
      </div>
    </div>
    {notScheduled.length ? <div className="session-calendar-unscheduled"><strong>Chưa xếp lịch</strong>{notScheduled.map((row) => <button key={row.id} type="button" onClick={() => setSelectedId(row.id)}>{row.title || `Buổi học #${row.id}`} <span>Chọn để xem chi tiết →</span></button>)}</div> : null}
    {selected ? <SessionDetailDialog row={selected} active={active} writable={writable} onSaved={onSaved} onClose={() => setSelectedId(null)} /> : null}
    {newSlot ? <SessionCreateDialog id={id} start={newSlot.start} end={newSlot.end} onSaved={() => { onSaved(); setNewSlot(null); }} onClose={() => setNewSlot(null)} /> : null}
  </div>;
}
