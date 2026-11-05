"use client";
import Link from "next/link";
import {ProgramTrust} from "./trust-workspace";
import {WorkspaceNavigation} from "./workspace-navigation";
import { useCallback, useRef, useState, type FormEvent } from "react";
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
export const programStatuses: Record<Program["status"], string> = {
  draft: "Bản nháp",
  active: "Đang hoạt động",
  paused: "Tạm dừng",
  completed: "Hoàn thành",
  cancelled: "Đã hủy",
};
export function Programs() {
  return <AppShell title="Program của tôi"><ProgramsContent/></AppShell>;
}
function ProgramsContent() {
 const actor=useActor();
  const [page, setPage] = useState(1),
    [status, setStatus] = useState("all");
  const load = useCallback(() => listPrograms(page), [page]);
  const result = useAsyncData(load);
  return (
    <>
      <section className="content-stack workspace-page">
        <header className="workspace-heading">
          <div>
            <h1>Program của tôi</h1>
            <p className="muted">
              Theo dõi chương trình học và cộng tác cùng nhau.
            </p>
          </div>
          {actor?.role==="tutor"&&<Link className="button button-primary" href="/programs/new">+ Tạo Program</Link>}
        </header>
        <nav className="workspace-filters" aria-label="Trạng thái Program">
          {Object.entries({ all: "Tất cả", ...programStatuses }).map(
            ([value, label]) => (
              <Button
                key={value}
                variant={status === value ? "primary" : "ghost"}
                aria-pressed={status === value}
                onClick={() => setStatus(value)}
              >
                {label}
              </Button>
            ),
          )}
        </nav>
        <p className="muted">Bộ lọc áp dụng trên trang hiện tại.</p>
        <ApiState {...result} />
        {result.data && (
          <>
            <div className="program-grid">
              {result.data.data
                .filter((p) => status === "all" || p.status === status)
                .map((p) => (
                  <Card key={p.id} className="program-card">
                    <div className="program-card-heading">
                      <span className="program-monogram" aria-hidden="true">
                        ▤
                      </span>
                      <Badge
                        tone={
                          p.status === "active"
                            ? "success"
                            : p.status === "paused"
                              ? "warning"
                              : "brand"
                        }
                      >
                        {programStatuses[p.status]}
                      </Badge>
                    </div>
                    <h2>{p.title}</h2>
                    <p className="muted">
                      {deliveryLabels[p.delivery_mode]} ·{" "}
                      {formatListingPrice(
                        p.agreed_fee_minor,
                        p.currency,
                        p.fee_unit,
                      )}
                    </p>
                    <p>{p.goal || "Chưa có mục tiêu học tập."}</p>
                    <div className="program-card-footer">
                      <span>#{p.id}</span>
                      <Link
                        className="button button-outline"
                        href={`/programs/${p.id}`}
                      >
                        {p.status === "draft" ? "Xem bản nháp" : "Mở workspace"}{" "}
                        →
                      </Link>
                    </div>
                  </Card>
                ))}
            </div>
            {!result.data.data.length && (
              <StatePanel
                kind="empty"
                message="Chưa có Program. Học viên có thể xem lời mời trước khi tham gia."
                action={<Link href="/invitations">Xem lời mời →</Link>}
              />
            )}
            <nav className="button-row" aria-label="Phân trang">
              <Button
                variant="outline"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                Trước
              </Button>
              <span>
                Trang {page} · {result.data.meta.total} Program
              </span>
              <Button
                variant="outline"
                disabled={page >= (result.data.meta.last_page ?? Math.max(1,Math.ceil(result.data.meta.total/result.data.meta.per_page)))}
                onClick={() => setPage((p) => p + 1)}
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
    <AppShell title="Workspace">
      <section className="content-stack workspace-page">
        <Link href="/programs">← Program của tôi</Link><WorkspaceNavigation id={id} active="overview"/>
        <ApiState {...result} />
        {error && (
          <p role="alert" className="field-error">
            {error}
          </p>
        )}
        {result.data && (
          <>
            {result.data.program.status==="completed"&&<ProgramTrust id={id}/>}<header className="workspace-heading">
              <div>
                <Badge tone="brand">
                  {programStatuses[result.data.program.status]}
                </Badge>
                <h1>{result.data.program.title}</h1>
              </div>
              {result.data.program.allowed_actions.includes("edit") && (
                <Link
                  className="button button-outline"
                  href={`/programs/${id}/edit`}
                >
                  Chỉnh sửa & gửi lời mời
                </Link>
              )}
            </header>
            <div className="workspace-columns">
              <Card>
                <h2>Tổng quan</h2>
                <Offer program={result.data.program} />
              </Card>
              <Card>
                <h2>Hoạt động</h2>
                {result.data.overview ? (
                  <>
                    <p>
                      Buổi tiếp theo:{" "}
                      {result.data.overview.next_session
                        ? new Date(
                            result.data.overview.next_session.planned_starts_at,
                          ).toLocaleString("vi-VN")
                        : "Chưa có lịch"}
                    </p>
                    <p>
                      Task hoàn thành:{" "}
                      {result.data.overview.task_counts.completed}
                    </p>
                    <p>
                      Đã nộp bài: {result.data.overview.task_counts.submitted}
                    </p>
                    <p>
                      Tiến độ:{" "}
                      {result.data.overview.latest_progress
                        ? `${result.data.overview.latest_progress.value}%`
                        : "Chưa ghi nhận"}
                    </p>
                    {result.data.overview.latest_announcement && (
                      <>
                        <h3>
                          {result.data.overview.latest_announcement.title}
                        </h3>
                        <p>{result.data.overview.latest_announcement.body}</p>
                      </>
                    )}
                  </>
                ) : (
                  <p className="muted">
                    Workspace sẽ mở sau khi học viên chấp nhận lời mời.
                  </p>
                )}
              </Card>
            </div>
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
    </AppShell>
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
  return (
    <AppShell role="tutor" title="Tạo Program">
      <section className="content-stack workspace-page">
        <Link href="/programs">← Quay lại Program</Link>
        <h1>{id ? "Chỉnh sửa & gửi lời mời" : "Tạo Program & gửi lời mời"}</h1>
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
    </AppShell>
  );
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
    <div className="workspace-columns">
      <Card>
        <form className="content-stack" onSubmit={submit}>
          <h2>Thông tin cơ bản</h2>
          {error && (
            <div role="alert">
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
          <Field
            htmlFor="program-customer"
            label="ID học viên"
            hint="Chọn học viên từ cuộc trò chuyện để điền sẵn."
            error={error?.fieldErrors?.customer_id?.[0]}
          >
            <Input
              type="number"
              required
              min={1}
              value={input.customer_id || ""}
              disabled={!!saved}
              onChange={(e) => field("customer_id", Number(e.target.value))}
            />
          </Field>
          <Field
            htmlFor="program-title"
            label="Tên chương trình học"
            error={error?.fieldErrors?.title?.[0]}
          >
            <Input
              required
              maxLength={180}
              value={input.title}
              onChange={(e) => field("title", e.target.value)}
            />
          </Field>
          {(
            [
              ["goal", "Mục tiêu học"],
              ["level", "Trình độ"],
              ["schedule_note", "Ghi chú lịch học"],
              ["location_text", "Địa điểm"],
              ["meeting_url", "Liên kết cuộc họp"],
            ] as const
          ).map(([name, label]) => (
            <Field
              key={name}
              htmlFor={`program-${name}`}
              label={label}
              error={error?.fieldErrors?.[name]?.[0]}
            >
              <Input
                value={input[name] ?? ""}
                onChange={(e) => field(name, e.target.value)}
              />
            </Field>
          ))}
          <div className="form-grid">
            <Field htmlFor="program-mode" label="Hình thức">
              <Select
                value={input.delivery_mode}
                onChange={(e) =>
                  field(
                    "delivery_mode",
                    e.target.value as Program["delivery_mode"],
                  )
                }
              >
                {Object.entries(deliveryLabels).map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
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
            <Field htmlFor="program-unit" label="Đơn vị">
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
            <Field htmlFor="program-count" label="Số buổi dự kiến">
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
          <Button type="submit" disabled={busy}>
            Lưu bản nháp
          </Button>
        </form>
      </Card>
      <Card className="program-summary">
        <h2>{saved ? "Bản đã lưu" : "Tóm tắt chương trình"}</h2>
        <Offer
          program={{
            ...input,
            currency: "VND",
            goal: input.goal ?? null,
            level: input.level ?? null,
            schedule_note: input.schedule_note ?? null,
            timezone: input.timezone ?? "Asia/Ho_Chi_Minh",
            planned_session_count: input.planned_session_count ?? null,
            location_text: input.location_text ?? null,
            meeting_url: input.meeting_url ?? null,
          }}
        />
        {saved && (
          <>
            <p>Lời mời sử dụng bản đã lưu. Lưu thay đổi trước khi gửi.</p>
            <Button disabled={busy || dirty} onClick={invite}>
              Gửi lời mời từ bản đã lưu
            </Button>
          </>
        )}
      </Card>
    </div>
  );
}
