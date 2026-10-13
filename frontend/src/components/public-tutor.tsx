"use client";

import Image from "next/image";
import Link from "next/link";
import { useCallback } from "react";
import { getPublicTutor, deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { PublicAvailability } from "./public-availability";
import { Badge, ErrorActions, StatePanel } from "./ui";

export function PublicTutor({ slug }: { slug: string }) {
  const load = useCallback(() => getPublicTutor(slug), [slug]);
  const { data, error, loading, reload } = useAsyncData(load);

  if (loading) return <StatePanel kind="loading" message="Đang tải hồ sơ gia sư…" />;
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

  const rating = data.rating;
  const initial = data.full_name.trim().split(/\s+/).slice(-1)[0]?.[0]?.toLocaleUpperCase() ?? "G";

  return (
    <section className="content-stack public-tutor-page">
      <nav className="public-breadcrumb" aria-label="Đường dẫn">
        <Link href="/marketplace">Marketplace</Link>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Hồ sơ gia sư</span>
      </nav>
      <div className="public-tutor-layout">
        <div className="public-tutor-main">
          <section className="card public-profile-hero">
            <span className="public-avatar public-avatar-large" aria-hidden="true">
              {data.avatar_url ? <Image src={data.avatar_url} alt="" fill unoptimized sizes="96px" /> : initial}
            </span>
            <div className="public-profile-intro">
              <p className="eyebrow">Gia sư</p>
              <h1>{data.full_name}</h1>
              <p className="public-headline">{data.headline}</p>
              <div className="public-profile-stats">
                <span>{data.experience_years} năm kinh nghiệm</span>
                {rating ? <span>{rating.average.toFixed(1)} ★ · {rating.count} đánh giá</span> : null}
                <Link href={`/tutors/${encodeURIComponent(data.slug)}/reviews`}>Xem nhận xét</Link>
              </div>
            </div>
          </section>

          {data.bio ? (
            <section className="card public-bio" aria-labelledby="tutor-bio-title">
              <h2 id="tutor-bio-title">Giới thiệu</h2>
              <p>{data.bio}</p>
            </section>
          ) : null}

          <section className="public-listings-section" aria-labelledby="tutor-listings-title">
            <div className="public-section-heading">
              <h2 id="tutor-listings-title">Dịch vụ đang cung cấp</h2>
              <span className="muted">{data.listings.length} dịch vụ</span>
            </div>
            {data.listings.length ? (
              <div className="public-listings-grid">
                {data.listings.map((listing) => (
                  <Link className="card public-listing-card" href={`/marketplace/${encodeURIComponent(listing.slug)}`} key={listing.id}>
                    <div className="public-detail-tags">
                      <Badge tone="brand">{deliveryLabels[listing.delivery_mode]}</Badge>
                      {listing.city ? <Badge>{listing.city}</Badge> : null}
                    </div>
                    <h3>{listing.title}</h3>
                    <p>{[listing.category_name, listing.subcategory_name, listing.specialization_name].filter(Boolean).join(" · ")}</p>
                    <strong>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)}</strong>
                  </Link>
                ))}
              </div>
            ) : (
              <StatePanel kind="empty" message="Gia sư chưa có dịch vụ đang công khai." />
            )}
          </section>
        </div>

        <aside className="public-tutor-sidebar" aria-label="Thông tin gia sư">
          <PublicAvailability availability={data.availability} />
          {rating ? <section className="card public-review-summary" aria-labelledby="tutor-rating-title">
            <h2 id="tutor-rating-title">Đánh giá</h2>
            {rating ? (
              <>
                <p className="public-rating-value"><strong>{rating.average.toFixed(1)}</strong><span aria-hidden="true">★</span></p>
                <p className="muted">Từ {rating.count} đánh giá hiển thị</p>
              </>
            ) : <p className="muted">Chưa có tổng hợp đánh giá.</p>}
            <Link className="button button-secondary public-profile-button" href={`/tutors/${encodeURIComponent(data.slug)}/reviews`}>
              Đọc nhận xét
            </Link>
          </section> : null}
        </aside>
      </div>
    </section>
  );
}
