"use client";

import {OpenChatButton} from "./open-chat-button";
import Image from "next/image";
import Link from "next/link";
import { useCallback, useState, type ReactNode } from "react";
import { getPublicTutor, getTutorReviews, searchListings, deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import type { PublicListing, TutorReviewCollection, TutorReviewAggregate } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { PublicAvailability } from "./public-availability";
import { FavoriteTutorButton } from "./favorite-tutor-button";
import { Badge, Button, ErrorActions, StatePanel } from "./ui";

function TutorSkeleton({ className = "" }: { className?: string }) {
  return <span className={`tutor-loading-skeleton ${className}`} />;
}

type TutorProfileIconName = "experience" | "services" | "specialty" | "location" | "teaching" | "subjects" | "warmup" | "practice" | "feedback" | "next";

function TutorProfileIcon({ name }: { name: TutorProfileIconName }) {
  const shapes: Record<TutorProfileIconName, ReactNode> = {
    experience: <><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18m-12 0v2h6v-2" /></>,
    services: <><rect x="5" y="3" width="14" height="18" rx="2" /><path d="M9 8h6M9 12h6M9 16h4" /></>,
    specialty: <><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="4" /><path d="m15 9 4-4" /></>,
    location: <><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></>,
    teaching: <><path d="m3 9 9-5 9 5-9 5-9-5Z" /><path d="M7 11v5c3 2 7 2 10 0v-5M21 9v6" /></>,
    subjects: <><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21V5.5Z" /><path d="M4 6v15m5-13h7m-7 4h7" /></>,
    warmup: <><path d="M4 5h16v11H8l-4 4V5Z" /><path d="M8 9h8m-8 4h5" /></>,
    practice: <><path d="M5 3h10l4 4v14H5V3Z" /><path d="M14 3v5h5M8 12h8m-8 4h8" /></>,
    feedback: <><path d="m5 12 4 4L19 6" /><circle cx="12" cy="12" r="9" /></>,
    next: <><path d="M7 17 17 7M7 7h10v10" /></>
  };

  return <svg aria-hidden="true" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" viewBox="0 0 24 24">{shapes[name]}</svg>;
}

function listingCover(category?: string) {
  const value = (category ?? "").toLocaleLowerCase("vi");
  if (value.includes("fitness")) return "/featured/fitness.svg";
  if (value.includes("sport") || value.includes("thể thao")) return "/featured/sports.svg";
  return "/featured/education.svg";
}

type RelatedTutor = { listing: PublicListing; rating: TutorReviewAggregate | null };
type TutorProfileTab = "overview" | "services" | "reviews" | "schedule";

async function loadRelatedTutors(currentSlug: string): Promise<RelatedTutor[]> {
  const response = await searchListings({ sort: "newest", per_page: 30 });
  const seen = new Set<string>([currentSlug]);
  const listings = response.data.filter((listing) => {
    if (!listing.tutor_slug || seen.has(listing.tutor_slug)) return false;
    seen.add(listing.tutor_slug);
    return true;
  }).slice(0, 3);

  return Promise.all(listings.map(async (listing) => {
    try {
      return { listing, rating: listing.tutor_slug ? (await getTutorReviews(listing.tutor_slug, 1)).aggregate : null };
    } catch {
      return { listing, rating: null };
    }
  }));
}

function tutorInitials(value?: string | null) {
  return (value || "TutorLink").trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi");
}

function formatReviewDate(value: string | null) {
  if (!value) return "Học viên đã xác minh";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Học viên đã xác minh" : new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric" }).format(date);
}

