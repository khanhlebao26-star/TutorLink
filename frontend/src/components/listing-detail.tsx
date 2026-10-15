"use client";

import Link from "next/link";
import { useCallback } from "react";
import { getPublicListing, deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Badge, ErrorActions, StatePanel } from "./ui";

export function ListingDetail({ slug }: { slug: string }) {
  const load = useCallback(() => getPublicListing(slug), [slug]);
  const { data, error, loading, reload } = useAsyncData(load);

  if (loading) return <StatePanel kind="loading" message="Đang tải dịch vụ…" />;
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
    return (
      <section className="content-stack">
        <Link href="/marketplace" className="text-button">← Quay lại Marketplace</Link>
        <StatePanel kind="error" message={message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} />
      </section>
    );
  }
  if (!data) return <StatePanel kind="empty" message="Chưa có thông tin dịch vụ." />;

  const taxonomy = [data.category_name, data.subcategory_name, data.specialization_name].filter(Boolean);
  return (
    <section className="content-stack public-detail-page">
      <nav className="public-breadcrumb" aria-label="Đường dẫn">
        <Link href="/marketplace">Marketplace</Link>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Chi tiết dịch vụ</span>
      </nav>
      <div className="public-detail-grid">
        <article className="card public-detail-main">
          <div className="public-detail-tags">
            <Badge tone="brand">{deliveryLabels[data.delivery_mode]}</Badge>
            {data.city ? <Badge>{data.city}</Badge> : null}
          </div>
          <h1>{data.title}</h1>
          {taxonomy.length ? <p className="public-taxonomy">{taxonomy.join(" · ")}</p> : null}
          <section className="public-description" aria-labelledby="listing-description-title">
            <h2 id="listing-description-title">Giới thiệu dịch vụ</h2>
            <p>{data.description}</p>
          </section>
        </article>

        <aside className="public-detail-sidebar" aria-label="Thông tin dịch vụ">
          <section className="card public-price-card">
            <span className="public-card-label">Mức phí tham khảo</span>
            <strong className="public-price">{formatListingPrice(data.price_from_minor, data.currency, data.price_unit)}</strong>
            <dl className="public-facts">
              <div><dt>Hình thức</dt><dd>{deliveryLabels[data.delivery_mode]}</dd></div>
              <div><dt>Khu vực</dt><dd>{[data.district, data.city].filter(Boolean).join(", ") || "Toàn quốc"}</dd></div>
            </dl>
          </section>

          {data.tutor_name || data.tutor_slug ? <section className="card public-tutor-card" aria-labelledby="listing-tutor-title">
            <h2 id="listing-tutor-title">Gia sư</h2>
            {data.tutor_slug ? (
              <Link className="public-tutor-link" href={`/tutors/${encodeURIComponent(data.tutor_slug)}`}>
                <span className="public-avatar" aria-hidden="true">{(data.tutor_name?.trim().split(/\s+/).slice(-1)[0]?.[0] ?? "G").toLocaleUpperCase()}</span>
                <span className="public-tutor-copy">
                  <strong>{data.tutor_name?.trim() || "Gia sư"}</strong>
                  <span>Xem hồ sơ gia sư</span>
                </span>
              </Link>
            ) : <p>{data.tutor_name}</p>}
          </section> : null}
        </aside>
      </div>
    </section>
  );
}
