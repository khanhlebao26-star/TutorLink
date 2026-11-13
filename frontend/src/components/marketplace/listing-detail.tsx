"use client";

import { FavoriteTutorButton } from "@/components/favorites/favorite-tutor-button";
import { OpenChatButton, type ChatContactContext } from "@/components/messaging/open-chat-button";
import { Badge, ErrorActions, StatePanel } from "@/components/shared/ui";
import { deliveryLabels, formatListingPrice, getPublicListing, getPublicTutor, getTutorReviews, searchListings } from "@/lib/data/marketplace";
import type { PublicListing, TutorPublic, TutorReviewCollection } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Image from "next/image";
import Link from "next/link";
import { useCallback } from "react";

const weekdays = ["Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7", "Chủ nhật"];
type DetailExtra = { tutor: TutorPublic | null; reviews: TutorReviewCollection | null };
type RelatedListing = { listing: PublicListing; rating: number | null; reviewCount: number };

function ListingDetailLoading() {
  return <section className="listing-detail-page listing-detail-loading" role="status" aria-live="polite" aria-label="Đang tải thông tin dịch vụ">
    <div className="detail-loading-heading"><span className="detail-loading-spinner" aria-hidden="true"><i /></span><div><strong>Đang chuẩn bị dịch vụ cho bạn</strong><span>Đang tải thông tin gia sư, mức phí và lịch khả dụng…</span></div></div>
    <div className="detail-loading-progress" aria-hidden="true"><i /></div>
    <div className="detail-loading-layout" aria-hidden="true">
      <div className="detail-loading-main"><span className="detail-skeleton detail-skeleton-cover"/><span className="detail-skeleton detail-skeleton-title"/><span className="detail-skeleton detail-skeleton-tutor"/><span className="detail-skeleton detail-skeleton-heading"/><span className="detail-skeleton detail-skeleton-copy"/><span className="detail-skeleton detail-skeleton-copy detail-skeleton-copy-short"/><span className="detail-skeleton detail-skeleton-schedule"/></div>
      <div className="detail-loading-sidebar"><span className="detail-skeleton detail-skeleton-price"/><span className="detail-skeleton detail-skeleton-tutor-card"/><span className="detail-skeleton detail-skeleton-review"/></div>
    </div>
  </section>;
}

function tutorInitials(name?: string | null) {
  return (name ?? "Gia sư").trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi");
}
function reviewAge(date?: string | null) {
  if (!date) return "Học viên đã xác minh";
  const weeks = Math.max(1, Math.floor((Date.now() - new Date(date).getTime()) / (7 * 24 * 60 * 60 * 1000)));
  if (weeks < 5) return `${weeks} tuần trước`;
  const months = Math.max(1, Math.floor(weeks / 4.35));
  return `${months} tháng trước`;
}
function coverFor(listing: PublicListing) {
  const category = (listing.category_name ?? "").toLocaleLowerCase("vi");
  if (category.includes("fitness")) return "/featured/fitness.svg";
  if (category.includes("sport") || category.includes("thể thao")) return "/featured/sports.svg";
  return "/featured/education.svg";
}