function PublicTutorLoading() {
  return <section className="content-stack public-tutor-page public-tutor-loading" role="status" aria-live="polite" aria-label="Đang tải hồ sơ gia sư">
    <div className="tutor-profile-inner tutor-loading-inner">
    <div className="tutor-loading-status"><span className="tutor-loading-spinner" aria-hidden="true" /><span><strong>Đang chuẩn bị hồ sơ gia sư</strong><small>Đang tải thông tin, dịch vụ và lịch khả dụng…</small></span></div>
    <div className="tutor-loading-hero" aria-hidden="true"><div className="tutor-loading-photo"><TutorSkeleton className="tutor-loading-avatar" /></div><div className="tutor-loading-hero-copy"><TutorSkeleton className="tutor-loading-eyebrow" /><TutorSkeleton className="tutor-loading-name" /><TutorSkeleton className="tutor-loading-headline" /><TutorSkeleton className="tutor-loading-stats" /></div><div className="tutor-loading-hero-actions"><TutorSkeleton className="tutor-loading-action" /><TutorSkeleton className="tutor-loading-action" /></div></div>
    <div className="tutor-loading-statbar" aria-hidden="true">{[0, 1, 2, 3].map((item) => <div key={item}><TutorSkeleton className="tutor-loading-stat-icon" /><span><TutorSkeleton className="tutor-loading-stat-title" /><TutorSkeleton className="tutor-loading-stat-caption" /></span></div>)}</div>
    <div className="tutor-loading-content-grid" aria-hidden="true">
      <div className="tutor-loading-content-main">
        <section className="tutor-loading-bio"><TutorSkeleton className="tutor-loading-section-title" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-line tutor-loading-line-short" /></section>
        <section className="tutor-loading-expertise"><TutorSkeleton className="tutor-loading-section-title" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-line tutor-loading-line-short" /></section>
        <section className="tutor-loading-listings"><div className="tutor-loading-listings-heading"><TutorSkeleton className="tutor-loading-section-title" /><TutorSkeleton className="tutor-loading-count" /></div><div className="public-listings-grid"><article className="card tutor-loading-listing"><TutorSkeleton className="tutor-loading-tag" /><TutorSkeleton className="tutor-loading-card-title" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-price" /></article><article className="card tutor-loading-listing"><TutorSkeleton className="tutor-loading-tag" /><TutorSkeleton className="tutor-loading-card-title" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-price" /></article></div></section>
      </div>
      <aside className="tutor-loading-content-side"><section className="card tutor-loading-availability"><TutorSkeleton className="tutor-loading-section-title" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-line" /><TutorSkeleton className="tutor-loading-line tutor-loading-line-short" /></section></aside>
    </div>
    </div>
  </section>;
}

