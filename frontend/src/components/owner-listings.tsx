"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { archiveOwnerListing, deliveryLabels, formatListingPrice, getOwnerListing, getOwnerListings, statusLabels, transitionOwnerListing } from "@/lib/data/marketplace";
import type { ListingAction, MarketplaceStatus, OwnerListing, OwnerListingCollection } from "@/lib/data/marketplace-types";
import { Badge, Button, DataTable, ErrorActions, StatePanel } from "./ui";

const statuses: Array<MarketplaceStatus | "all"> = ["all", "active", "paused", "draft", "hidden", "archived"];
const actionLabels = { publish: "Công khai", pause: "Tạm dừng", archive: "Lưu trữ" };

export function OwnerListingActions({ listing, busy, onAction }: { listing: OwnerListing; busy: boolean; onAction: (action: Exclude<ListingAction, "edit">) => void }) {
  return <div className="owner-row-actions">
    {listing.allowed_actions.includes("edit") && !busy ? <Link className="button button-ghost" href={`/me/listings/${listing.id}/edit`}>Chỉnh sửa</Link> : null}
    {(["publish", "pause", "archive"] as const).filter((action) => listing.allowed_actions.includes(action)).map((action) => <Button key={action} variant={action === "archive" ? "danger" : "outline"} disabled={busy} onClick={() => onAction(action)}>{actionLabels[action]}</Button>)}
  </div>;
}

export function OwnerStatus({ status }: { status: MarketplaceStatus }) {
  return <Badge className={`owner-status owner-status-${status}`} tone={status === "active" ? "success" : status === "paused" ? "warning" : "neutral"}>{statusLabels[status]}</Badge>;
}

export function OwnerListings() {
  const [collection, setCollection] = useState<OwnerListingCollection | null>(null);
  const [status, setStatus] = useState<MarketplaceStatus | "all">("all");
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState("");

  useEffect(() => {
    let current = true;
    getOwnerListings(status === "all" ? undefined : status, page).then((data) => { if (current) { setCollection(data); setError(null); } }).catch((reason) => { if (current) { setError(reason); setCollection(null); } }).finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [status, page, retry]);

  async function act(listing: OwnerListing, action: Exclude<ListingAction, "edit">) {
    if (busy || !listing.allowed_actions.includes(action)) return;
    if (action === "archive" && !window.confirm(`Lưu trữ “${listing.title}”? Dịch vụ sẽ không còn hiển thị công khai.`)) return;
    setBusy(true); setNotice("");
    try {
      if (action === "archive") await archiveOwnerListing(listing.id);
      else await transitionOwnerListing(listing.id, action === "publish" ? "active" : "paused");
      setNotice("Đã cập nhật dịch vụ.");
      setLoading(true); setRetry((value) => value + 1);
    } catch (reason) {
      setNotice(describeApiError(reason));
      if (reason instanceof ApiClientError && [403, 404, 409].includes(reason.status ?? 0)) { setLoading(true); setRetry((value) => value + 1); }
      setError(reason);
    } finally { setBusy(false); }
  }

  return <section className="owner-listings">
    <header className="owner-heading"><div><h1>Dịch vụ của tôi</h1><p className="muted">Tạo, chỉnh sửa và quản lý dịch vụ bạn cung cấp. Chỉ gia sư đã được phê duyệt mới có thể công khai dịch vụ.</p></div>{collection?.meta.allowed_actions.includes("create") && !busy ? <Link className="button button-primary" href="/me/listings/new">＋ Tạo dịch vụ mới</Link> : null}</header>
    <nav className="owner-tabs" aria-label="Lọc dịch vụ theo trạng thái">{statuses.map((item) => <Button key={item} variant="ghost" disabled={busy || loading} aria-pressed={status === item} onClick={() => { if (item === status && page === 1) return; setStatus(item); setPage(1); setLoading(true); }}>{item === "all" ? "Tất cả" : statusLabels[item]}{collection ? ` (${collection.meta.status_counts[item]})` : ""}</Button>)}</nav>
    {notice ? <p role="status" className="owner-notice">{notice}</p> : null}
    {loading ? <StatePanel kind="loading" message="Đang tải dịch vụ của bạn…" /> : !collection ? <StatePanel kind="error" message={describeApiError(error)} action={<ErrorActions login={error instanceof ApiClientError && [401, 419].includes(error.status ?? 0)} onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} />} /> : <>
      {!collection.data.length ? <StatePanel kind="empty" message="Chưa có dịch vụ trong trạng thái này." /> : <DataTable rows={collection.data} rowKey={(row) => row.id} columns={[
        { key: "title", label: "Dịch vụ", render: (row) => <Link className="owner-title" href={`/me/listings/${row.id}`}>{row.title}</Link> },
        { key: "category", label: "Danh mục", render: (row) => row.category_name ?? "—" },
        { key: "specialization", label: "Chuyên ngành", render: (row) => row.specialization_name ?? "—" },
        { key: "delivery", label: "Hình thức", render: (row) => deliveryLabels[row.delivery_mode] },
        { key: "location", label: "Địa điểm", render: (row) => [row.district, row.city].filter(Boolean).join(", ") || "—" },
        { key: "price", label: "Giá từ", render: (row) => <strong>{formatListingPrice(row.price_from_minor, row.currency, row.price_unit)}</strong> },
        { key: "status", label: "Trạng thái", render: (row) => <OwnerStatus status={row.status} /> },
        { key: "actions", label: "Thao tác", render: (row) => <OwnerListingActions listing={row} busy={busy} onAction={(action) => void act(row, action)} /> },
      ]} />}
      <div className="owner-pagination"><span>{collection.meta.total} dịch vụ · Trang {collection.meta.current_page}</span><Button variant="outline" disabled={busy || page <= 1} onClick={() => { setPage(page - 1); setLoading(true); }}>Trước</Button><Button variant="outline" disabled={busy || page * collection.meta.per_page >= collection.meta.total} onClick={() => { setPage(page + 1); setLoading(true); }}>Sau</Button></div>
      {error instanceof ApiClientError && [401, 419].includes(error.status ?? 0) ? <ErrorActions login onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} /> : null}
    </>}
  </section>;
}

