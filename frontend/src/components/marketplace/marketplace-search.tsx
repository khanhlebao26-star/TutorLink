"use client";

import { Button, ErrorActions, Field, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { readListingQuery, updateListingQuery, type ListingQueryKey } from "@/lib/data/listing-query";
import { deliveryLabels, formatListingPrice, getMarketplaceCatalog, searchListings } from "@/lib/data/marketplace";
import type { PublicListing } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useMemo, useRef } from "react";

type MarketTone = "education" | "fitness" | "sports";
const coverByTone: Record<MarketTone, string> = {
  education: "/featured/education.svg",
  fitness: "/featured/fitness.svg",
  sports: "/featured/sports.svg",
};
function toneFor(category?: string): MarketTone {
  const value = (category ?? "").toLocaleLowerCase("vi");
  if (value.includes("fitness")) return "fitness";
  if (value.includes("sport") || value.includes("thể thao")) return "sports";
  return "education";
}
function categoryLabel(category?: string) {
  const tone = toneFor(category);
  return tone === "fitness" ? "Fitness" : tone === "sports" ? "Thể thao" : "Giáo dục";
}
const initials = (name: string) => name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi");

function ListingCard({ listing }: { listing: PublicListing }) {
  const tone = toneFor(listing.category_name);
  const href = `/marketplace/${encodeURIComponent(listing.slug)}`;
  return <article className={`market-card market-card-${tone} min-w-0 overflow-hidden rounded-[15px] border border-[#e3e8f2] bg-white shadow-[0_8px_26px_#11224b0a] transition-[border-color,box-shadow,transform] hover:-translate-y-[3px] hover:border-[#f3b2d7] hover:shadow-[0_15px_34px_#11224b14]`}>
    <Link className="market-card-cover relative block h-[180px] overflow-hidden bg-[#fff0f8] sm:h-[145px] md:h-[170px]" href={href} aria-label={`Xem dịch vụ ${listing.title}`}>
      <Image src={coverByTone[tone]} alt={`Ảnh minh họa ${categoryLabel(listing.category_name)}`} fill sizes="(max-width: 700px) 100vw, (max-width: 1150px) 50vw, 34vw" />
      <span className="market-card-category">{categoryLabel(listing.category_name)}</span>
    </Link>
    <div className="market-card-content p-4">
      <div className="market-card-heading flex items-start justify-between gap-2.5">
        <div><p className="market-card-kicker">DỊCH VỤ ĐANG HOẠT ĐỘNG</p><h2><Link href={href}>{listing.title}</Link></h2></div>
        <span className="market-card-mode">{deliveryLabels[listing.delivery_mode]}</span>
      </div>
      <Link className="market-card-tutor mt-[13px] flex min-w-0 items-center gap-2.5 text-inherit" href={listing.tutor_slug ? `/tutors/${encodeURIComponent(listing.tutor_slug)}` : href}>
        <span className="market-card-avatar inline-flex h-[35px] w-[35px] flex-[0_0_35px] items-center justify-center rounded-full bg-[linear-gradient(135deg,#ec20a0,#7744ef)] text-[.66rem] font-extrabold text-white" aria-hidden="true">{initials(listing.tutor_name ?? listing.title)}</span>
        <span className="flex min-w-0 flex-1 flex-col gap-0.5"><strong className="overflow-hidden text-ellipsis whitespace-nowrap text-[.76rem]">{listing.tutor_name || "TutorLink Tutor"}</strong><small className="overflow-hidden text-ellipsis whitespace-nowrap text-[.65rem] text-[var(--market-muted)]">{[listing.specialization_name, listing.city].filter(Boolean).join(" · ") || "Gia sư & huấn luyện viên"}</small></span>
        <span className="market-card-chevron" aria-hidden="true">↗</span>
      </Link>
      <p className="market-card-description">{listing.description}</p>
      <div className="market-card-footer flex items-center justify-between gap-2.5 border-t border-[#edf0f6] pt-[13px]"><span className="market-card-price text-[.86rem] font-extrabold text-[var(--market-pink)]">{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</span><Link className="market-card-link inline-flex items-center gap-2 rounded-lg border border-[#f3c4de] px-2.5 py-2 text-[.69rem] font-extrabold text-[var(--market-pink)] transition-colors hover:bg-[var(--market-pink)] hover:text-white" href={href}>Xem dịch vụ <span aria-hidden="true">→</span></Link></div>
    </div>
  </article>;
}

function MarketplaceLoading() {
  return <div className="market-loading" role="status" aria-live="polite" aria-label="Đang tải danh sách dịch vụ">
    <div className="market-loading-message"><span className="market-loading-orbit" aria-hidden="true"><i /><b>✦</b></span><div><strong>Đang tìm người đồng hành phù hợp</strong><span>Đang kết nối với các dịch vụ dành cho mục tiêu của bạn…</span></div></div>
    <div className="market-loading-progress" aria-hidden="true"><i /></div>
    <div className="market-loading-grid" aria-hidden="true">{[0, 1].map((item) => <div className="market-loading-card" key={item}><span className="market-loading-cover" /><span className="market-loading-line market-loading-line-wide" /><span className="market-loading-line market-loading-line-short" /><div><i /><span /><b /></div><span className="market-loading-line market-loading-line-medium" /></div>)}</div>
  </div>;
}

export function MarketplaceSearch() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const url = searchParams.toString();
  const query = useMemo(() => readListingQuery(new URLSearchParams(url)), [url]);
  const catalogLoad = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(catalogLoad, false);
  const listingLoad = useCallback(() => {
    if (query.min_price !== undefined && query.max_price !== undefined && query.min_price > query.max_price) {
      return Promise.reject(new ApiClientError("Phí tối đa phải lớn hơn hoặc bằng phí tối thiểu."));
    }
    return searchListings(query);
  }, [query]);
  const results = useAsyncData(listingLoad, false);
  const formRef = useRef<HTMLFormElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const category = catalog.data?.find((item) => item.id === query.category_id);
  const subcategories = category?.subcategories ?? [];
  const specializations = subcategories.find((item) => item.id === query.subcategory_id)?.specializations ?? [];
  const change = (changes: Partial<Record<ListingQueryKey, string>>) => {
    const fields = formRef.current && !("page" in changes) ? new FormData(formRef.current) : null;
    const draft = fields ? Object.fromEntries(["city", "min_price", "max_price"].map((key) => [key, String(fields.get(key) ?? "")])) : {};
    if (searchRef.current && !("page" in changes)) Object.assign(draft, { q: searchRef.current.value });
    const next = updateListingQuery(url, { ...draft, ...changes });
    router.push(`${pathname}${next ? `?${next}` : ""}`, { scroll: false });
  };
  const totalPages = results.data ? Math.ceil(results.data.meta.total / results.data.meta.per_page) : 0;
  const currentPage = results.data?.meta.current_page ?? query.page ?? 1;

  return <div className="marketplace-search">
    <section className="market-hero border-b border-[#f1edf7] px-[17px] pb-6 pt-[38px] text-center sm:px-6 sm:pb-[30px] sm:pt-[55px]" aria-labelledby="market-title">
      <div className="market-hero-inner mx-auto max-w-[1000px]">
        <p className="market-eyebrow mb-2.5 flex items-center justify-center gap-[9px] text-[.68rem] font-extrabold tracking-[.11em] text-[var(--market-pink)]"><span className="inline-block h-[3px] w-5 rounded bg-[var(--market-pink)]" aria-hidden="true" /> KHÁM PHÁ CÙNG TUTORLINK</p>
        <h1 className="mx-auto mb-3 max-w-[880px] text-[2rem] leading-[1.08] tracking-[-.045em] sm:text-[clamp(2rem,4.2vw,3.35rem)]" id="market-title">Tìm người đồng hành <span className="text-[var(--market-pink)]">phù hợp với bạn</span></h1>
        <p className="market-hero-copy mx-auto mb-[18px] text-[.83rem] leading-[1.6] text-[var(--market-muted)] sm:mb-6 sm:text-base">Gia sư, huấn luyện viên và những dịch vụ giúp bạn tiến gần hơn tới mục tiêu.</p>
        <form key={url} className="market-search-form mx-auto flex max-w-[760px] items-center gap-1 rounded-2xl border border-[#e1e6f2] bg-white p-1.5 shadow-[0_14px_36px_#13234c12] sm:gap-3 sm:p-2" onSubmit={(event) => {
          event.preventDefault();
          const form = new FormData(event.currentTarget);
          change({ q: String(form.get("q") ?? ""), city: String(form.get("city") ?? ""), min_price: String(form.get("min_price") ?? ""), max_price: String(form.get("max_price") ?? "") });
        }}>
          <label className="market-search-input flex min-w-0 flex-1 items-center gap-[7px] px-1.5 sm:gap-3 sm:px-3"><svg className="h-[22px] w-[22px] flex-[0_0_22px] fill-none stroke-[#8591af]" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg><span className="visually-hidden">Tìm dịch vụ</span><input className="min-h-[43px] w-full border-0 bg-transparent p-0 text-[.75rem] text-[var(--market-ink)] outline-none placeholder:text-[#9aa5bd] sm:min-h-12 sm:text-[.92rem]" ref={searchRef} id="market-q" name="q" type="search" defaultValue={query.q ?? ""} placeholder="Bạn muốn học hoặc rèn luyện điều gì?" /></label>
          <Button className="!min-h-[43px] !rounded-[11px] !px-3 !py-[9px] !text-[.7rem] sm:!min-h-12 sm:!px-[22px] sm:!py-3 sm:!text-[.78rem]" type="submit">Tìm kiếm <span aria-hidden="true">→</span></Button>
        </form>
        <div className="market-category-wrap" aria-label="Lọc theo lĩnh vực">
          {catalog.loading ? <span className="market-catalog-loading">Đang tải lĩnh vực…</span> : catalog.error ? <StatePanel kind="error" message={catalog.error.message} action={<ErrorActions onRetry={catalog.reload} />} /> : <div className="market-category-tabs">
            <button type="button" aria-pressed={!query.category_id} onClick={() => change({ category: "" })}>Tất cả</button>
            {catalog.data?.map((item) => <button type="button" aria-pressed={query.category_id === item.id} key={item.id} onClick={() => change({ category: String(item.id) })}>{item.name}</button>)}
          </div>}
        </div>
      </div>
    </section>

    <section className="market-content mx-auto grid max-w-[1480px] grid-cols-1 items-start gap-5 px-[15px] pt-[23px] md:grid-cols-[220px_minmax(0,1fr)] md:px-5 md:pt-[35px] lg:grid-cols-[255px_minmax(0,1fr)] lg:gap-[30px] lg:px-7" aria-label="Danh sách dịch vụ">
      <form ref={formRef} key={url} className="market-filters sticky top-[18px] grid grid-cols-2 gap-[11px] rounded-2xl border border-[var(--market-line)] bg-white p-[15px] shadow-[0_10px_30px_#17254f08] md:flex md:flex-col md:gap-[17px] md:p-5" onSubmit={(event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        change({ city: String(form.get("city") ?? ""), min_price: String(form.get("min_price") ?? ""), max_price: String(form.get("max_price") ?? "") });
      }}>
        <div className="market-filter-heading col-span-full flex items-center justify-between"><div className="flex items-center gap-2.5"><span className="market-filter-icon inline-flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-[#fff0f8] text-[1.15rem] text-[var(--market-pink)]" aria-hidden="true">☷</span><h2 className="m-0 text-base">Bộ lọc</h2></div><Link href={pathname} className="market-reset text-[.75rem] font-bold text-[var(--market-pink)]">Đặt lại</Link></div>
        <Field label="Lĩnh vực" htmlFor="market-category"><select id="market-category" value={query.category_id ?? ""} disabled={catalog.loading || Boolean(catalog.error)} onChange={(event) => change({ category: event.target.value })}><option value="">Tất cả danh mục</option>{catalog.data?.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
        <Field label="Danh mục" htmlFor="market-subcategory"><select id="market-subcategory" value={query.subcategory_id ?? ""} disabled={!category} onChange={(event) => change({ subcategory: event.target.value })}><option value="">Tất cả danh mục con</option>{subcategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
        <Field label="Chuyên môn" htmlFor="market-specialization"><select id="market-specialization" value={query.specialization_id ?? ""} disabled={!query.subcategory_id} onChange={(event) => change({ specialization: event.target.value })}><option value="">Tất cả chuyên môn</option>{specializations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
        <Field label="Hình thức" htmlFor="market-mode"><select id="market-mode" value={query.delivery_mode ?? ""} onChange={(event) => change({ delivery_mode: event.target.value })}><option value="">Tất cả hình thức</option>{Object.entries(deliveryLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
        <Field label="Thành phố" htmlFor="market-city"><input id="market-city" name="city" defaultValue={query.city ?? ""} placeholder="Ví dụ: Hà Nội" /></Field>
        <Field label="Đơn vị phí" htmlFor="market-unit"><select id="market-unit" value={query.price_unit ?? ""} onChange={(event) => change({ price_unit: event.target.value })}><option value="">Tất cả</option><option value="hour">Giờ</option><option value="session">Buổi</option><option value="course">Khóa học</option></select></Field>
        <div className="market-price-fields col-span-full grid grid-cols-2 gap-[9px]"><Field label="Từ (VND)" htmlFor="market-min"><input id="market-min" name="min_price" type="number" min="0" step="1" disabled={!query.price_unit} defaultValue={query.min_price ?? ""} placeholder="Tối thiểu" /></Field><Field label="Đến (VND)" htmlFor="market-max"><input id="market-max" name="max_price" type="number" min={query.min_price ?? 0} step="1" disabled={!query.price_unit} defaultValue={query.max_price ?? ""} placeholder="Tối đa" /></Field></div>
        {!query.price_unit && <p className="market-filter-hint">Chọn đơn vị phí để lọc theo giá.</p>}
        <Button type="submit" variant="secondary">Áp dụng bộ lọc</Button>
      </form>

    <div className="market-results min-w-0" aria-busy={results.loading}>
        <div className="market-results-heading mb-[15px] flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p className="market-eyebrow">DÀNH CHO MỤC TIÊU CỦA BẠN</p><h2>Khám phá dịch vụ</h2></div><div className="market-sort-control grid grid-cols-2 gap-2.5 sm:flex sm:items-end"><Field label="Sắp xếp" htmlFor="market-sort"><select id="market-sort" value={query.sort} onChange={(event) => change({ sort: event.target.value })}><option value="newest">Mới nhất</option><option value="price_asc" disabled={!query.price_unit}>Giá thấp đến cao</option><option value="price_desc" disabled={!query.price_unit}>Giá cao đến thấp</option></select></Field><Field label="Mỗi trang" htmlFor="market-size"><select id="market-size" value={query.per_page} onChange={(event) => change({ per_page: event.target.value })}>{[...new Set([6, 12, 24, query.per_page ?? 6])].map((size) => <option key={size} value={size}>{size}</option>)}</select></Field></div></div>
        {results.loading ? <MarketplaceLoading /> : results.error ? <StatePanel kind="error" message={results.error.status === 404 ? "Dịch vụ tìm kiếm chưa sẵn sàng trên máy chủ. Vui lòng thử lại sau." : describeApiError(results.error)} action={<ErrorActions login={results.error.status === 401 || results.error.status === 419} onRetry={results.reload} />} /> : results.data && <>
          <p className="market-count" role="status">Hiển thị <strong>{results.data.data.length}</strong> trong <strong>{results.data.meta.total}</strong> dịch vụ</p>
          {!results.data.data.length ? <StatePanel kind="empty" message="Không tìm thấy dịch vụ phù hợp. Hãy thay đổi hoặc xóa bộ lọc." action={<Link className="button button-secondary" href={pathname}>Xóa bộ lọc</Link>} /> : <div className="market-card-grid grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-[18px] lg:grid-cols-3">{results.data.data.map((listing) => <ListingCard listing={listing} key={listing.id} />)}</div>}
          {totalPages > 1 && <nav className="market-pagination mt-7 flex flex-wrap items-center justify-center gap-3" aria-label="Phân trang dịch vụ"><Button variant="secondary" disabled={currentPage <= 1} onClick={() => change({ page: String(currentPage - 1) })}>← Trước</Button><span aria-live="polite">Trang {currentPage} / {totalPages}</span><Button variant="secondary" disabled={currentPage >= totalPages} onClick={() => change({ page: String(currentPage + 1) })}>Sau →</Button></nav>}
        </>}
      </div>
    </section>
  </div>;
}
