"use client";

import Link from "next/link";
import { useCallback } from "react";
import { getListing } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Badge, ErrorActions, StatePanel } from "./ui";

export function ListingDetail({ slug }: { slug: string }) {
  const load = useCallback(() => getListing(slug), [slug]);
  const { data, error, loading, reload } = useAsyncData(load);
  return <section className="content-stack"><Link href="/marketplace" className="text-button">← Quay lại Marketplace</Link>{loading ? <StatePanel kind="loading" message="Đang tải chi tiết Listing…" /> : error ? <StatePanel kind="error" message={error.status === 404 ? "Listing không tồn tại hoặc không còn công khai." : error.message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} /> : data ? <article className="card detail-card"><Badge tone="success">{data.status === "active" ? "Đang hoạt động" : data.status}</Badge><h1>{data.title}</h1><p className="lead">{data.description}</p><dl className="detail-list"><div><dt>Tutor</dt><dd>{data.tutor_name}</dd></div><div><dt>Chuyên môn</dt><dd>{data.specialization_name}</dd></div><div><dt>Hình thức</dt><dd>{data.delivery_mode}</dd></div><div><dt>Khu vực</dt><dd>{data.city ?? "Toàn quốc"}</dd></div><div><dt>Mức phí tham khảo</dt><dd>{new Intl.NumberFormat("vi-VN").format(data.price_from_minor)} {data.currency} / {data.price_unit === "hour" ? "giờ" : data.price_unit}</dd></div></dl></article> : <StatePanel kind="empty" message="Chưa có dữ liệu Listing." />}</section>;
}
