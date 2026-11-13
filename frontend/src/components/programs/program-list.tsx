"use client";

import { AppShell, useActor } from "@/components/layout/app-shell";
import { programStatuses } from "@/components/programs/program-statuses";
import { ApiState } from "@/components/shared/api-state";
import { Badge, Button, Card, StatePanel } from "@/components/shared/ui";
import {
  listPrograms,
  type Program
} from "@/lib/data/collaboration";
import { deliveryLabels } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useListFilters } from "@/lib/hooks/use-list-filters";
import "@/styles/listings.css";
import "@/styles/programs.css";
import Link from "next/link";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";

export function Programs({ embedded = false }: { embedded?: boolean }) {
  const content = <Suspense fallback={<StatePanel kind="loading" message="Đang tải chương trình học…" />}><ProgramsContent /></Suspense>;
  return embedded ? content : <AppShell title="Program của tôi">{content}</AppShell>;
}
function ProgramsContent() {
  const actor = useActor();
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
          {actor?.role === "tutor" && <Link className="button button-primary" href="/programs/new">+ Tạo chương trình</Link>}
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
                    <svg viewBox="0 0 64 64" fill="none"><rect x="13" y="12" width="38" height="43" rx="8" /><path d="M22 8v9m20-9v9M14 25h36M23 34h18M23 42h11" /></svg>
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
              <span className="program-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 6.2c-2.1-1.6-5.2-2.2-9-1.7v13c3.8-.5 6.9.1 9 1.7m0-13c2.1-1.6 5.2-2.2 9-1.7v13c-3.8-.5-6.9.1-9 1.7m0-13v13" /></svg></span>
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
                disabled={page >= (result.data.meta.last_page ?? Math.max(1, Math.ceil(result.data.meta.total / result.data.meta.per_page)))}
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