function ProgramContent({ listing }: { listing: PublicListing }) {
  const category = (listing.category_name ?? "").toLocaleLowerCase("vi");
  const subject = listing.specialization_name || listing.subcategory_name || listing.category_name || "nội dung dịch vụ";
  const modules = category.includes("fitness")
    ? [
        ["Đánh giá thể trạng và mục tiêu", `Trao đổi mục tiêu, làm quen với các bài tập nền tảng phù hợp với thể trạng và kinh nghiệm của bạn.`],
        ["Kỹ thuật tập luyện cơ bản", `Thực hành các động tác chính trong ${subject}, chú trọng tư thế và cách tập an toàn.`],
        ["Thực hành và tăng dần cường độ", "Điều chỉnh bài tập theo tiến độ, duy trì kỹ thuật đúng và xây dựng thói quen tập luyện."],
        ["Đánh giá tiến độ và định hướng", "Tổng kết kết quả, củng cố các động tác và thống nhất kế hoạch rèn luyện tiếp theo."],
      ]
    : category.includes("sport") || category.includes("thể thao")
      ? [
          ["Làm quen kỹ thuật nền tảng", `Tìm hiểu tư thế, quy tắc an toàn và các kỹ thuật cơ bản của ${subject}.`],
          ["Luyện kỹ thuật và di chuyển", "Thực hành từng kỹ thuật, phối hợp động tác và cải thiện khả năng di chuyển."],
          ["Thực hành tình huống", "Áp dụng kỹ thuật vào bài tập tình huống, nhận góp ý và điều chỉnh điểm cần cải thiện."],
          ["Tổng kết và định hướng rèn luyện", "Ôn lại nội dung chính, đánh giá tiến bộ và gợi ý cách tự luyện tập sau chương trình."],
        ]
      : [
          ["Củng cố nền tảng", `Ôn kiến thức cốt lõi của ${subject}, xác định mục tiêu và những phần cần ưu tiên.`],
          ["Phát triển kiến thức và kỹ năng", "Học các chủ điểm trọng tâm, luyện tập theo từng dạng và nhận phản hồi thường xuyên."],
          ["Luyện tập theo mục tiêu", "Kết hợp kiến thức, thực hành bài tập và củng cố những nội dung còn vướng."],
          ["Tổng kết và kế hoạch tiếp theo", "Ôn nội dung chính, nhìn lại tiến độ và thống nhất hướng học tiếp theo."],
        ];

  return <section className="listing-program-content" aria-labelledby="listing-program-title">
    <div className="listing-program-intro">
      <p className="listing-program-eyebrow">NỘI DUNG CHƯƠNG TRÌNH</p>
      <h2 id="listing-program-title">Lộ trình 8 tuần</h2>
      <p>Lộ trình tham khảo có thể thay đổi sau buổi đánh giá đầu vào để phù hợp với mục tiêu của bạn.</p>
      <div className="listing-program-stats"><span><strong>16</strong><small>Buổi học</small></span><span><strong>08</strong><small>Tuần đồng hành</small></span></div>
    </div>
    <div className="listing-program-modules">{modules.map(([title, description], index) => <details className="listing-program-module" key={title} open={index === 0}>
      <summary><span className="listing-program-number">{String(index + 1).padStart(2, "0")}</span><span className="listing-program-module-copy"><strong>{title}</strong><small>Tuần {index * 2 + 1}–{index * 2 + 2} · 4 buổi</small></span><span className="listing-program-toggle" aria-hidden="true" /></summary>
      <p>{description}</p>
    </details>)}</div>
  </section>;
}

function StudentFeedback({ review, tutorSlug, loading }: { review: TutorReviewCollection | null; tutorSlug: string; loading: boolean }) {
  return <section className="listing-student-feedback" aria-labelledby="listing-feedback-title">
    <div className="listing-feedback-heading"><div><p className="listing-program-eyebrow">PHẢN HỒI THỰC TẾ</p><h2 id="listing-feedback-title">Học viên nói gì về khóa học?</h2></div>
      {review?.aggregate.count ? <div className="listing-feedback-score"><strong>{review.aggregate.average?.toFixed(1)}</strong><span aria-label="5 sao">★★★★★</span><small>{review.aggregate.count} đánh giá đã xác minh</small></div> : null}
    </div>
    {loading ? <p className="listing-feedback-empty">Đang tải phản hồi của học viên…</p> : review?.data.length ? <div className="listing-feedback-grid">
      {review.data.slice(0, 3).map((item) => <article className="listing-feedback-card" key={item.id}>
        <span className="listing-feedback-stars" aria-label={`${item.rating} trên 5 sao`}>{"★".repeat(item.rating)}<span>{"★".repeat(5 - item.rating)}</span></span>
        <p>“{item.comment || "Học viên đã gửi đánh giá cho dịch vụ này."}”</p>
        <div className="listing-feedback-author"><span className="listing-feedback-avatar" aria-hidden="true">HV</span><span><strong>Học viên đã xác minh</strong><small>{reviewAge(item.created_at)}</small></span></div>
      </article>)}
    </div> : <p className="listing-feedback-empty">Chưa có phản hồi công khai cho dịch vụ này.</p>}
    {review?.aggregate.count ? <Link className="listing-feedback-all" href={`/tutors/${encodeURIComponent(tutorSlug)}/reviews`}>Xem tất cả <span aria-hidden="true">→</span></Link> : null}
  </section>;
}

