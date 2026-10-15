"use client";

import Link from "next/link";
import { useCallback } from "react";
import { useRouter } from "next/navigation";
import { describeApiError } from "@/lib/api/client";
import { getTutorReviews } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Button, StatePanel } from "./ui";

const ratingLabels = ["5 sao", "4 sao", "3 sao", "2 sao", "1 sao"] as const;
const ratingValues = [5, 4, 3, 2, 1] as const;

function displayDate(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat("vi-VN", { day: "numeric", month: "long", year: "numeric" }).format(date);
}

export function VisibleReviews({ slug, page }: { slug: string; page: number }) {
  const router = useRouter();
  const load = useCallback(() => getTutorReviews(slug, page), [slug, page]);
  const { data, error, loading, reload } = useAsyncData(load);
  const route = `/tutors/${encodeURIComponent(slug)}/reviews`;

  const breadcrumb = (
    <nav className="public-breadcrumb" aria-label="Đường dẫn">
      <Link href="/marketplace">Marketplace</Link>
      <span aria-hidden="true">/</span>
      <Link href={`/tutors/${encodeURIComponent(slug)}`}>Hồ sơ gia sư</Link>
      <span aria-hidden="true">/</span>
      <span aria-current="page">Nhận xét</span>
    </nav>
  );

  if (loading) {
    return <section className="content-stack public-reviews-page">{breadcrumb}<StatePanel kind="loading" message="Đang tải nhận xét công khai…" /></section>;
  }
  if (error) {
    const message = error.status === 404
      ? "Không tìm thấy hồ sơ gia sư công khai."
      : error.status === 422
        ? "Không tải được trang nhận xét này. Hãy quay lại trang đầu."
        : describeApiError(error, "Không thể tải nhận xét công khai. Hãy thử lại sau.");
    return (
      <section className="content-stack public-reviews-page">
        {breadcrumb}
        <StatePanel
          kind="error"
          message={message}
          action={
            <div className="button-row">
              {error.status === 401 ? <Link className="button button-secondary" href="/auth/login">Đăng nhập</Link> : null}
              {error.status === 422 && page !== 1 ? <Link className="button button-secondary" href={route}>Trang đầu</Link> : null}
              <Button variant="secondary" onClick={reload}>Thử lại</Button>
            </div>
          }
        />
      </section>
    );
  }
  if (!data) {
    return <section className="content-stack public-reviews-page">{breadcrumb}<StatePanel kind="empty" message="Chưa có nhận xét công khai." /></section>;
  }

  const totalPages = Math.max(1, Math.ceil(data.meta.total / data.meta.per_page));
  const histogramMaximum = Math.max(0, ...ratingValues.map((rating) => data.aggregate.histogram[String(rating) as keyof typeof data.aggregate.histogram]));

  return (
    <section className="content-stack public-reviews-page">
      {breadcrumb}
      <div className="public-reviews-layout">
        <section className="card public-review-list" aria-labelledby="public-reviews-title">
          <div className="public-section-heading">
            <div>
              <p className="eyebrow">Gia sư</p>
              <h1 id="public-reviews-title">Nhận xét công khai</h1>
            </div>
            <span className="muted">{data.meta.total} nhận xét</span>
          </div>
          {!data.data.length ? (
            <StatePanel kind="empty" message="Chưa có nhận xét công khai." />
          ) : (
            <ol className="review-list">
              {data.data.map((review) => (
                <li className="public-review" key={review.id}>
                  <div className="review-heading">
                    <div>
                      <h2>{review.author_name}</h2>
                      {review.created_at ? <time dateTime={review.created_at}>{displayDate(review.created_at)}</time> : null}
                    </div>
                    <span className="review-stars" role="img" aria-label={`${review.rating} trên 5 sao`}>
                      {"★".repeat(review.rating)}{"☆".repeat(5 - review.rating)}
                    </span>
                  </div>
                  {review.comment ? <p>{review.comment}</p> : null}
                </li>
              ))}
            </ol>
          )}
          {totalPages > 1 ? (
            <nav className="review-pagination" aria-label="Phân trang nhận xét">
              <Button
                variant="outline"
                disabled={loading || data.meta.current_page <= 1}
                onClick={() => router.push(`${route}?page=${data.meta.current_page - 1}`, { scroll: false })}
              >
                ← Trước
              </Button>
              <span aria-live="polite">Trang {data.meta.current_page} / {totalPages}</span>
              <Button
                variant="outline"
                disabled={loading || data.meta.current_page >= totalPages}
                onClick={() => router.push(`${route}?page=${data.meta.current_page + 1}`, { scroll: false })}
              >
                Sau →
              </Button>
            </nav>
          ) : null}
        </section>

        <aside className="card public-rating-summary" aria-labelledby="public-rating-title">
          <h2 id="public-rating-title">Đánh giá tổng hợp</h2>
          <div className="public-rating-value">
            <strong>{data.aggregate.average === null ? "—" : data.aggregate.average.toFixed(2)}</strong>
            <span>/ 5</span>
          </div>
          <p className="muted">{data.aggregate.count} nhận xét công khai</p>
          <ul className="rating-histogram">
            {ratingValues.map((rating, index) => {
              const count = data.aggregate.histogram[String(rating) as keyof typeof data.aggregate.histogram];
              const width = histogramMaximum ? (count / histogramMaximum) * 100 : 0;
              return (
                <li key={rating}>
                  <span>{ratingLabels[index]}</span>
                  <span className="rating-bar" aria-hidden="true"><span style={{ width: `${width}%` }} /></span>
                  <span>{count}</span>
                </li>
              );
            })}
          </ul>
        </aside>
      </div>
    </section>
  );
}
