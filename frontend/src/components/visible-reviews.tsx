"use client";

import Link from "next/link";
import { useCallback, useState } from "react";
import { getVisibleReviews } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Button, ErrorActions, StatePanel } from "./ui";

function dateLabel(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium" }).format(date);
}

function RatingStars({ rating }: { rating: number }) {
  const stars = Math.max(0, Math.min(5, Math.round(rating)));
  return <span className="review-stars" role="img" aria-label={`${rating} trên 5 sao`}>{"★".repeat(stars)}{"☆".repeat(5 - stars)}</span>;
}

export function VisibleReviews({ slug }: { slug: string }) {
  const [page, setPage] = useState(1);
  const load = useCallback(() => getVisibleReviews(slug, page), [slug, page]);
  const { data, error, loading, reload } = useAsyncData(load);

  if (loading) return <StatePanel kind="loading" message="Đang tải nhận xét…" />;
  if (error) {
    const message = error.status === 401
      ? "Phiên đăng nhập đã hết hạn. Đăng nhập rồi thử lại."
      : error.status === 419
        ? "Phiên bảo mật đã hết hạn. Hãy thử tải lại."
        : error.status === 403 || error.status === 404
          ? "Không tìm thấy nhận xét công khai cho gia sư này."
          : error.status === 429
            ? "Bạn đang gửi yêu cầu quá nhanh. Hãy chờ một chút rồi thử lại."
            : error.status == null
              ? "Không thể kết nối tới máy chủ. Kiểm tra mạng rồi thử lại."
              : "Máy chủ đang gặp sự cố. Hãy thử lại sau.";
    return (
      <section className="content-stack">
        <Link href={`/tutors/${encodeURIComponent(slug)}`} className="text-button">← Quay lại hồ sơ gia sư</Link>
        <StatePanel kind="error" message={message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} />
      </section>
    );
  }
  if (!data) return <StatePanel kind="empty" message="Chưa có nhận xét." />;

  const aggregate = data.aggregate;
  const histogram = aggregate?.histogram;
  const pageCount = Math.max(1, Math.ceil(data.meta.total / data.meta.per_page));

  return (
    <section className="content-stack public-reviews-page">
      <nav className="public-breadcrumb" aria-label="Đường dẫn">
        <Link href="/marketplace">Marketplace</Link>
        <span aria-hidden="true">/</span>
        <Link href={`/tutors/${encodeURIComponent(slug)}`}>Hồ sơ gia sư</Link>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Nhận xét</span>
      </nav>
      <div className="public-reviews-layout">
        <section className="card public-review-list" aria-labelledby="reviews-title">
          <div className="public-section-heading">
            <div>
              <p className="eyebrow">Nhận xét học viên</p>
              <h1 id="reviews-title">Đánh giá và nhận xét</h1>
            </div>
            <span className="muted">{data.meta.total} nhận xét</span>
          </div>
          {data.data.length ? (
            <ul className="review-list">
              {data.data.map((review) => (
                <li className="public-review" key={review.id}>
                  <div className="review-heading">
                    <div>
                      <h2>{review.author_name}</h2>
                      <time dateTime={review.created_at}>{dateLabel(review.created_at)}</time>
                    </div>
                    <RatingStars rating={review.rating} />
                  </div>
                  <p>{review.comment}</p>
                </li>
              ))}
            </ul>
          ) : (
            <StatePanel kind="empty" message="Chưa có nhận xét công khai." />
          )}
          {pageCount > 1 ? (
            <nav className="review-pagination" aria-label="Phân trang nhận xét">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Trước</Button>
              <span aria-live="polite">Trang {data.meta.current_page} / {pageCount}</span>
              <Button variant="secondary" disabled={page >= pageCount} onClick={() => setPage((value) => Math.min(pageCount, value + 1))}>Sau</Button>
            </nav>
          ) : null}
        </section>

        {aggregate ? <aside className="card public-rating-summary" aria-labelledby="rating-summary-title">
          <h2 id="rating-summary-title">Tổng quan đánh giá</h2>
          {aggregate ? (
            <>
              <p className="public-rating-value"><strong>{aggregate.average.toFixed(1)}</strong><span aria-hidden="true">★</span></p>
              <p className="muted">Từ {aggregate.count} đánh giá hiển thị</p>
            </>
          ) : <p className="muted">Chưa có tổng hợp đánh giá.</p>}
          {histogram ? (
            <ol className="rating-histogram" aria-label="Số lượng đánh giá theo số sao">
              {[5, 4, 3, 2, 1].map((star) => {
                const count = histogram[star as 1 | 2 | 3 | 4 | 5] ?? 0;
                const width = aggregate.count > 0 ? `${Math.min(100, count / aggregate.count * 100)}%` : "0%";
                return (
                  <li key={star}>
                    <span>{star} ★</span>
                    <span className="rating-bar" role="img" aria-label={`${star} sao: ${count} đánh giá`}><span style={{ width }} /></span>
                    <span>{count}</span>
                  </li>
                );
              })}
            </ol>
          ) : null}
        </aside> : null}
      </div>
    </section>
  );
}
