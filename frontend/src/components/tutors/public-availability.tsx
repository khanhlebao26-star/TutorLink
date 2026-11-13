import type { Availability } from "@/lib/data/marketplace-types";

const weekdays: Record<number, string> = {
  0: "Thứ hai",
  1: "Thứ ba",
  2: "Thứ tư",
  3: "Thứ năm",
  4: "Thứ sáu",
  5: "Thứ bảy",
  6: "Chủ nhật",
};

export function PublicAvailability({ availability }: { availability?: Availability | null }) {
  if (!availability) return null;
  const rulesByWeekday = new Map<number, string[]>();
  availability.rules
    .slice()
    .sort((a, b) => a.weekday - b.weekday || a.start_time.localeCompare(b.start_time))
    .forEach((rule) => {
      const slots = rulesByWeekday.get(rule.weekday) ?? [];
      slots.push(`${rule.start_time}–${rule.end_time}`);
      rulesByWeekday.set(rule.weekday, slots);
    });
  const daysWithAvailability = rulesByWeekday.size;

  return (
    <section className="card public-availability tutor-schedule-card" aria-labelledby="availability-title">
      <div className="tutor-schedule-card-heading">
        <div>
          <span className="tutor-schedule-card-eyebrow">KHUNG GIỜ HÀNG TUẦN</span>
          <h2 id="availability-title">Lịch trống <small>(tham khảo)</small></h2>
          {availability.timezone ? <span className="public-timezone">Múi giờ: {availability.timezone}</span> : null}
        </div>
        <div className="tutor-schedule-availability-count"><strong>{daysWithAvailability}</strong><span>/ 7 ngày có giờ</span></div>
      </div>
      <div className="tutor-schedule-legend" aria-label="Chú giải lịch">
        <span><i className="is-available" /> Có khung giờ tham khảo</span>
        <span><i className="is-unavailable" /> Chưa cập nhật</span>
      </div>
      <ul className="availability-list">
        {Object.entries(weekdays).map(([weekday, label]) => {
          const slots = rulesByWeekday.get(Number(weekday)) ?? [];
          return <li className={slots.length ? "has-availability" : "no-availability"} key={weekday}>
            <div className="tutor-schedule-day-heading"><strong>{label}</strong><span>{slots.length ? "Có thể trao đổi" : "Chưa cập nhật"}</span></div>
            <div className="tutor-schedule-slots">{slots.length ? slots.map((slot) => <span key={slot}>{slot}</span>) : <span className="tutor-schedule-empty-slot">Chưa có khung giờ</span>}</div>
          </li>;
        })}
      </ul>
      <p className="tutor-schedule-reference-note"><span aria-hidden="true">ⓘ</span>Lịch này mang tính tham khảo, chưa giữ chỗ. Hãy nhắn tin với gia sư để thống nhất ngày và giờ học.</p>
    </section>
  );
}