function SimilarServices({ services, loading, error }: { services: RelatedListing[] | null; loading: boolean; error: boolean }) {
  return <section className="listing-similar-services" aria-labelledby="listing-similar-title">
    <div className="listing-similar-heading"><div><p className="listing-program-eyebrow">CÓ THỂ BẠN QUAN TÂM</p><h2 id="listing-similar-title">Dịch vụ tương tự</h2></div><Link href="/marketplace">Xem tất cả dịch vụ <span aria-hidden="true">→</span></Link></div>
    {loading ? <p className="listing-similar-state">Đang tìm dịch vụ phù hợp…</p> : error ? <p className="listing-similar-state">Chưa thể tải dịch vụ tương tự. Bạn có thể xem tất cả dịch vụ.</p> : services?.length ? <div className="listing-similar-grid">{services.map(({ listing, rating }) => <article className="listing-similar-card" key={listing.id}>
      <Link href={`/marketplace/${encodeURIComponent(listing.slug)}`} className="listing-similar-cover"><Image src={coverFor(listing)} alt={`Ảnh minh họa ${listing.category_name || "dịch vụ"}`} fill sizes="(max-width: 600px) 100vw, (max-width: 900px) 50vw, 33vw"/><span>{listing.category_name || "Dịch vụ"}</span></Link>
      <div className="listing-similar-body"><p className="listing-similar-category">{listing.category_name || "TutorLink"}</p><h3><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>{listing.title}</Link></h3><p className="listing-similar-tutor">{listing.tutor_name || "Gia sư TutorLink"}{rating !== null ? <> · {rating.toFixed(1)} <span aria-label="sao">★</span></> : null}</p><strong>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</strong></div>
    </article>)}</div> : <p className="listing-similar-state">Chưa có dịch vụ tương tự được công khai.</p>}
  </section>;
}

