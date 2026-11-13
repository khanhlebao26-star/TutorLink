"use client";
import Link from "next/link";
import {ProgramTrust} from "./trust-workspace";
import { ProgramOverview } from "./program-overview";
import { Suspense, useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import { useListFilters } from "@/lib/hooks/use-list-filters";
import { ProgramStudentPicker } from "./program-student-picker";
import { useRouter } from "next/navigation";
import { AppShell, useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Button, Card, Field, Input, Select, StatePanel } from "./ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import {
  listPrograms,
  getProgram,
  getOverview,
  createProgram,
  editProgram,
  sendInvitation,
  mutationKey,
  transitionProgram,
  type Program,
  type ProgramInput,
} from "@/lib/data/collaboration";
import { formatListingPrice, deliveryLabels } from "@/lib/data/marketplace";
import "@/app/workspace.css";
import "@/app/owner-listings.css";
export const programStatuses: Record<Program["status"], string> = {
  draft: "Bản nháp",
  active: "Đang hoạt động",
  paused: "Tạm dừng",
  completed: "Hoàn thành",
  cancelled: "Đã hủy",
};
export function Programs({ embedded = false }: { embedded?: boolean }) {
  const content = <Suspense fallback={<StatePanel kind="loading" message="Đang tải chương trình học…" />}><ProgramsContent /></Suspense>;
  return embedded ? content : <AppShell title="Program của tôi">{content}</AppShell>;
}
function ProgramsContent() {
 const actor=useActor();
  const { page, setPage, status, setStatus } = useListFilters(["all", ...Object.keys(programStatuses)]);
  const [filterLoading, setFilterLoading] = useState(false);
  const filterTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => {
    if (filterTimer.current) clearTimeout(filterTimer.current);
  }, []);
  const selectStatus = (value: string) => {
    if (value === status) return;
    if (filterTimer.current) clearTimeout(filterTimer.current);
    setStatus(value);
    setFilterLoading(true);
    filterTimer.current = setTimeout(() => {
      setFilterLoading(false);
      filterTimer.current = null;
    }, 420);
  };
  const load = useCallback(() => listPrograms(page), [page]);
  const result = useAsyncData(load);
  const programs = result.data?.data ?? [];
  const visiblePrograms = programs.filter((program) => status === "all" || program.status === status);
  const statusCounts = programs.reduce<Record<string, number>>((counts, program) => {
    counts[program.status] = (counts[program.status] ?? 0) + 1;
    return counts;
  }, {});
  const filters = [
    { value: "all", label: "Tất cả", count: programs.length },
    ...Object.entries(programStatuses)
      .filter(([value]) => value !== "draft")
      .map(([value, label]) => ({ value, label, count: statusCounts[value] ?? 0 })),
  ];
  return (
    <>
      <section className="content-stack workspace-page">
        <header className="workspace-heading">
          <div>
            <h1>{actor?.role === "tutor" ? "Chương trình học" : "Program của tôi"}</h1>
            <p className="muted">
              {actor?.role === "tutor" ? "Tạo chương trình riêng cho học viên, gửi lời mời và theo dõi tiến độ giảng dạy." : "Theo dõi chương trình học và cộng tác cùng nhau."}
            </p>
          </div>
          {actor?.role==="tutor"&&<Link className="button button-primary" href="/programs/new">+ Tạo chương trình</Link>}
        </header>
        <nav className="workspace-filters program-status-filters" aria-label="Trạng thái Program">
          {filters.map(({ value, label, count }) => (
              <Button
                key={value}
                variant={status === value ? "primary" : "ghost"}
                aria-pressed={status === value}
                onClick={() => selectStatus(value)}
              >
                <span>{label}</span><span className="program-filter-count">{count}</span>
              </Button>
          ))}
        </nav>
        {filterLoading && result.data ? (
          <StatePanel kind="loading" message="Đang cập nhật danh sách chương trình..." />
        ) : <ApiState {...result} />}
        {result.data && !filterLoading && (
          <>
            {visiblePrograms.length ? <div className="program-grid">
              {visiblePrograms.map((p) => (
                <Card key={p.id} className="program-card program-showcase-card">
                  <div className="program-art" aria-hidden="true">
                    <span className="program-art-orbit" />
                    <svg viewBox="0 0 64 64" fill="none"><rect x="13" y="12" width="38" height="43" rx="8"/><path d="M22 8v9m20-9v9M14 25h36M23 34h18M23 42h11"/></svg>
                    <Badge tone={p.status === "active" ? "success" : p.status === "paused" ? "warning" : "brand"}>{programStatuses[p.status]}</Badge>
                  </div>
                  <div className="program-card-content">
                    <h2>{p.title}</h2>
                    <p className="program-participant"><span aria-hidden="true">♙</span> {actor?.role === "tutor" ? `Học viên #${p.customer_id}` : `Program #${p.id}`}</p>
                    <div className="program-tags">
                      <span>{p.level || "Chương trình học"}</span>
                      <span>{deliveryLabels[p.delivery_mode]}</span>
                      {p.planned_session_count ? <span>{p.planned_session_count} buổi</span> : null}
                    </div>
                    <p className="program-goal"><span aria-hidden="true">◉</span> {p.goal || "Theo dõi mục tiêu và tiến độ học tập cùng nhau."}</p>
                    <p className="program-schedule"><span aria-hidden="true">▦</span> {p.schedule_note || (p.status === "completed" ? "Chương trình đã hoàn thành" : "Chưa có lịch học tiếp theo")}</p>
                    <Link className="button button-outline program-open-link" href={`/programs/${p.id}`}>
                      {p.status === "completed" || p.status === "cancelled" ? "Xem lại chương trình" : "Mở workspace"} <span aria-hidden="true">→</span>
                    </Link>
                  </div>
                </Card>
              ))}
            </div> : <div className="program-empty-callout">
              <span className="program-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 6.2c-2.1-1.6-5.2-2.2-9-1.7v13c3.8-.5 6.9.1 9 1.7m0-13c2.1-1.6 5.2-2.2 9-1.7v13c-3.8-.5-6.9.1-9 1.7m0-13v13"/></svg></span>
              <div>
                <h2>{programs.length ? `Chưa có chương trình ${status === "all" ? "nào" : `ở trạng thái “${programStatuses[status as Program["status"]]}”`}` : actor?.role === "tutor" ? "Bạn chưa tạo chương trình học nào" : "Bạn chưa đăng ký dịch vụ nào"}</h2>
                <p>{actor?.role === "tutor" ? (programs.length ? "Thử chọn trạng thái khác để xem các chương trình bạn đang quản lý." : "Tạo chương trình cho học viên, thống nhất lịch học và gửi lời mời để bắt đầu.") : programs.length ? "Bạn chưa đăng ký dịch vụ nào trong mục này. Thử chọn trạng thái khác để xem Program của bạn." : "Hãy kiểm tra Lời mời học. Khi bạn chấp nhận lời mời, Program sẽ xuất hiện ở đây."}</p>
              </div>
              {!programs.length ? <Link className="button button-primary" href={actor?.role === "tutor" ? "/programs/new" : "/invitations"}>{actor?.role === "tutor" ? "Tạo chương trình" : "Xem lời mời Program"} <span aria-hidden="true">→</span></Link> : null}
            </div>}
            <nav className="button-row" aria-label="Phân trang">
              <Button
                variant="outline"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                Trước
              </Button>
              <span>
                Trang {page} · {result.data.meta.total} Program
              </span>
              <Button
                variant="outline"
                disabled={page >= (result.data.meta.last_page ?? Math.max(1,Math.ceil(result.data.meta.total/result.data.meta.per_page)))}
                onClick={() => setPage(page + 1)}
              >
                Sau
              </Button>
            </nav>
          </>
        )}
      </section>
    </>
  );
}
export function ProgramDetail({ id }: { id: number }) {
  const load = useCallback(async () => {
    const program = await getProgram(id);
    return {
      program,
      overview: program.status === "draft" ? null : await getOverview(id),
    };
  }, [id]);
  const result = useAsyncData(load);
  const [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  const key = useRef<{ action: string; version: number; key: string } | null>(
    null,
  );
  async function act(action: string) {
    if (!result.data || busy) return;
    const p = result.data.program;
    setBusy(true);
    setError("");
    if (
      !key.current ||
      key.current.action !== action ||
      key.current.version !== p.version
    )
      key.current = { action, version: p.version, key: mutationKey() };
    try {
      await transitionProgram(id, action, key.current.key);
      key.current = null;
      result.reload();
    } catch (e) {
      setError(describeApiError(e)+(e instanceof ApiClientError&&e.blockers?` Còn buổi học: ${e.blockers.sessions.join(", ")||"không"}; bài tập: ${e.blockers.tasks.join(", ")||"không"}.`:""));
    } finally {
      setBusy(false);
    }
  }
  return (
      <section className="content-stack workspace-page program-overview-page">
        <ApiState {...result} />
        {error && (
          <p role="alert" className="field-error">
            {error}
          </p>
        )}
        {result.data && (
          <>
            <ProgramOverview program={result.data.program} overview={result.data.overview} />
            {result.data.program.status === "completed" && <ProgramTrust id={id} />}
            {result.data.program.allowed_actions.some(
              (a) => a !== "edit" && a !== "send_invitation",
            ) && (
              <Card>
                <h2>Trạng thái chương trình</h2>
                <div className="button-row">
                  {result.data.program.allowed_actions
                    .filter((a) => a !== "edit" && a !== "send_invitation")
                    .map((a) => (
                      <Button
                        key={a}
                        disabled={busy}
                        variant={a === "cancel" ? "danger" : "outline"}
                        onClick={() => act(a)}
                      >
                        {{
                          pause: "Tạm dừng",
                          resume: "Tiếp tục",
                          complete: "Hoàn thành",
                          cancel: "Hủy Program",
                        }[a] || a}
                      </Button>
                    ))}
                </div>
                <Button variant="ghost" disabled={busy} onClick={result.reload}>
                  Tải trạng thái mới nhất
                </Button>
              </Card>
            )}
          </>
        )}
      </section>
  );
}
export function Offer({
  program: p,
}: {
  program: Pick<
    Program,
    | "title"
    | "goal"
    | "level"
    | "delivery_mode"
    | "agreed_fee_minor"
    | "currency"
    | "fee_unit"
    | "schedule_note"
    | "timezone"
    | "planned_session_count"
    | "location_text"
    | "meeting_url"
  >;
}) {
  return (
    <dl className="offer-facts">
      <div>
        <dt>Mục tiêu</dt>
        <dd>{p.goal || "Chưa ghi nhận"}</dd>
      </div>
      <div>
        <dt>Trình độ</dt>
        <dd>{p.level || "Chưa ghi nhận"}</dd>
      </div>
      <div>
        <dt>Hình thức</dt>
        <dd>{deliveryLabels[p.delivery_mode]}</dd>
      </div>
      <div>
        <dt>Học phí thỏa thuận</dt>
        <dd>
          {formatListingPrice(p.agreed_fee_minor, p.currency, p.fee_unit)}
        </dd>
      </div>
      <div>
        <dt>Lịch học</dt>
        <dd>
          {p.schedule_note || "Chưa thống nhất"} · {p.timezone}
        </dd>
      </div>
      <div>
        <dt>Số buổi dự kiến</dt>
        <dd>{p.planned_session_count ?? "Chưa thống nhất"}</dd>
      </div>
      <div>
        <dt>Địa điểm / Liên kết</dt>
        <dd>{p.location_text || p.meeting_url || "Chưa thống nhất"}</dd>
      </div>
    </dl>
  );
}
export function ProgramEditor({
  id,
  customerId,
  sourceListingId,
}: {
  id?: number;
  customerId?: number;
  sourceListingId?: number;
}) {
  const load = useCallback(
    () => (id ? getProgram(id) : Promise.resolve(null)),
    [id],
  );
  const result = useAsyncData(load);
  const content = (
      <section className="owner-form-page program-editor-page">
        <Link href="/programs" className="program-editor-back">← Chương trình học</Link>
        <ApiState {...result} />
        {!result.loading &&
          !result.error &&
          (result.data && !result.data.allowed_actions.includes("edit") ? (
            <StatePanel
              kind="error"
              message="Bản nháp đang khóa hoặc bạn không có quyền chỉnh sửa."
            />
          ) : (
            <ProgramForm
              key={result.data?.version ?? "new"}
              existing={result.data}
              customerId={customerId}
              sourceListingId={sourceListingId}
            />
          ))}
      </section>
  );
  return id ? content : <AppShell role="tutor" title="Tạo Program">{content}</AppShell>;
}
function ProgramForm({
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
            }}/>
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
              <Input placeholder="Ví dụ: Thứ 2, 4, 6 · 19:00 – 20:00" value={input.schedule_note ?? ""} onChange={(e) => field("schedule_note", e.target.value)}/>
            </Field>
            <Field htmlFor="program-timezone" label="Múi giờ" error={error?.fieldErrors?.timezone?.[0]}>
              <Input placeholder="Asia/Ho_Chi_Minh" value={input.timezone} onChange={(e) => field("timezone", e.target.value)}/>
            </Field>
            <Field htmlFor="program-location_text" label="Địa điểm học" error={error?.fieldErrors?.location_text?.[0]}>
              <Input placeholder="Địa chỉ hoặc ghi chú địa điểm học" value={input.location_text ?? ""} onChange={(e) => field("location_text", e.target.value)}/>
            </Field>
            <Field htmlFor="program-meeting_url" label="Liên kết lớp học" hint="Không bắt buộc. Có thể bổ sung sau." error={error?.fieldErrors?.meeting_url?.[0]}>
              <Input placeholder="https://meet.google.com/..." value={input.meeting_url ?? ""} onChange={(e) => field("meeting_url", e.target.value)}/>
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
        <header><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M8 4H5a2 2 0 0 0-2 2v14h18V6a2 2 0 0 0-2-2h-3M8 3h8v5H8V3ZM7 12h10M7 16h6"/></svg></span><div><h2>Tóm tắt chương trình</h2><small>Cập nhật theo thông tin bạn nhập</small></div></header>
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
