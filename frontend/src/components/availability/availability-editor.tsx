"use client";

import { Button, ErrorActions, Field, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { validateAvailability, weekdayLabels } from "@/lib/data/availability-validation";
import { getOwnerAvailability, saveOwnerAvailability } from "@/lib/data/marketplace";
import type { Availability } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Link from "next/link";
import { useCallback, useState } from "react";

export function AvailabilityEditor() {
  const load = useCallback(() => getOwnerAvailability(), []);
  const result = useAsyncData(load);
  const needsProfile = result.error?.status === 422;
  return <section className="availability-page content-stack gap-4 sm:gap-[18px]">
    <div><h1>Lịch khả dụng</h1><p className="muted">Thiết lập các khung giờ bạn có thể dạy, lặp lại mỗi tuần.</p></div>
    <p className="availability-notice rounded-xl px-[15px] py-[13px] leading-[1.5] sm:px-[18px] sm:py-[14px]"><strong>Lịch chỉ để tham khảo, không giữ chỗ.</strong><br />Bạn và học viên cần trao đổi để thống nhất lịch học trực tiếp.</p>
    {result.loading ? <StatePanel kind="loading" message="Đang tải lịch khả dụng…" /> : needsProfile ? (
      <StatePanel kind="empty" message="Hãy tạo và lưu bản nháp hồ sơ gia sư để thiết lập lịch khả dụng."
        action={<Link className="button button-secondary" href="/profile">Mở Hồ sơ Tutor</Link>} />
    ) : result.error ? <StatePanel kind="error" message={describeApiError(result.error)} action={<ErrorActions login={result.error.status === 401 || result.error.status === 419} onRetry={result.reload} />} /> : result.data ? <ScheduleForm initial={result.data} /> : null}
  </section>;
}

function ScheduleForm({ initial }: { initial: Availability }) {
  const [value, setValue] = useState(initial);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<ApiClientError | null>(null);
  const [fields, setFields] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState("");
  const [latest, setLatest] = useState<Availability | null>(null);
  const change = (next: Availability) => { setValue(next); setFields({}); setMessage(""); };
  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending) return;
    const validation = validateAvailability(value);
    setFields(validation); setError(null); setMessage("");
    if (Object.keys(validation).length) return;
    setPending(true);
    try { await saveOwnerAvailability(value); setMessage("Đã lưu lịch khả dụng."); setLatest(null); }
    catch (reason) {
      const failure = reason instanceof ApiClientError ? reason : new ApiClientError("Không thể lưu lịch. Hãy thử lại.");
      setError(failure); setFields(failure.fieldErrors ?? {});
      if (failure.status === 409) {
        try { setLatest(await getOwnerAvailability()); }
        catch { /* Preserve the draft even when conflict refetch is unavailable. */ }
      }
    } finally { setPending(false); }
  }
  return <form className="card availability-form grid gap-3 px-[14px] py-4 sm:gap-[14px] sm:px-[clamp(16px,1.8vw,26px)] sm:py-5 [&>.button-row>.button]:w-full sm:[&>.button-row>.button]:w-auto" onSubmit={save} aria-busy={pending}>
    <fieldset className="grid min-w-0 gap-2.5 sm:gap-3 [&>.field]:w-full sm:[&>.field]:max-w-[360px]" disabled={pending}>
      <legend>Thiết lập lịch theo tuần</legend>
      <Field label="Múi giờ (IANA)" htmlFor="availability-timezone" error={fields.timezone?.[0]} hint="Khung giờ bên dưới theo múi giờ này. Ví dụ Asia/Ho_Chi_Minh."><input id="availability-timezone" value={value.timezone} required onChange={(event) => change({ ...value, timezone: event.target.value })} /></Field>
      {weekdayLabels.map((day, dayIndex) => {
        const weekday = dayIndex;
        const rules = value.rules.map((rule, index) => ({ ...rule, index })).filter((rule) => rule.weekday === weekday);
        return <section className="availability-day grid grid-cols-1 items-start gap-x-3 gap-y-[9px] py-[13px] sm:grid-cols-[92px_minmax(0,1fr)] sm:py-[14px] md:grid-cols-[108px_minmax(0,1fr)]" key={weekday} aria-labelledby={`weekday-${weekday}`}>
          <h2 className="m-0 text-[.9rem] leading-[1.35] sm:mt-2" id={`weekday-${weekday}`}>{day}</h2>
          <div className="availability-slots grid min-w-0 gap-2 has-[>p.muted]:items-center has-[>p.muted]:grid-cols-1 sm:has-[>p.muted]:grid-cols-[minmax(0,1fr)_auto]">
            {!rules.length ? <p className="muted">Chưa có khung giờ</p> : rules.map((rule, slot) => <div className="availability-slot grid min-w-0 grid-cols-2 items-end gap-2 sm:gap-x-3 md:grid-cols-[minmax(0,190px)_minmax(0,190px)_auto]" key={rule.index}>
              <Field label={`${day} — bắt đầu ${slot + 1}`} htmlFor={`start-${rule.index}`} error={fields[`rules.${rule.index}.start_time`]?.[0]}><input type="time" id={`start-${rule.index}`} required value={rule.start_time} onChange={(event) => change({ ...value, rules: value.rules.map((item, index) => index === rule.index ? { ...item, start_time: event.target.value } : item) })} /></Field>
              <Field label={`${day} — kết thúc ${slot + 1}`} htmlFor={`end-${rule.index}`} error={fields[`rules.${rule.index}.end_time`]?.[0]}><input type="time" id={`end-${rule.index}`} required value={rule.end_time} onChange={(event) => change({ ...value, rules: value.rules.map((item, index) => index === rule.index ? { ...item, end_time: event.target.value } : item) })} /></Field>
              <Button className="col-span-full !min-h-[34px] !px-[9px] !py-1.5 !text-[.78rem] !justify-self-start md:col-span-1 md:!min-h-[42px]" variant="ghost" aria-label={`Bỏ khung giờ ${slot + 1} ${day}`} onClick={() => change({ ...value, rules: value.rules.filter((_, index) => index !== rule.index) })}>Bỏ khung giờ</Button>
            </div>)}
            <Button className="!text-accent-dark" variant="ghost" onClick={() => change({ ...value, rules: [...value.rules, { weekday, start_time: "09:00", end_time: "12:00" }] })}>+ Thêm khung giờ {day}</Button>
          </div>
        </section>;
      })}
    </fieldset>
    {fields.rules ? <p role="alert" className="field-error">{fields.rules.join(" ")}</p> : null}
    {error ? <div role="alert"><p className="field-error">{describeApiError(error)}{error.status === 409 ? " Dữ liệu đã thay đổi. Bản đang nhập được giữ lại để bạn đối chiếu." : ""}</p>{error.status === 401 || error.status === 419 ? <Link className="text-button" href="/auth/login" target="_blank" rel="noopener noreferrer">Đăng nhập trong tab mới rồi thử lưu lại</Link> : null}</div> : null}
    {latest ? <details><summary>Xem lịch mới nhất từ máy chủ</summary><p>{latest.timezone}</p>{latest.rules.map((rule, index) => <p key={index}>{weekdayLabels[rule.weekday]}: {rule.start_time}–{rule.end_time}</p>)}<Button variant="secondary" disabled={pending} onClick={() => { change(latest); setLatest(null); }}>Dùng lịch từ máy chủ</Button></details> : null}
    {message ? <p role="status" className="inline-success">{message}</p> : null}
    <div className="button-row"><Button type="submit" disabled={pending}>{pending ? "Đang lưu…" : "Lưu lịch khả dụng"}</Button></div>
  </form>;
}
