"use client";

import Link from "next/link";
import { useCallback, useState } from "react";
import { AppShell } from "@/components/app-shell";
import { getListings } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { Badge, ErrorActions, Field, StatePanel } from "@/components/ui";
import type { DeliveryMode } from "@/lib/data/types";

const deliveryLabels: Record<DeliveryMode, string> = { online: "Online", offline: "Trực tiếp", hybrid: "Kết hợp" };

export default function MarketplacePage() {
  const load = useCallback(() => getListings(), []);
  const { data, error, loading, reload } = useAsyncData(load);
  const [query, setQuery] = useState("");
  const visible = data?.filter((listing) => `${listing.title} ${listing.tutor_name} ${listing.specialization_name}`.toLowerCase().includes(query.toLowerCase())) ?? [];
  return <AppShell title="Marketplace"><section className="content-stack"><div><p className="kicker">Service Listing</p><h1>Tìm dịch vụ phù hợp</h1><p className="lead">Xem các dịch vụ đang được công khai theo hình thức và khu vực.</p></div><Field label="Tìm kiếm" htmlFor="listing-search"><input id="listing-search" placeholder="Ví dụ: tiếng Anh, Toán…" value={query} onChange={(event) => setQuery(event.target.value)} /></Field>{loading ? <StatePanel kind="loading" message="Đang tải Listing…" /> : error ? <StatePanel kind="error" message={error.status === 401 ? "Phiên đăng nhập không còn hiệu lực." : error.message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} /> : !visible.length ? <StatePanel kind="empty" message={query ? "Không tìm thấy Listing phù hợp." : "Chưa có Listing đang hoạt động."} /> : <div className="listing-grid">{visible.map((listing) => <Link className="card listing-card" href={`/marketplace/${listing.slug}`} key={listing.id}><div className="listing-card-top"><Badge tone="success">{deliveryLabels[listing.delivery_mode]}</Badge><span className="muted">{listing.city ?? "Toàn quốc"}</span></div><h2>{listing.title}</h2><p>{listing.description}</p><p className="muted">{listing.tutor_name} · {listing.specialization_name}</p><strong>{new Intl.NumberFormat("vi-VN").format(listing.price_from_minor)} {listing.currency} / {listing.price_unit === "hour" ? "giờ" : listing.price_unit}</strong></Link>)}</div>}</section></AppShell>;
}
