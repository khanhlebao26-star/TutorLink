"use client";

import { Badge, Button, ErrorActions, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import {
  archiveOwnerListing,
  deliveryLabels,
  formatListingPrice,
  getOwnerListing,
  getOwnerListings,
  statusLabels,
  transitionOwnerListing,
} from "@/lib/data/marketplace";
import type { MarketplaceStatus, OwnerListing, OwnerListingAction, OwnerListingCollection, OwnerListingStatusFilter } from "@/lib/data/marketplace-types";
import { useListFilters } from "@/lib/hooks/use-list-filters";
import Image from "next/image";
import Link from "next/link";
import { useEffect, useState } from "react";

type OwnerMutationAction = Exclude<OwnerListingAction, "edit">;

const actionLabels: Record<OwnerMutationAction, string> = {
  publish: "Công khai",
  pause: "Tạm dừng",
  archive: "Lưu trữ",
};

const mutationActions: OwnerMutationAction[] = ["publish", "pause", "archive"];
const ownerFilters: Array<{ status: OwnerListingStatusFilter; label: string }> = [
  { status: "all", label: "Tất cả" },
  { status: "draft", label: "Bản nháp" },
  { status: "active", label: "Đang hoạt động" },
  { status: "inactive", label: "Tạm dừng" },
  { status: "cancelled", label: "Đã lưu trữ" },
];

function listingCategory(name?: string) {
  const category = (name || "").toLocaleLowerCase("vi");
  if (category.includes("fitness")) return { label: "Fitness", tone: "fitness" };
  if (category.includes("sport") || category.includes("thể thao")) return { label: "Thể thao", tone: "sports" };
  return { label: category.includes("language") || category.includes("education") ? "Giáo dục" : name || "Dịch vụ", tone: "education" };
}

function OwnerListingsLoading() {
  return <div className="owner-service-loading" role="status" aria-label="Đang tải danh sách dịch vụ">
    <p><span className="owner-loading-spinner" aria-hidden="true" />Đang chuẩn bị dịch vụ của bạn…</p>
    <div className="owner-service-grid" aria-hidden="true">{[0, 1, 2, 3].map((item) => <div className="owner-service-skeleton" key={item}><i/><div><b/><span/><span/><em/></div></div>)}</div>
  </div>;
}

function OwnerServiceCard({ listing, busy, onAction }: { listing: OwnerListing; busy: boolean; onAction: (action: OwnerMutationAction) => void }) {
  const category = listingCategory(listing.category_name);
  const location = [listing.district, listing.city].filter(Boolean).join(", ");
  return <article className="owner-service-card">
    <div className="owner-service-main">
      <Link href={`/me/listings/${listing.id}`} className={`owner-service-cover owner-service-cover-${category.tone}`} aria-label={`Quản lý ${listing.title}`}>
        <Image src={`/featured/${category.tone}.svg`} alt="Ảnh minh họa dịch vụ" fill sizes="(max-width: 600px) 100vw, 190px" />
        <span>Ảnh minh họa</span>
      </Link>
      <div className="owner-service-content">
        <div className="owner-service-meta"><span className={`owner-category owner-category-${category.tone}`}>{category.label}</span><OwnerStatus status={listing.status}/></div>
        <h2><Link href={`/me/listings/${listing.id}`}>{listing.title}</Link></h2>
        <p className="owner-service-specialty">{listing.specialization_name || "Chưa cập nhật chuyên ngành"}</p>
        <p className="owner-service-description">{listing.description || "Thêm mô tả để học viên hiểu rõ hơn về dịch vụ của bạn."}</p>
        <div className="owner-service-facts">
          <span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>{deliveryLabels[listing.delivery_mode]}</span>
          <span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/></svg>{location || (listing.delivery_mode === "online" ? "Không giới hạn địa điểm" : "Chưa cập nhật địa điểm")}</span>
        </div>
      </div>
    </div>
    <div className="owner-service-footer"><div className="owner-service-price"><small>Giá dịch vụ từ</small><strong>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</strong></div><OwnerListingActions listing={listing} busy={busy} onAction={onAction}/></div>
  </article>;
}

export function OwnerListingActions({
  listing,
  busy,
  onAction,
}: {
  listing: OwnerListing;
  busy: boolean;
  onAction: (action: OwnerMutationAction) => void;
}) {
  return (
    <div className="owner-row-actions">
      {listing.allowed_actions.includes("edit") && !busy ? (
        <Link className="button button-ghost" href={`/me/listings/${listing.id}/edit`}>
          Chỉnh sửa
        </Link>
      ) : null}
      {mutationActions.filter((action) => listing.allowed_actions.includes(action)).map((action) => (
        <Button
          key={action}
          variant={action === "archive" ? "danger" : "outline"}
          disabled={busy}
          onClick={() => onAction(action)}
        >
          {actionLabels[action]}
        </Button>
      ))}
    </div>
  );
}

export function OwnerStatus({ status }: { status: MarketplaceStatus }) {
  return (
    <Badge
      className={`owner-status owner-status-${status}`}
      tone={status === "active" ? "success" : status === "inactive" ? "warning" : "neutral"}
    >
      {status === "inactive" ? "Tạm dừng" : status === "cancelled" ? "Đã lưu trữ" : statusLabels[status]}
    </Badge>
  );
}

export function OwnerListings() {
  const [collection, setCollection] = useState<OwnerListingCollection | null>(null);
  const filters = useListFilters(ownerFilters.map((filter) => filter.status));
  const status = filters.status as OwnerListingStatusFilter;
  const { page, setPage, setStatus } = filters;
  const [retry, setRetry] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState("");

  useEffect(() => {
    let current = true;
    getOwnerListings({ page, status })
      .then((data) => {
        if (current) {
          setCollection(data);
          setError(null);
        }
      })
      .catch((reason) => {
        if (current) {
          setError(reason);
          setCollection(null);
        }
      })
      .finally(() => {
        if (current) setLoading(false);
      });
    return () => {
      current = false;
    };
  }, [page, retry, status]);

  async function act(listing: OwnerListing, action: OwnerMutationAction) {
    if (busy || !listing.allowed_actions.includes(action)) return;
    if (action === "archive" && !window.confirm(`Lưu trữ “${listing.title}”? Dịch vụ sẽ không còn hiển thị công khai.`)) return;

    setBusy(true);
    setNotice("");
    try {
      if (action === "archive") await archiveOwnerListing(listing.id);
      else await transitionOwnerListing(listing.id, action === "publish" ? "active" : "inactive");
      setNotice("Đã cập nhật dịch vụ.");
      setLoading(true);
      setRetry((value) => value + 1);
    } catch (reason) {
      setNotice(describeApiError(reason));
      if (reason instanceof ApiClientError && reason.status === 409) {
        setLoading(true);
        setRetry((value) => value + 1);
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="owner-listings owner-service-dashboard">
      <header className="owner-heading">
        <div>
          <h1>Dịch vụ của tôi</h1>
          <p className="muted">Quản lý nội dung, học phí và trạng thái hiển thị của các dịch vụ bạn cung cấp.</p>
        </div>
        {!busy && collection?.meta.allowed_actions.includes("create") ? <Link className="button button-primary" href="/me/listings/new">＋ Tạo dịch vụ mới</Link> : null}
      </header>

      {notice ? <p role="status" className="owner-notice">{notice}</p> : null}
      {collection ? <>
        <div className="owner-service-summary" aria-label="Tổng quan dịch vụ">
          {[{ key: "all", label: "Tổng dịch vụ", icon: "grid" }, { key: "active", label: "Đang hoạt động", icon: "check" }, { key: "draft", label: "Đang soạn thảo", icon: "edit" }].map((item) => <div key={item.key}><span className={`owner-summary-icon owner-summary-${item.icon}`} aria-hidden="true"><svg viewBox="0 0 24 24" fill="none">{item.icon === "grid" ? <><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></> : item.icon === "check" ? <><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></> : <><path d="m15 4 5 5-10 10-6 1 1-6L15 4Zm-8 9 4 4"/></>}</svg></span><div><strong>{collection.meta.status_counts[item.key as OwnerListingStatusFilter]}</strong><span>{item.label}</span></div></div>)}
        </div>
        <nav className="owner-tabs owner-service-filters" aria-label="Lọc dịch vụ theo trạng thái">
          {ownerFilters.map((filter) => <Button key={filter.status} variant="ghost" aria-pressed={status === filter.status} disabled={busy} onClick={() => { if (status !== filter.status) { setLoading(true); setStatus(filter.status); } }}>{filter.label}<span className="owner-tab-count">{collection.meta.status_counts[filter.status]}</span></Button>)}
        </nav>
      </> : null}
      {loading ? (
        <OwnerListingsLoading />
      ) : error instanceof ApiClientError && error.status === 403 ? (
        <StatePanel
          kind="error"
          message="Để quản lý dịch vụ, tài khoản gia sư cần hoạt động, đã xác minh email và có hồ sơ được phê duyệt. Bạn có thể kiểm tra tiến độ trong Hồ sơ Tutor."
          action={<Link className="button button-secondary" href="/profile">Mở Hồ sơ Tutor</Link>}
        />
      ) : !collection ? (
        <StatePanel
          kind="error"
          message={describeApiError(error)}
          action={<ErrorActions login={error instanceof ApiClientError && [401, 419].includes(error.status ?? 0)} onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} />}
        />
      ) : (
        <>
          {!collection.data.length ? (
            <StatePanel kind="empty" message={status === "all" ? "Bạn chưa có dịch vụ nào." : `Không có dịch vụ ở trạng thái “${ownerFilters.find((filter) => filter.status === status)?.label}”.`} />
          ) : (
            <div className="owner-service-grid">{collection.data.map((listing) => <OwnerServiceCard key={listing.id} listing={listing} busy={busy} onAction={(action) => void act(listing, action)}/>)}</div>
          )}
          <div className="owner-pagination">
            <span>{collection.meta.total} dịch vụ · Trang {collection.meta.current_page} / {Math.max(1, Math.ceil(collection.meta.total / collection.meta.per_page))}</span>
            {collection.meta.total > collection.meta.per_page ? <><Button variant="outline" disabled={busy || collection.meta.current_page <= 1} onClick={() => { setLoading(true); setPage(Math.max(1, page - 1)); }}>← Trước</Button>
            <Button variant="outline" disabled={busy || collection.meta.current_page * collection.meta.per_page >= collection.meta.total} onClick={() => { setLoading(true); setPage(page + 1); }}>Sau →</Button></> : null}
          </div>
        </>
      )}
    </section>
  );
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
    getOwnerListing(id)
      .then((data) => {
        if (current) {
          setListing(data);
          setError(null);
        }
      })
      .catch((reason) => {
        if (current) {
          setListing(null);
          setError(reason);
        }
      })
      .finally(() => {
        if (current) setLoading(false);
      });
    return () => {
      current = false;
    };
  }, [id, retry]);

  async function act(action: OwnerMutationAction) {
    if (!listing || busy || !listing.allowed_actions.includes(action)) return;
    if (action === "archive" && !window.confirm("Lưu trữ dịch vụ? Dịch vụ sẽ không còn hiển thị công khai.")) return;

    setBusy(true);
    try {
      if (action === "archive") await archiveOwnerListing(id);
      else await transitionOwnerListing(id, action === "publish" ? "active" : "inactive");
      setActionError(null);
      setLoading(true);
      setRetry((value) => value + 1);
    } catch (reason) {
      setActionError(reason);
      if (reason instanceof ApiClientError && reason.status === 409) {
        setLoading(true);
        setRetry((value) => value + 1);
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="owner-detail">
      <Link href="/me/listings">← Dịch vụ của tôi</Link>
      {loading ? (
        <StatePanel kind="loading" message="Đang tải dịch vụ…" />
      ) : !listing ? (
        <StatePanel
          kind="error"
          message={error instanceof ApiClientError && error.status === 404 ? "Không tìm thấy dịch vụ này." : describeApiError(error)}
          action={<ErrorActions login={error instanceof ApiClientError && [401, 419].includes(error.status ?? 0)} onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} />}
        />
      ) : (
        <article className="card">
          <div className="owner-heading"><h1>{listing.title}</h1><OwnerStatus status={listing.status} /></div>
          <p className="owner-description">{listing.description}</p>
          <dl className="owner-detail-grid">
            <div><dt>Chuyên ngành</dt><dd>{listing.specialization_name ?? `#${listing.specialization_id}`}</dd></div>
            <div><dt>Hình thức</dt><dd>{deliveryLabels[listing.delivery_mode]}</dd></div>
            <div><dt>Địa điểm</dt><dd>{[listing.district, listing.city].filter(Boolean).join(", ") || "—"}</dd></div>
            <div><dt>Giá từ</dt><dd>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</dd></div>
          </dl>
          {actionError ? <div role="alert"><p>{describeApiError(actionError)}</p>{actionError instanceof ApiClientError && [401, 419].includes(actionError.status ?? 0) ? <ErrorActions login onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} /> : null}</div> : null}
          <OwnerListingActions listing={listing} busy={busy} onAction={(action) => void act(action)} />
        </article>
      )}
    </section>
  );
}
