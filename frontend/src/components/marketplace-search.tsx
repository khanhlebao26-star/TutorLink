"use client";

import Link from "next/link";
import { useCallback, useMemo, useRef, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { deliveryLabels, formatListingPrice, getMarketplaceCatalog, searchListings } from "@/lib/data/marketplace";
import type { PublicListing } from "@/lib/data/marketplace-types";
import { readListingQuery, updateListingQuery, type ListingQueryKey } from "@/lib/data/listing-query";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Badge, Button, ErrorActions, Field, StatePanel } from "./ui";

const initials = (name: string) => name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase();

function ListingFacts({ listing }: { listing: PublicListing }) {
  return <div className="market-tags">
    {listing.category_name && <Badge tone="brand">{listing.category_name}</Badge>}
    {listing.specialization_name && <Badge>{listing.specialization_name}</Badge>}
    <Badge tone="success">{deliveryLabels[listing.delivery_mode]}</Badge>
    {listing.city && <Badge>{listing.city}</Badge>}
  </div>;
}

function ListingRating({ listing }: { listing: PublicListing }) {
  return listing.rating && listing.rating.count > 0 ? <p className="market-rating"><span aria-hidden="true">★</span> {listing.rating.average.toFixed(1)} <small>({listing.rating.count} đánh giá)</small></p> : null;
}