export function ListingDetail({ slug }: { slug: string }) {
  const load = useCallback(() => getPublicListing(slug), [slug]);
  const { data, error, loading, reload } = useAsyncData(load);
  const tutorLoad = useCallback(async (): Promise<DetailExtra | null> => {
    if (!data?.tutor_slug) return null;
    const [tutorResult, reviewResult] = await Promise.allSettled([
      getPublicTutor(data.tutor_slug),
      getTutorReviews(data.tutor_slug),
    ]);
    return {
      tutor: tutorResult.status === "fulfilled" ? tutorResult.value : null,
      reviews: reviewResult.status === "fulfilled" ? reviewResult.value : null,
    };
  }, [data?.tutor_slug]);
  const extra = useAsyncData(tutorLoad);
  const relatedLoad = useCallback(async (): Promise<RelatedListing[]> => {
    if (!data) return [];
    const response = await searchListings({ sort: "newest", per_page: 50 });
    const currentCategory = (data.category_name ?? "").toLocaleLowerCase("vi");
    const currentSubcategory = (data.subcategory_name ?? "").toLocaleLowerCase("vi");
    const currentSpecialization = (data.specialization_name ?? "").toLocaleLowerCase("vi");
    const candidates = response.data.filter((item) => item.slug !== data.slug);
    const score = (item: PublicListing) => {
      if (currentSpecialization && item.specialization_name?.toLocaleLowerCase("vi") === currentSpecialization) return 0;
      if (currentSubcategory && item.subcategory_name?.toLocaleLowerCase("vi") === currentSubcategory) return 1;
      if (currentCategory && item.category_name?.toLocaleLowerCase("vi") === currentCategory) return 2;
      return 3;
    };
    const picked = candidates.sort((a, b) => score(a) - score(b)).slice(0, 3);
    return Promise.all(picked.map(async (listing): Promise<RelatedListing> => {
      if (!listing.tutor_slug) return { listing, rating: null, reviewCount: 0 };
      try {
        const reviews = await getTutorReviews(listing.tutor_slug, 1);
        return { listing, rating: reviews.aggregate.average, reviewCount: reviews.aggregate.count };
      } catch {
        return { listing, rating: null, reviewCount: 0 };
      }
    }));
  }, [data?.slug, data?.category_name, data?.subcategory_name, data?.specialization_name]);
  const related = useAsyncData(relatedLoad);

  if (loading) return <ListingDetailLoading />;
  if (error) {
    const message = error.status === 401
      ? "Phiên đăng nhập đã hết hạn. Đăng nhập rồi thử lại."
      : error.status === 419
        ? "Phiên bảo mật đã hết hạn. Hãy thử tải lại."
        : error.status === 403 || error.status === 404
          ? "Dịch vụ này không tồn tại hoặc chưa được công khai."
          : error.status === 429
            ? "Bạn đang gửi yêu cầu quá nhanh. Hãy chờ một chút rồi thử lại."
            : error.status == null
              ? "Không thể kết nối tới máy chủ. Kiểm tra mạng rồi thử lại."
              : "Máy chủ đang gặp sự cố. Hãy thử lại sau.";
    return <section className="listing-detail-page"><Link href="/marketplace" className="text-button">← Quay lại Marketplace</Link><StatePanel kind="error" message={message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} /></section>;
  }
  if (!data) return <section className="listing-detail-page"><StatePanel kind="empty" message="Chưa có thông tin dịch vụ." /></section>;

  const tutor = extra.data?.tutor;
  const review = extra.data?.reviews;
  const tutorName = data.tutor_name?.trim() || tutor?.name?.trim() || "Gia sư TutorLink";
  const chatContact: ChatContactContext = {
    tutorName,
    listingTitle: data.title,
    category: data.specialization_name || data.category_name,
    delivery: data.delivery_mode === "online" ? "Học trực tuyến" : "Trực tiếp",
    price: formatListingPrice(data.price_from_minor, data.currency, data.price_unit),
    image: coverFor(data),
  };
  const taxonomy = [data.category_name, data.subcategory_name, data.specialization_name].filter(Boolean);
  const availability = tutor?.availability_rules ?? [];
  const firstReview = review?.data[0];

  return <section className="content-stack listing-detail-page">
    <nav className="public-breadcrumb listing-breadcrumb" aria-label="Đường dẫn">
      <Link href="/">Trang chủ</Link><span aria-hidden="true">/</span>
      <Link href="/marketplace">Gia sư</Link><span aria-hidden="true">/</span>
      <span aria-current="page">{data.title}</span>
    </nav>

    <div className="listing-detail-layout">
      <main className="listing-detail-main">
        <section className="listing-gallery" aria-label="Ảnh minh họa dịch vụ">
          <Image src={coverFor(data)} alt={`Ảnh minh họa cho ${data.title}`} fill priority sizes="(max-width: 900px) 100vw, 60vw" />
          <span className="listing-gallery-label">{data.category_name || "Dịch vụ TutorLink"}</span>
          <span className="listing-gallery-note">Ảnh minh họa</span>
        </section>

        <article className="listing-detail-copy">
          <div className="listing-detail-title-row"><h1>{data.title}</h1><span className="listing-title-spark" aria-hidden="true">✦</span></div>
          <Link className="listing-tutor-inline" href={data.tutor_slug ? `/tutors/${encodeURIComponent(data.tutor_slug)}` : "/marketplace"}>
            <span className="listing-tutor-avatar" aria-hidden="true">{tutorInitials(tutorName)}</span>
            <strong>{tutorName}<span className="listing-verified" aria-label="Hồ sơ công khai">✓</span></strong>
            <span className="listing-inline-divider" aria-hidden="true">·</span><span>{taxonomy.at(-1) || "Gia sư / Huấn luyện viên"}</span>
            {tutor ? <><span className="listing-inline-divider" aria-hidden="true">·</span><span>{tutor.experience_years} năm kinh nghiệm</span></> : null}
          </Link>
          <div className="listing-detail-tags">{taxonomy.map((tag, index) => <Badge key={`${tag}-${index}`} tone={index === 0 ? "brand" : "neutral"}>{tag}</Badge>)}<Badge tone="success">{deliveryLabels[data.delivery_mode]}</Badge></div>

          <section className="listing-description-block" aria-labelledby="listing-description-title">
            <h2 id="listing-description-title">Giới thiệu dịch vụ</h2>
            <p>{data.description}</p>
          </section>

          <section className="listing-highlights" aria-labelledby="listing-highlights-title">
            <h2 id="listing-highlights-title">Thông tin dịch vụ</h2>
            <ul>
              {data.specialization_name ? <li>{data.specialization_name}</li> : null}
              <li>{deliveryLabels[data.delivery_mode]}{data.delivery_mode === "offline" && data.city ? ` tại ${data.city}` : ""}</li>
              {data.delivery_mode !== "offline" && data.city ? <li>Khu vực: {data.city}</li> : null}
            </ul>
          </section>

          <section className="listing-schedule" aria-labelledby="listing-schedule-title">
            <div className="listing-schedule-heading"><span aria-hidden="true">▦</span><div><h2 id="listing-schedule-title">Lịch học</h2><small>Chỉ để tham khảo</small></div></div>
            {tutor && !extra.loading ? <div className="listing-week-grid">{weekdays.map((day, index) => {
              const slots = availability.filter((rule) => rule.weekday === index);
              return <div className={`listing-week-day${slots.length ? " has-slots" : ""}`} key={day}><span>{day}</span>{slots.length ? slots.map((slot) => <strong key={`${slot.start_time}-${slot.end_time}`}>{slot.start_time}–{slot.end_time}</strong>) : <i aria-label="Chưa có lịch" />}</div>;
            })}</div> : <p className="listing-schedule-empty">{extra.loading ? "Đang tải lịch khả dụng…" : "Gia sư chưa công khai lịch khả dụng."}</p>}
            <p className="listing-schedule-note">Vui lòng nhắn tin để trao đổi lịch học phù hợp.</p>
          </section>
        </article>
      </main>

      <aside className="listing-detail-sidebar" aria-label="Thông tin đặt dịch vụ">
        <section className="listing-price-card card">
          <p className="listing-price"><strong>{formatListingPrice(data.price_from_minor, data.currency, data.price_unit).split(" /")[0]}</strong><span>/ {formatListingPrice(data.price_from_minor, data.currency, data.price_unit).split(" / ").at(-1)}</span></p>
          <small className="listing-price-note">Giá tham khảo</small>
          {tutor ? <OpenChatButton peerId={tutor.messaging_user_id} listingId={data.id} contact={chatContact} /> : data.tutor_slug && extra.loading ? <button className="button button-primary" type="button" disabled>Đang tải thông tin gia sư…</button> : data.tutor_slug ? <Link className="button button-secondary" href={`/tutors/${encodeURIComponent(data.tutor_slug)}`}>Xem hồ sơ gia sư</Link> : <p className="listing-no-contact">Thông tin liên hệ chưa khả dụng.</p>}
          {data.tutor_slug ? <FavoriteTutorButton slug={data.tutor_slug} /> : null}
          <dl className="listing-facts">
            <div><dt>Danh mục</dt><dd>{data.category_name || "—"}</dd></div>
            <div><dt>Chuyên môn</dt><dd>{data.specialization_name || data.subcategory_name || "—"}</dd></div>
            <div><dt>Hình thức học</dt><dd>{deliveryLabels[data.delivery_mode]}</dd></div>
            <div><dt>Địa điểm</dt><dd>{[data.district, data.city].filter(Boolean).join(", ") || (data.delivery_mode === "online" ? "Online (không giới hạn)" : "Linh hoạt")}</dd></div>
          </dl>
        </section>

        {data.tutor_slug ? <section className="listing-tutor-card card">
          <Link href={`/tutors/${encodeURIComponent(data.tutor_slug)}`} className="listing-tutor-card-heading"><span className="listing-tutor-avatar">{tutorInitials(tutorName)}</span><span><strong>{tutorName}<i aria-label="Hồ sơ công khai">✓</i></strong><small>{tutor?.headline || taxonomy.at(-1) || "Gia sư TutorLink"}{tutor ? ` · ${tutor.experience_years} năm kinh nghiệm` : ""}</small></span></Link>
          <p>{tutor?.bio || "Xem hồ sơ để tìm hiểu thêm về kinh nghiệm và chuyên môn của gia sư."}</p>
          <Link href={`/tutors/${encodeURIComponent(data.tutor_slug)}`} className="listing-profile-link">Xem hồ sơ Tutor</Link>
        </section> : null}

        <section className="listing-reviews-card card" aria-labelledby="listing-reviews-title">
          <div className="listing-reviews-heading"><h2 id="listing-reviews-title">Đánh giá từ học viên</h2>{data.tutor_slug ? <Link href={`/tutors/${encodeURIComponent(data.tutor_slug)}/reviews`}>Xem tất cả</Link> : null}</div>
          {review?.aggregate.count ? <>
            <div className="listing-rating-summary"><strong>{review.aggregate.average?.toFixed(1)}</strong><span aria-label="5 sao">★★★★★</span><small>{review.aggregate.count} đánh giá</small></div>
            {firstReview ? <article className="listing-review-snippet"><div><span className="listing-review-avatar">{tutorInitials(firstReview.author_name)}</span><span><strong>{firstReview.author_name}</strong><small>{firstReview.created_at ? new Date(firstReview.created_at).toLocaleDateString("vi-VN") : "Học viên"}</small></span></div><span className="listing-review-stars" aria-label={`${firstReview.rating} trên 5 sao`}>{"★".repeat(firstReview.rating)}</span><p>{firstReview.comment || "Học viên hài lòng với dịch vụ."}</p></article> : null}
          </> : <p className="listing-review-empty">{extra.loading ? "Đang tải đánh giá…" : "Chưa có đánh giá công khai."}</p>}
        </section>
      </aside>
    </div>
    <ProgramContent listing={data} />
    {data.tutor_slug ? <StudentFeedback review={review ?? null} tutorSlug={data.tutor_slug} loading={extra.loading} /> : null}
    <SimilarServices services={related.data} loading={related.loading} error={Boolean(related.error)} />
  </section>;
}