export function OwnerListingDetail({ id }: { id: number }) {
  const [listing, setListing] = useState<OwnerListing | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [actionError, setActionError] = useState<unknown>(null);
  const [retry, setRetry] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    let current = true;
    getOwnerListing(id).then((data) => { if (current) { setListing(data); setError(null); } }).catch((reason) => { if (current) { setListing(null); setError(reason); } }).finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [id, retry]);
  async function act(action: Exclude<ListingAction, "edit">) {
    if (!listing || busy || !listing.allowed_actions.includes(action)) return;
    if (action === "archive" && !window.confirm("Lưu trữ dịch vụ? Dịch vụ sẽ không còn hiển thị công khai.")) return;
    setBusy(true);
    try { if (action === "archive") await archiveOwnerListing(id); else await transitionOwnerListing(id, action === "publish" ? "active" : "paused"); setActionError(null); }
    catch (reason) { setActionError(reason); }
    finally { setBusy(false); setLoading(true); setRetry((value) => value + 1); }
  }
  return <section className="owner-detail"><Link href="/me/listings">← Dịch vụ của tôi</Link>{loading ? <StatePanel kind="loading" message="Đang tải dịch vụ…" /> : !listing ? <StatePanel kind="error" message={error instanceof ApiClientError && error.status === 404 ? "Không tìm thấy dịch vụ này." : describeApiError(error)} action={<ErrorActions login={error instanceof ApiClientError && [401, 419].includes(error.status ?? 0)} onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} />} /> : <article className="card">
    <div className="owner-heading"><h1>{listing.title}</h1><OwnerStatus status={listing.status} /></div><p className="owner-description">{listing.description}</p>
    <dl className="owner-detail-grid"><div><dt>Chuyên ngành</dt><dd>{listing.specialization_name ?? `#${listing.specialization_id}`}</dd></div><div><dt>Hình thức</dt><dd>{deliveryLabels[listing.delivery_mode]}</dd></div><div><dt>Địa điểm</dt><dd>{[listing.district, listing.city].filter(Boolean).join(", ") || "—"}</dd></div><div><dt>Giá từ</dt><dd>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</dd></div></dl>
    {actionError ? <div role="alert"><p>{describeApiError(actionError)}</p>{actionError instanceof ApiClientError && [401, 419].includes(actionError.status ?? 0) ? <ErrorActions login onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} /> : null}</div> : null}<OwnerListingActions listing={listing} busy={busy} onAction={(action) => void act(action)} />
  </article>}</section>;
}