export function MarketplaceSearch() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const url = searchParams.toString();
  const query = useMemo(() => readListingQuery(new URLSearchParams(url)), [url]);
  const catalogLoad = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(catalogLoad);
  const listingLoad = useCallback(() => {
    if (query.min_fee !== undefined && query.max_fee !== undefined && query.min_fee > query.max_fee) {
      return Promise.reject(new ApiClientError("Phí tối đa phải lớn hơn hoặc bằng phí tối thiểu."));
    }
    return searchListings(query);
  }, [query]);
  const results = useAsyncData(listingLoad);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const selected = results.data?.data.find((item) => item.id === selectedId) ?? results.data?.data[0];
  const category = catalog.data?.find((item) => item.id === query.category_id);
  const subcategories = category?.subcategories ?? [];
  const specializations = subcategories.find((item) => item.id === query.subcategory_id)?.specializations ?? [];
  const change = (changes: Partial<Record<ListingQueryKey, string>>) => {
    const fields = formRef.current && !("page" in changes) ? new FormData(formRef.current) : null;
    const draft = fields ? Object.fromEntries(["q", "city", "min_fee", "max_fee"].map((key) => [key, String(fields.get(key) ?? "")])) : {};
    const next = updateListingQuery(url, { ...draft, ...changes });
    router.push(`${pathname}${next ? `?${next}` : ""}`, { scroll: false });
  };
  const totalPages = results.data ? Math.ceil(results.data.meta.total / results.data.meta.per_page) : 0;
  const currentPage = results.data?.meta.current_page ?? query.page ?? 1;

  return <div className="marketplace-search">
    <section className="market-hero">
      <p className="market-eyebrow">KHÁM PHÁ CÙNG TUTORLINK</p>
      <h1>Khám phá dịch vụ phù hợp với mục tiêu của bạn</h1>
      <p>Học tập, rèn luyện sức khỏe, phát triển kỹ năng — cùng những người dạy phù hợp.</p>
    </section>
    <section className="market-catalog" aria-label="Nhóm dịch vụ">
      {catalog.loading ? <StatePanel kind="loading" message="Đang tải danh mục…" /> : catalog.error ? <StatePanel kind="error" message={catalog.error.message} action={<ErrorActions onRetry={catalog.reload} />} /> : <div className="market-category-tabs">
        <button type="button" aria-pressed={!query.category_id} onClick={() => change({ category: "" })}>Tất cả dịch vụ</button>
        {catalog.data?.map((item) => <button type="button" aria-pressed={query.category_id === item.id} key={item.id} onClick={() => change({ category: String(item.id) })}>{item.name}</button>)}
      </div>}
    </section>
    <form ref={formRef} key={url} className="market-filter-form" onSubmit={(event) => {
      event.preventDefault();
      const form = new FormData(event.currentTarget);
      change({ q: String(form.get("q") ?? ""), city: String(form.get("city") ?? ""), min_fee: String(form.get("min_fee") ?? ""), max_fee: String(form.get("max_fee") ?? "") });
    }}>
      <div className="market-search-line">
        <Field label="Tìm dịch vụ" htmlFor="market-q"><input id="market-q" name="q" type="search" defaultValue={query.q ?? ""} placeholder="Tìm theo tên dịch vụ, kỹ năng…" /></Field>
        <Button type="submit">Tìm kiếm</Button>
      </div>
      <div className="market-filter-grid">
        <Field label="Danh mục" htmlFor="market-subcategory"><select id="market-subcategory" value={query.subcategory_id ?? ""} disabled={!category} onChange={(event) => change({ subcategory: event.target.value })}><option value="">Tất cả</option>{subcategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
        <Field label="Chuyên môn" htmlFor="market-specialization"><select id="market-specialization" value={query.specialization_id ?? ""} disabled={!query.subcategory_id} onChange={(event) => change({ specialization: event.target.value })}><option value="">Tất cả</option>{specializations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
        <Field label="Hình thức học" htmlFor="market-mode"><select id="market-mode" value={query.delivery_mode ?? ""} onChange={(event) => change({ delivery_mode: event.target.value })}><option value="">Tất cả</option>{Object.entries(deliveryLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
        <Field label="Thành phố" htmlFor="market-city"><input id="market-city" name="city" defaultValue={query.city ?? ""} placeholder="Tất cả thành phố" /></Field>
        <Field label="Đơn vị phí" htmlFor="market-unit"><select id="market-unit" value={query.price_unit ?? ""} onChange={(event) => change({ price_unit: event.target.value })}><option value="">Tất cả</option><option value="hour">Giờ</option><option value="session">Buổi</option><option value="package">Gói</option></select></Field>
        <Field label="Sắp xếp" htmlFor="market-sort"><select id="market-sort" value={query.sort} onChange={(event) => change({ sort: event.target.value })}><option value="newest">Mới nhất</option><option value="price_asc" disabled={!query.price_unit}>Phí tăng dần</option><option value="price_desc" disabled={!query.price_unit}>Phí giảm dần</option></select></Field>
        <Field label="Phí tối thiểu (VND)" htmlFor="market-min" hint={!query.price_unit ? "Chọn đơn vị phí để lọc giá." : undefined}><input id="market-min" name="min_fee" type="number" min="0" step="1" disabled={!query.price_unit} defaultValue={query.min_fee ?? ""} /></Field>
        <Field label="Phí tối đa (VND)" htmlFor="market-max"><input id="market-max" name="max_fee" type="number" min={query.min_fee ?? 0} step="1" disabled={!query.price_unit} defaultValue={query.max_fee ?? ""} /></Field>
        <Field label="Dịch vụ mỗi trang" htmlFor="market-size"><select id="market-size" value={query.per_page} onChange={(event) => change({ per_page: event.target.value })}>{[...new Set([6, 12, 24, query.per_page ?? 6])].map((size) => <option key={size} value={size}>{size}</option>)}</select></Field>
      </div>
      <div className="market-filter-actions"><Button type="submit" variant="secondary">Áp dụng bộ lọc</Button><Link href={pathname} className="market-reset">Xóa bộ lọc</Link></div>
    </form>
    <section className="market-results" aria-label="Kết quả tìm dịch vụ" aria-busy={results.loading}>
      {results.loading ? <StatePanel kind="loading" message="Đang tìm dịch vụ phù hợp…" /> : results.error ? <StatePanel kind="error" message={results.error.status === 404 ? "Dịch vụ tìm kiếm chưa sẵn sàng trên máy chủ. Vui lòng thử lại sau." : describeApiError(results.error)} action={<ErrorActions login={results.error.status === 401 || results.error.status === 419} onRetry={results.reload} />} /> : results.data && <>
        <p className="market-count" role="status">Hiển thị <strong>{results.data.data.length}</strong> trong <strong>{results.data.meta.total}</strong> dịch vụ</p>
        {!results.data.data.length ? <StatePanel kind="empty" message="Không tìm thấy dịch vụ phù hợp. Hãy thay đổi hoặc xóa bộ lọc." action={<Link className="button button-secondary" href={pathname}>Xóa bộ lọc</Link>} /> : <div className="market-result-layout">
          <div className="market-list">{results.data.data.map((listing) => <article className={`market-row${selected?.id === listing.id ? " is-selected" : ""}`} key={listing.id}>
            <div className="market-initials" aria-hidden="true">{initials(listing.tutor?.full_name ?? listing.title)}</div>
            <div className="market-row-body"><h2><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>{listing.title}</Link></h2>
              {listing.tutor && <Link className="market-tutor" href={`/tutors/${encodeURIComponent(listing.tutor.slug)}`}>{listing.tutor.full_name}</Link>}
              <ListingFacts listing={listing} /><p className="market-description">{listing.description}</p><ListingRating listing={listing} />
            </div>
            <div className="market-row-actions"><strong>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</strong><Link className="button button-primary" href={`/marketplace/${encodeURIComponent(listing.slug)}`}>Xem dịch vụ →</Link><Button variant="secondary" aria-pressed={selected?.id === listing.id} onClick={() => setSelectedId(listing.id)}>Xem nhanh</Button></div>
          </article>)}</div>
          {selected && <aside className="market-preview" aria-label="Dịch vụ đang xem nhanh"><p className="market-eyebrow">ĐANG XEM NHANH</p><div className="market-preview-initials" aria-hidden="true">{initials(selected.tutor?.full_name ?? selected.title)}</div><h2>{selected.title}</h2>{selected.tutor && <p>{selected.tutor.full_name}</p>}<ListingFacts listing={selected} /><p>{selected.description}</p><ListingRating listing={selected} /><strong>{formatListingPrice(selected.price_from_minor, selected.currency, selected.price_unit)}</strong><Link className="button button-primary" href={`/marketplace/${encodeURIComponent(selected.slug)}`}>Xem chi tiết dịch vụ</Link>{selected.tutor && <Link className="button button-secondary" href={`/tutors/${encodeURIComponent(selected.tutor.slug)}`}>Hồ sơ người dạy</Link>}</aside>}
        </div>}
        {totalPages > 1 && <nav className="market-pagination" aria-label="Phân trang dịch vụ"><Button variant="secondary" disabled={currentPage <= 1} onClick={() => change({ page: String(currentPage - 1) })}>← Trước</Button><span aria-live="polite">Trang {currentPage} / {totalPages}</span><Button variant="secondary" disabled={currentPage >= totalPages} onClick={() => change({ page: String(currentPage + 1) })}>Sau →</Button></nav>}
      </>}
    </section>
  </div>;
}
