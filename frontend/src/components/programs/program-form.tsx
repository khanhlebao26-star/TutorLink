"use client";

import { ProgramStudentPicker } from "@/components/programs/program-student-picker";
import { Button, Card, Field, Input, Select } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import {
  createProgram,
  editProgram,
  mutationKey,
  sendInvitation,
  type Program,
  type ProgramInput
} from "@/lib/data/collaboration";
import { deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import "@/styles/listings.css";
import "@/styles/programs.css";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useRef, useState, type FormEvent } from "react";

export function ProgramForm({
  existing,
  customerId,
  sourceListingId,
}: {
  existing: Program | null;
  customerId?: number;
  sourceListingId?: number;
}) {
  const router = useRouter();
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState<ApiClientError | null>(null),
    [busy, setBusy] = useState(false),
    [saved, setSaved] = useState(existing);
  const key = useRef<string | null>(null);
  const locked = useRef(false);
  const [input, setInput] = useState<ProgramInput>({
    customer_id: existing?.customer_id ?? customerId ?? 0,
    title: existing?.title ?? "",
    delivery_mode: existing?.delivery_mode ?? "online",
    agreed_fee_minor: existing?.agreed_fee_minor ?? 0,
    fee_unit: existing?.fee_unit ?? "session",
    goal: existing?.goal ?? "",
    level: existing?.level ?? "",
    location_text: existing?.location_text ?? "",
    meeting_url: existing?.meeting_url ?? "",
    schedule_note: existing?.schedule_note ?? "",
    timezone: existing?.timezone ?? "Asia/Ho_Chi_Minh",
    planned_session_count: existing?.planned_session_count ?? null,
    source_listing_id: existing?.source_listing_id ?? sourceListingId ?? null,
  });
  function field<K extends keyof ProgramInput>(
    name: K,
    value: ProgramInput[K],
  ) {
    setDirty(true);
    setInput({ ...input, [name]: value });
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (locked.current) return;
    locked.current = true;
    setBusy(true);
    setError(null);
    try {
      const p = saved
        ? await editProgram(saved.id, input, saved.version)
        : await createProgram(input);
      setSaved(p);
      setDirty(false);
      router.push(`/programs/${p.id}`);
    } catch (e) {
      setError(
        e instanceof ApiClientError
          ? e
          : new ApiClientError(describeApiError(e)),
      );
    } finally {
      locked.current = false;
      setBusy(false);
    }
  }
  async function invite() {
    if (!saved || locked.current) return;
    locked.current = true;
    setBusy(true);
    setError(null);
    key.current ??= mutationKey();
    try {
      const invitation = await sendInvitation(
        saved.id,
        saved.version,
        key.current,
      );
      router.push(`/invitations/${invitation.id}`);
    } catch (e) {
      setError(
        e instanceof ApiClientError
          ? e
          : new ApiClientError(describeApiError(e)),
      );
    } finally {
      locked.current = false;
      setBusy(false);
    }
  }
  return (
    <div className="program-builder">
      <header className="program-builder-heading">
        <div><span className="program-builder-kicker">THIẾT KẾ HÀNH TRÌNH HỌC TẬP</span><h1>{existing ? "Chỉnh sửa chương trình học" : "Tạo chương trình học mới"}</h1><p>Thống nhất mục tiêu, lịch học và học phí để bắt đầu đồng hành cùng học viên.</p></div>
        <span className="program-builder-state"><span aria-hidden="true">●</span> {saved ? dirty ? "Có thay đổi chưa lưu" : "Bản nháp đã lưu" : "Bản nháp mới"}</span>
      </header>
      <ol className="program-builder-journey" aria-label="Quy trình tạo chương trình"><li aria-current="step"><span>1</span>Thiết lập chương trình</li><li><span>2</span>Lưu bản nháp</li><li><span>3</span>Gửi lời mời học</li></ol>
      <div className="program-builder-layout">
        <form className="owner-form program-builder-form" onSubmit={submit}>
          {error && (
            <div role="alert" className="program-builder-error">
              <p className="field-error">{describeApiError(error)}</p>
              {error.status === 409 && (
                <p>
                  Giữ nội dung hiện tại và{" "}
                  <Link href={`/programs/${saved?.id}`}>
                    mở trạng thái mới nhất
                  </Link>{" "}
                  trước khi thao tác lại.
                </p>
              )}
            </div>
          )}
          <fieldset className="owner-form-fields" disabled={busy}>
            <section className="program-builder-section" aria-labelledby="program-info-heading">
              <header><span aria-hidden="true">01</span><div><h2 id="program-info-heading">Thông tin chương trình</h2><p>Chọn học viên và xác định mục tiêu bạn sẽ cùng đạt được.</p></div></header>
              <div className="owner-form-grid owner-form-grid-two">
                <Field
                  htmlFor="program-customer"
                  label="Học viên"
                  hint={saved ? "Học viên được giữ cố định sau khi lưu chương trình." : "Chọn học viên từ các cuộc trò chuyện đang hoạt động của bạn."}
                  error={error?.fieldErrors?.customer_id?.[0]}
                >
                  <ProgramStudentPicker value={input.customer_id} disabled={Boolean(saved)} onChange={(studentId, listingId) => {
                    setDirty(true);
                    setInput((previous) => ({ ...previous, customer_id: studentId, source_listing_id: listingId }));
                  }} />
                </Field>
                <Field
                  htmlFor="program-title"
                  label="Tên chương trình học"
                  error={error?.fieldErrors?.title?.[0]}
                >
                  <Input
                    required
                    maxLength={180}
                    placeholder="Ví dụ: Lộ trình tiếng Anh giao tiếp 8 tuần"
                    value={input.title}
                    onChange={(e) => field("title", e.target.value)}
                  />
                </Field>
              </div>
              <div className="program-builder-goals">
                {(
                  [
                    ["goal", "Mục tiêu học"],
                    ["level", "Trình độ"],
                  ] as const
                ).map(([name, label]) => (
                  <Field
                    key={name}
                    htmlFor={`program-${name}`}
                    label={label}
                    error={error?.fieldErrors?.[name]?.[0]}
                  >
                    {name === "goal" ? <textarea rows={3} value={input.goal ?? ""} placeholder="Ví dụ: Tự tin giao tiếp trong công việc sau 8 tuần…" onChange={(e) => field("goal", e.target.value)} /> : <Input
                      placeholder="Ví dụ: Mới bắt đầu, A2, nâng cao…"
                      value={input[name] ?? ""}
                      onChange={(e) => field(name, e.target.value)}
                    />}
                  </Field>
                ))}
              </div>
            </section>
            <section className="program-builder-section" aria-labelledby="program-schedule-heading">
              <header><span aria-hidden="true">02</span><div><h2 id="program-schedule-heading">Hình thức & lịch học</h2><p>Đề xuất thời gian và cách thức học thuận tiện cho hai bên.</p></div></header>
              <fieldset className="owner-delivery"><legend>Hình thức học</legend><div>
                {Object.entries(deliveryLabels).map(([value, label]) => <label key={value}>
                  <input type="radio" name="program-delivery" value={value} checked={input.delivery_mode === value} onChange={() => field("delivery_mode", value as Program["delivery_mode"])} />{label}
                </label>)}
              </div></fieldset>
              <div className="owner-form-grid owner-form-grid-two">
                <Field htmlFor="program-schedule_note" label="Lịch học dự kiến" hint="Có thể trao đổi và thống nhất thêm với học viên." error={error?.fieldErrors?.schedule_note?.[0]}>
                  <Input placeholder="Ví dụ: Thứ 2, 4, 6 · 19:00 – 20:00" value={input.schedule_note ?? ""} onChange={(e) => field("schedule_note", e.target.value)} />
                </Field>
                <Field htmlFor="program-timezone" label="Múi giờ" error={error?.fieldErrors?.timezone?.[0]}>
                  <Input placeholder="Asia/Ho_Chi_Minh" value={input.timezone} onChange={(e) => field("timezone", e.target.value)} />
                </Field>
                <Field htmlFor="program-location_text" label="Địa điểm học" error={error?.fieldErrors?.location_text?.[0]}>
                  <Input placeholder="Địa chỉ hoặc ghi chú địa điểm học" value={input.location_text ?? ""} onChange={(e) => field("location_text", e.target.value)} />
                </Field>
                <Field htmlFor="program-meeting_url" label="Đường dẫn tham gia buổi học" hint="Dán đường dẫn từ Google Meet, Zoom hoặc ứng dụng bạn dùng. Có thể để trống nếu chưa có." error={error?.fieldErrors?.meeting_url?.[0]}>
                  <Input placeholder="Dán link buổi học tại đây" value={input.meeting_url ?? ""} onChange={(e) => field("meeting_url", e.target.value)} />
                </Field>
              </div>
            </section>
            <section className="program-builder-section" aria-labelledby="program-fees-heading">
              <header><span aria-hidden="true">03</span><div><h2 id="program-fees-heading">Học phí & thời lượng</h2><p>Học phí sẽ được gửi cùng lời mời để học viên xem xét.</p></div></header>
              <div className="owner-form-grid">
                <Field
                  htmlFor="program-fee"
                  label="Học phí (VND)"
                  error={error?.fieldErrors?.agreed_fee_minor?.[0]}
                >
                  <Input
                    type="number"
                    min={0}
                    step={1}
                    required
                    value={input.agreed_fee_minor}
                    onChange={(e) =>
                      field("agreed_fee_minor", Number(e.target.value))
                    }
                  />
                </Field>
                <Field htmlFor="program-unit" label="Đơn vị tính" error={error?.fieldErrors?.fee_unit?.[0]}>
                  <Select
                    value={input.fee_unit}
                    onChange={(e) =>
                      field("fee_unit", e.target.value as Program["fee_unit"])
                    }
                  >
                    <option value="session">Buổi</option>
                    <option value="hour">Giờ</option>
                    <option value="course">Khóa</option>
                  </Select>
                </Field>
                <Field htmlFor="program-count" label="Số buổi dự kiến" error={error?.fieldErrors?.planned_session_count?.[0]}>
                  <Input
                    type="number"
                    min={1}
                    value={input.planned_session_count ?? ""}
                    onChange={(e) =>
                      field(
                        "planned_session_count",
                        e.target.value ? Number(e.target.value) : null,
                      )
                    }
                  />
                </Field>
              </div>
            </section>
          </fieldset>
          <footer className="owner-form-footer program-builder-footer">
            <small>Lưu bản nháp trước khi gửi lời mời cho học viên.</small>
            {!busy ? <Link className="button button-outline" href="/programs">Hủy</Link> : null}
            <Button type="submit" disabled={busy}>
              {busy ? "Đang lưu…" : "Lưu bản nháp"}
            </Button>
          </footer>
        </form>
        <aside className="program-builder-sidebar" aria-label="Tóm tắt trước khi gửi">
          <Card className="program-builder-summary">
            <header><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M8 4H5a2 2 0 0 0-2 2v14h18V6a2 2 0 0 0-2-2h-3M8 3h8v5H8V3ZM7 12h10M7 16h6" /></svg></span><div><h2>Tóm tắt chương trình</h2><small>Cập nhật theo thông tin bạn nhập</small></div></header>
            <h3>{input.title.trim() || "Chương trình học của bạn"}</h3>
            <span className="program-builder-mode">{deliveryLabels[input.delivery_mode]}</span>
            <div className="program-builder-price"><small>Học phí thỏa thuận</small><strong>{formatListingPrice(input.agreed_fee_minor, "VND", input.fee_unit)}</strong></div>
            <dl>
              <div><dt>Trình độ</dt><dd>{input.level?.trim() || "Chưa thiết lập"}</dd></div>
              <div><dt>Số buổi</dt><dd>{input.planned_session_count ? `${input.planned_session_count} buổi` : "Chưa thiết lập"}</dd></div>
              <div><dt>Lịch học</dt><dd>{input.schedule_note?.trim() || "Chưa thống nhất"}</dd></div>
              <div><dt>Múi giờ</dt><dd>{input.timezone || "Chưa thiết lập"}</dd></div>
            </dl>
            <div className="program-builder-goal-preview"><strong>Mục tiêu đồng hành</strong><p>{input.goal?.trim() || "Mô tả điều học viên có thể đạt được sau chương trình."}</p></div>
            {saved && (
              <div className="program-builder-invite">
                <p>{dirty ? "Bạn có thay đổi chưa lưu. Hãy lưu bản nháp trước khi gửi." : "Lời mời sẽ sử dụng thông tin trong bản nháp đã lưu."}</p>
                <Button disabled={busy || dirty} onClick={invite}>
                  Gửi lời mời từ bản đã lưu
                </Button>
              </div>
            )}
          </Card>
          <div className="program-builder-tip"><span aria-hidden="true">i</span><div><strong>Học viên sẽ nhận được gì?</strong><p>Lời mời gồm mục tiêu, lịch học và học phí bạn đã lưu. Chương trình bắt đầu khi học viên chấp nhận lời mời.</p></div></div>
        </aside>
      </div>
    </div>
  );
}
