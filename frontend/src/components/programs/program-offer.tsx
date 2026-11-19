"use client";

import {
  type Program
} from "@/lib/data/collaboration";
import { deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import "@/styles/listings.css";
import "@/styles/programs.css";

export function Offer({
  program: p,
}: {
  program: Pick<
    Program,
    | "title"
    | "goal"
    | "level"
    | "delivery_mode"
    | "agreed_fee_minor"
    | "currency"
    | "fee_unit"
    | "schedule_note"
    | "timezone"
    | "planned_session_count"
    | "location_text"
    | "meeting_url"
  >;
}) {
  return (
    <dl className="offer-facts">
      <div>
        <dt>Mục tiêu</dt>
        <dd>{p.goal || "Chưa ghi nhận"}</dd>
      </div>
      <div>
        <dt>Trình độ</dt>
        <dd>{p.level || "Chưa ghi nhận"}</dd>
      </div>
      <div>
        <dt>Hình thức</dt>
        <dd>{deliveryLabels[p.delivery_mode]}</dd>
      </div>
      <div>
        <dt>Học phí thỏa thuận</dt>
        <dd>
          {formatListingPrice(p.agreed_fee_minor, p.currency, p.fee_unit)}
        </dd>
      </div>
      <div>
        <dt>Lịch học</dt>
        <dd>
          {p.schedule_note || "Chưa thống nhất"} · {p.timezone}
        </dd>
      </div>
      <div>
        <dt>Số buổi dự kiến</dt>
        <dd>{p.planned_session_count ?? "Chưa thống nhất"}</dd>
      </div>
      <div>
        <dt>Địa điểm / Đường dẫn tham gia</dt>
        <dd>{p.location_text || p.meeting_url || "Chưa thống nhất"}</dd>
      </div>
    </dl>
  );
}