export function PublicTutor({ slug, initialTab = "overview" }: { slug: string; initialTab?: TutorProfileTab }) {
  const [activeTab, setActiveTab] = useState<TutorProfileTab>(initialTab);
  const [page, setPage] = useState(1);
  const [reviewPage, setReviewPage] = useState(1);
  const [listingSlide, setListingSlide] = useState(0);
  const load = useCallback(() => getPublicTutor(slug, page), [slug, page]);
  const { data, error, loading, reload } = useAsyncData(load);
  const loadReviews = useCallback(() => getTutorReviews(slug, reviewPage), [slug, reviewPage]);
  const reviews = useAsyncData<TutorReviewCollection>(loadReviews);
  const loadRelated = useCallback(() => loadRelatedTutors(slug), [slug]);
  const relatedTutors = useAsyncData<RelatedTutor[]>(loadRelated);
  const selectTab = useCallback((tab: TutorProfileTab) => {
    setActiveTab(tab);
    const url = new URL(window.location.href);
    if (tab === "overview") url.searchParams.delete("tab");
    else url.searchParams.set("tab", tab);
    window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
  }, []);

  if (loading) return <PublicTutorLoading />;
  if (error) {
    const message = error.status === 401
      ? "Phiên đăng nhập đã hết hạn. Đăng nhập rồi thử lại."
      : error.status === 419
        ? "Phiên bảo mật đã hết hạn. Hãy thử tải lại."
        : error.status === 403 || error.status === 404
          ? "Không tìm thấy hồ sơ gia sư công khai."
          : error.status === 429
            ? "Bạn đang gửi yêu cầu quá nhanh. Hãy chờ một chút rồi thử lại."
            : error.status == null
              ? "Không thể kết nối tới máy chủ. Kiểm tra mạng rồi thử lại."
              : "Máy chủ đang gặp sự cố. Hãy thử lại sau.";
    return (
      <section className="content-stack">
        <Link href="/marketplace" className="text-button">← Quay lại Marketplace</Link>
        <StatePanel kind="error" message={message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} />
      </section>
    );
  }
  if (!data) return <StatePanel kind="empty" message="Chưa có thông tin hồ sơ gia sư." />;

  const name = data.name?.trim() || "Gia sư";
  const initial = name.split(/\s+/).slice(-1)[0]?.[0]?.toLocaleUpperCase() ?? "G";
  const primarySpecialization = data.specializations[0];
  const primaryListing = data.listings[0];
  const chatContact = primaryListing ? {
    tutorName: name,
    listingTitle: primaryListing.title,
    category: primaryListing.specialization_name || primarySpecialization?.name,
    delivery: deliveryLabels[primaryListing.delivery_mode],
    price: formatListingPrice(primaryListing.price_from_minor, primaryListing.currency, primaryListing.price_unit),
    image: listingCover(primarySpecialization?.category || primarySpecialization?.name),
  } : undefined;
  const city = data.listings.find((listing) => listing.city)?.city;
  const listingCount = data.listings_meta.has_more ? `${data.listings.length}+` : String(data.listings.length);
  const visibleListings = data.listings.slice(listingSlide * 3, listingSlide * 3 + 3);
  const hasListingNavigation = data.listings.length > 3 || data.listings_meta.has_more || page > 1;
  const canGoToPreviousListings = listingSlide > 0 || page > 1;
  const canGoToNextListings = (listingSlide + 1) * 3 < data.listings.length || data.listings_meta.has_more;
  const availability = data.availability_rules.length
    ? {
        timezone: data.availability_rules[0].timezone,
        rules: data.availability_rules.map((rule) => ({
          weekday: rule.weekday,
          start_time: rule.start_time,
          end_time: rule.end_time,
        })),
      }
    : null;

  return (
    <section className="content-stack public-tutor-page tutor-profile-redesign">
      <div className="tutor-profile-inner">
      <nav className="public-breadcrumb tutor-profile-breadcrumb" aria-label="Đường dẫn">
        <Link href="/marketplace">Gia sư</Link><span aria-hidden="true">›</span>
        {primarySpecialization ? <><span>{primarySpecialization.name}</span><span aria-hidden="true">›</span></> : null}
        <span aria-current="page">Hồ sơ gia sư</span>
      </nav>

      <section className="tutor-profile-hero" aria-labelledby="tutor-profile-name">
        <div className="tutor-profile-media" aria-label={`Ảnh đại diện ${name}`}>
          <span className="public-avatar public-avatar-large" aria-hidden="true">{initial}</span>
          <span className="tutor-profile-media-note">Hồ sơ TutorLink</span>
        </div>
        <div className="tutor-profile-intro">
          <p className="eyebrow">HỒ SƠ GIA SƯ</p>
          <h1 id="tutor-profile-name">{name}<span className="tutor-profile-verified" aria-label="Gia sư đã xác minh">✓</span></h1>
          <p className="tutor-profile-headline">{data.headline || "Gia sư / Huấn luyện viên"}</p>
          {city ? <p className="tutor-profile-location"><span><TutorProfileIcon name="location" /></span>{city}</p> : null}
          {reviews.data?.aggregate.count ? <button className="tutor-profile-rating" onClick={() => selectTab("reviews")} type="button"><span aria-hidden="true">★</span><strong>{reviews.data.aggregate.average?.toFixed(1)}</strong><small>({reviews.data.aggregate.count} đánh giá)</small></button> : reviews.loading ? <span className="tutor-profile-rating-loading">Đang tải đánh giá…</span> : null}
        </div>
        <div className="tutor-profile-actions">
          <OpenChatButton peerId={data.messaging_user_id} contact={chatContact} />
          <FavoriteTutorButton slug={data.slug} />
        </div>
      </section>

      <nav className="tutor-profile-tabs" role="tablist" aria-label="Nội dung hồ sơ gia sư">
        {([
          ["overview", "Tổng quan"],
          ["services", "Dịch vụ"],
          ["reviews", "Đánh giá"],
          ["schedule", "Lịch học"],
        ] as const).map(([tab, label]) => <button
          aria-controls="tutor-profile-active-panel"
          aria-selected={activeTab === tab}
          className={activeTab === tab ? "is-active" : ""}
          id={`tutor-profile-tab-${tab}`}
          key={tab}
          onClick={() => selectTab(tab)}
          role="tab"
          tabIndex={activeTab === tab ? 0 : -1}
          type="button"
        >{label}</button>)}
      </nav>

      {activeTab === "overview" ? <div className="tutor-profile-tab-panel tutor-profile-overview-panel" id="tutor-profile-active-panel" role="tabpanel" aria-labelledby="tutor-profile-tab-overview" tabIndex={0}>
      <section className="tutor-profile-stats" aria-label="Thông tin nhanh">
        <div><span className="tutor-profile-stat-icon"><TutorProfileIcon name="experience" /></span><span><strong>{data.experience_years}+ năm</strong><small>Kinh nghiệm giảng dạy</small></span></div>
        <div><span className="tutor-profile-stat-icon"><TutorProfileIcon name="services" /></span><span><strong>{listingCount} dịch vụ</strong><small>Đang hoạt động</small></span></div>
        <div><span className="tutor-profile-stat-icon"><TutorProfileIcon name="specialty" /></span><span><strong>{primarySpecialization?.category || primarySpecialization?.name || "Chuyên môn"}</strong><small>Lĩnh vực giảng dạy</small></span></div>
        <div><span className="tutor-profile-stat-icon"><TutorProfileIcon name="location" /></span><span><strong>{city || "Linh hoạt"}</strong><small>Khu vực hoạt động</small></span></div>
      </section>

      <div className="tutor-profile-body" id="overview">
        <div className="tutor-profile-content">
          {data.bio ? <section className="tutor-profile-about" aria-labelledby="tutor-bio-title"><h2 id="tutor-bio-title">Giới thiệu bản thân</h2><p>{data.bio}</p></section> : null}

          <section className="tutor-profile-expertise" aria-labelledby="tutor-expertise-title">
            <h2 id="tutor-expertise-title">Chuyên môn &amp; Kinh nghiệm</h2>
            <div className="tutor-profile-expertise-row"><span className="tutor-profile-expertise-icon"><TutorProfileIcon name="teaching" /></span><span><strong>Kinh nghiệm giảng dạy</strong><small>{data.experience_years} năm kinh nghiệm trong lĩnh vực {primarySpecialization?.name || "giảng dạy"}.</small></span></div>
            {data.specializations.length ? <div className="tutor-profile-expertise-row"><span className="tutor-profile-expertise-icon"><TutorProfileIcon name="subjects" /></span><span><strong>Chuyên môn</strong><span className="tutor-profile-tags">{data.specializations.map((item) => <Badge key={item.id} tone="neutral">{item.name}</Badge>)}</span></span></div> : null}
          </section>

        </div>

        <aside className="tutor-profile-sidebar" aria-label="Liên hệ gia sư">
          <section className="tutor-profile-booking-card">
            <span className="tutor-profile-booking-icon"><TutorProfileIcon name="warmup" /></span>
            <h2>Bắt đầu học cùng {name}</h2>
            <p>Trao đổi mục tiêu và thống nhất lịch phù hợp với gia sư.</p>
            <button className="button button-primary" onClick={() => setActiveTab("schedule")} type="button">Xem lịch học</button>
            <OpenChatButton peerId={data.messaging_user_id} contact={chatContact} label="Nhắn tin tư vấn" />
          </section>
        </aside>
      </div>

      <section className="tutor-profile-journey" aria-labelledby="tutor-journey-title">
        <div className="tutor-profile-journey-heading">
          <p className="tutor-profile-services-eyebrow">ĐỒNG HÀNH CÙNG BẠN</p>
          <h2 id="tutor-journey-title">Một buổi học với {name} có gì?</h2>
          <p>Lộ trình rõ ràng, nội dung thực tế và phản hồi cá nhân sau mỗi buổi.</p>
        </div>
        <div className="tutor-profile-journey-grid">
          <article><span className="tutor-profile-journey-icon"><TutorProfileIcon name="warmup" /></span><strong className="tutor-profile-journey-number">01</strong><h3>Khởi động tự nhiên</h3><p>Trò chuyện ngắn để khởi động vốn từ và tạo cảm giác thoải mái.</p></article>
          <article><span className="tutor-profile-journey-icon"><TutorProfileIcon name="practice" /></span><strong className="tutor-profile-journey-number">02</strong><h3>Học qua tình huống</h3><p>Thực hành nội dung sát với mục tiêu giao tiếp hoặc kỹ năng của bạn.</p></article>
          <article><span className="tutor-profile-journey-icon"><TutorProfileIcon name="feedback" /></span><strong className="tutor-profile-journey-number">03</strong><h3>Phản hồi tức thì</h3><p>Sửa phát âm, từ vựng và cách diễn đạt ngay khi thực hành.</p></article>
          <article><span className="tutor-profile-journey-icon"><TutorProfileIcon name="next" /></span><strong className="tutor-profile-journey-number">04</strong><h3>Kế hoạch tiếp theo</h3><p>Nhận ghi chú cùng bài luyện ngắn để duy trì tiến bộ giữa hai buổi.</p></article>
        </div>
      </section>

      <section className="tutor-profile-start-section" aria-labelledby="tutor-start-title">
        <div className="tutor-profile-journey-heading">
          <p className="tutor-profile-services-eyebrow">BẮT ĐẦU THẬT ĐƠN GIẢN</p>
          <h2 id="tutor-start-title">Học cùng {name} chỉ trong 3 bước</h2>
        </div>
        <ol className="tutor-profile-start-steps">
          <li><span>1</span><h3>Chọn dịch vụ</h3><p>Chọn nội dung phù hợp với mục tiêu học tập của bạn.</p></li>
          <li><span>2</span><h3>Nhắn tin trao đổi</h3><p>Thống nhất trình độ, nhu cầu và thời gian thuận tiện.</p></li>
          <li><span>3</span><h3>Bắt đầu học</h3><p>Tham gia buổi học và theo dõi tiến bộ trên TutorLink.</p></li>
        </ol>
      </section>

      <section className="tutor-profile-faq-section" aria-labelledby="tutor-faq-title">
        <div className="tutor-profile-faq-intro">
          <p className="tutor-profile-services-eyebrow">CÂU HỎI THƯỜNG GẶP</p>
          <h2 id="tutor-faq-title">Thông tin trước khi bắt đầu</h2>
          <p>Bạn vẫn còn câu hỏi? Hãy nhắn tin trực tiếp để {name} tư vấn lộ trình phù hợp.</p>
          <div className="tutor-profile-faq-contact"><OpenChatButton className="tutor-profile-contact-button" label={`Nhắn tin với ${name} →`} peerId={data.messaging_user_id} contact={chatContact} /></div>
        </div>
        <div className="tutor-profile-faq-list">
          <details open><summary>{name} có dạy người mới bắt đầu không?<span aria-hidden="true">+</span></summary><p>Có. Nội dung có thể được điều chỉnh theo trình độ hiện tại, từ nền tảng phát âm đến giao tiếp cơ bản.</p></details>
          <details><summary>Tôi có thể đổi lịch học không?<span aria-hidden="true">+</span></summary><p>Bạn có thể nhắn tin trực tiếp với gia sư để trao đổi và thống nhất thời gian phù hợp.</p></details>
          <details><summary>Buổi học diễn ra trên nền tảng nào?<span aria-hidden="true">+</span></summary><p>Hình thức học được ghi trong từng dịch vụ. Hãy xem chi tiết dịch vụ hoặc nhắn tin để xác nhận cách học.</p></details>
          <details><summary>Có bài tập sau mỗi buổi học không?<span aria-hidden="true">+</span></summary><p>Nội dung luyện tập có thể được thống nhất cùng gia sư dựa trên mục tiêu và tiến độ của bạn.</p></details>
        </div>
      </section>

      <section className="tutor-profile-explore-section" aria-labelledby="tutor-explore-title">
        <div className="tutor-profile-explore-heading">
          <div><p className="tutor-profile-services-eyebrow">KHÁM PHÁ THÊM</p><h2 id="tutor-explore-title">Tutor bạn có thể quan tâm</h2></div>
          <Link className="tutor-profile-inline-link" href="/marketplace">Xem tất cả Tutor <span aria-hidden="true">→</span></Link>
        </div>
        {relatedTutors.loading ? <div className="tutor-profile-related-empty" role="status">Đang tìm thêm tutor phù hợp…</div> : relatedTutors.data?.length ? <div className="tutor-profile-related-grid">
          {relatedTutors.data.map(({ listing, rating }) => {
            const tutorName = listing.tutor_name || "TutorLink Tutor";
            const tutorHref = listing.tutor_slug ? `/tutors/${encodeURIComponent(listing.tutor_slug)}` : `/marketplace/${encodeURIComponent(listing.slug)}`;
            return <Link className="tutor-profile-related-card" href={tutorHref} key={listing.tutor_slug || listing.slug}>
              <span className="tutor-profile-related-image"><Image alt={`Ảnh minh họa ${listing.category_name || "gia sư"}`} fill sizes="(max-width: 560px) 100vw, 33vw" src={listingCover(listing.category_name)} /></span>
              <span className="tutor-profile-related-copy"><strong>{tutorName}<i aria-label="Hồ sơ đã xác minh">✓</i></strong><small>{[listing.specialization_name || listing.category_name, listing.city].filter(Boolean).join(" · ")}</small><span>{rating?.count ? <>★ {rating.average?.toFixed(1)} ({rating.count} đánh giá)</> : "Gia sư TutorLink"}</span><b>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</b></span>
            </Link>;
          })}
        </div> : <div className="tutor-profile-related-empty">Chưa có tutor khác để gợi ý. <Link href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link></div>}
      </section>

      <section className="tutor-profile-cta" aria-labelledby="tutor-profile-cta-title">
        <div className="tutor-profile-cta-copy">
          <p>SẴN SÀNG TIẾN BỘ?</p>
          <h2 id="tutor-profile-cta-title">Bắt đầu hành trình học tập cùng {name}.</h2>
          <span>Nhắn tin để trao đổi mục tiêu và tìm thời gian học phù hợp nhất với bạn.</span>
        </div>
        <div className="tutor-profile-cta-action">
          <OpenChatButton className="tutor-profile-cta-button" label={`Nhắn tin với ${name}`} peerId={data.messaging_user_id} contact={chatContact} />
        </div>
      </section>
      </div> : null}

      {activeTab === "services" ? <section className="tutor-profile-tab-panel tutor-profile-services" id="tutor-profile-active-panel" role="tabpanel" aria-labelledby="tutor-profile-tab-services" tabIndex={0}>
        <div className="tutor-profile-section-heading">
          <div><p className="tutor-profile-services-eyebrow">DỊCH VỤ CỦA {name.toLocaleUpperCase("vi")}</p><h2 id="tutor-listings-title">Dịch vụ đang cung cấp <span>({listingCount})</span></h2></div>
          <div className="tutor-profile-services-contact"><OpenChatButton className="tutor-profile-contact-button" label={`Trao đổi với ${name} →`} peerId={data.messaging_user_id} contact={chatContact} /></div>
        </div>
        {data.listings.length ? <div className={`tutor-profile-carousel${hasListingNavigation ? " has-navigation" : ""}`}>
          {hasListingNavigation ? <button className="tutor-profile-carousel-arrow" type="button" aria-label="Xem các dịch vụ trước" disabled={!canGoToPreviousListings} onClick={() => {
            if (listingSlide > 0) setListingSlide(listingSlide - 1);
            else if (page > 1) { setPage(page - 1); setListingSlide(2); }
          }}><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m15 18-6-6 6-6" /></svg></button> : null}
          <div className="public-listings-grid">
          {visibleListings.map((listing) => {
            const formattedPrice = formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit);
            const [amount, ...unitParts] = formattedPrice.split(" / ");
            const category = primarySpecialization?.category || primarySpecialization?.name || "Dịch vụ";
            return <article className="card public-listing-card tutor-profile-service-card" key={listing.slug}>
              <Link className="tutor-profile-service-cover" href={`/marketplace/${encodeURIComponent(listing.slug)}`} aria-label={`Xem dịch vụ ${listing.title}`}>
                <Image alt={`Ảnh minh họa ${category}`} fill sizes="(max-width: 560px) 100vw, (max-width: 860px) 50vw, 33vw" src={listingCover(category)} />
                <span>{category}</span>
              </Link>
              <div className="tutor-profile-service-body">
                <h3><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>{listing.title}</Link></h3>
                <p className="tutor-profile-service-mode"><i aria-hidden="true" />{deliveryLabels[listing.delivery_mode]}{listing.delivery_mode === "offline" && listing.city ? ` tại ${listing.city}` : ""}</p>
                <p className="tutor-profile-service-description">{data.bio || `Dịch vụ ${category.toLocaleLowerCase("vi")} do ${name} hướng dẫn.`}</p>
                <div className="tutor-profile-service-footer"><span><strong>{amount}</strong><small>/ {unitParts.join(" / ")}</small></span><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>Xem dịch vụ <span aria-hidden="true">→</span></Link></div>
              </div>
            </article>;
          })}
          </div>
          {hasListingNavigation ? <button className="tutor-profile-carousel-arrow" type="button" aria-label="Xem các dịch vụ tiếp theo" disabled={!canGoToNextListings} onClick={() => {
            if ((listingSlide + 1) * 3 < data.listings.length) setListingSlide(listingSlide + 1);
            else if (data.listings_meta.has_more) { setPage(data.listings_meta.next_page ?? page + 1); setListingSlide(0); }
          }}><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6" /></svg></button> : null}
        </div> : <StatePanel kind="empty" message="Gia sư chưa có dịch vụ đang công khai." />}
      </section> : null}

      {activeTab === "reviews" ? <section className="tutor-profile-tab-panel tutor-profile-reviews-section" id="tutor-profile-active-panel" aria-labelledby="tutor-profile-tab-reviews" role="tabpanel" tabIndex={0}>
        <div className="tutor-profile-review-summary">
          <p className="tutor-profile-services-eyebrow">ĐÁNH GIÁ ĐÃ XÁC MINH</p>
          <h2 id="tutor-reviews-title">Học viên nói gì về {name}?</h2>
          {reviews.data ? <>
            <div className="tutor-profile-review-score"><strong>{reviews.data.aggregate.average?.toFixed(1) ?? "—"}</strong><span><b aria-label="5 sao">★★★★★</b><small>{reviews.data.aggregate.count} đánh giá</small></span></div>
            <div className="tutor-profile-rating-bars" aria-label="Phân bố đánh giá">
              {[5, 4, 3].map((rating) => {
                const count = reviews.data!.aggregate.histogram[String(rating) as keyof typeof reviews.data.aggregate.histogram];
                const total = reviews.data!.aggregate.count;
                return <div key={rating}><span>{rating} sao</span><i><b style={{ width: `${total ? (count / total) * 100 : 0}%` }} /></i><small>{count}</small></div>;
              })}
            </div>
          </> : <p className="tutor-profile-review-loading">{reviews.loading ? "Đang tải đánh giá…" : "Chưa có đánh giá công khai."}</p>}
        </div>
        <div className="tutor-profile-review-list">
          {reviews.error ? <div className="tutor-profile-review-empty">Không tải được đánh giá. <Button variant="secondary" onClick={reviews.reload}>Thử lại</Button></div> : null}
          {reviews.data?.data.length ? reviews.data.data.map((review) => <article className="tutor-profile-review-card" key={review.id}>
            <div className="tutor-profile-review-author"><span aria-hidden="true">{tutorInitials(review.author_name)}</span><div><strong>{review.author_name}</strong><b aria-label={`${review.rating} trên 5 sao`}>{"★".repeat(review.rating)}{"☆".repeat(5 - review.rating)}</b></div><time>{formatReviewDate(review.created_at)}</time></div>
            <p>{review.comment || "Học viên đã gửi đánh giá cho buổi học này."}</p>
          </article>) : !reviews.error ? <div className="tutor-profile-review-empty">{reviews.loading ? "Đang tải nhận xét…" : "Những đánh giá đầu tiên sẽ xuất hiện tại đây."}</div> : null}
          {reviews.data && reviews.data.meta.total > reviews.data.meta.per_page ? <nav className="review-pagination" aria-label="Phân trang đánh giá">
            <Button variant="outline" disabled={reviews.loading || reviews.data.meta.current_page <= 1} onClick={() => setReviewPage((current) => Math.max(1, current - 1))}><span aria-hidden="true">←</span> Trước</Button>
            <span aria-live="polite">Trang {reviews.data.meta.current_page} / {Math.ceil(reviews.data.meta.total / reviews.data.meta.per_page)}</span>
            <Button variant="outline" disabled={reviews.loading || reviews.data.meta.current_page >= Math.ceil(reviews.data.meta.total / reviews.data.meta.per_page)} onClick={() => setReviewPage((current) => current + 1)}>Tiếp <span aria-hidden="true">→</span></Button>
          </nav> : null}
        </div>
      </section> : null}

      {activeTab === "schedule" ? <section className="tutor-profile-tab-panel tutor-profile-schedule-panel" id="tutor-profile-active-panel" aria-labelledby="tutor-profile-tab-schedule" role="tabpanel" tabIndex={0}>
        <div className="tutor-profile-schedule-heading"><div><p className="tutor-profile-services-eyebrow">THỜI GIAN CỦA {name.toLocaleUpperCase("vi")}</p><h2>Lịch học khả dụng</h2><p>Tham khảo khung giờ gia sư thường sắp xếp và chọn thời gian phù hợp với bạn.</p></div>{availability ? <OpenChatButton className="tutor-profile-schedule-contact" peerId={data.messaging_user_id} contact={chatContact} label={`Trao đổi lịch với ${name}`} /> : null}</div>
        {availability ? <div className="tutor-profile-availability"><PublicAvailability availability={availability} /></div> : <section className="card tutor-profile-no-availability"><h2>Lịch trống</h2><p>Gia sư chưa công khai khung giờ. Hãy nhắn tin để trao đổi lịch phù hợp.</p><OpenChatButton peerId={data.messaging_user_id} contact={chatContact} label={`Nhắn tin với ${name}`} /></section>}
      </section> : null}
      </div>
    </section>
  );
}
