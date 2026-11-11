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

  return (
    <section className="card public-availability" aria-labelledby="availability-title">
      <div className="public-section-heading">
        <h2 id="availability-title">Lịch trống <small>(tham khảo)</small></h2>
        {availability?.timezone ? <span className="public-timezone">Múi giờ: {availability.timezone}</span> : null}
      </div>
      <ul className="availability-list">
        {Object.entries(weekdays).map(([weekday, label]) => {
          const slots = rulesByWeekday.get(Number(weekday)) ?? [];
          return <li key={weekday}><strong>{label}</strong><span>{slots.length ? slots.join(", ") : "Chưa cập nhật"}</span></li>;
        })}
      </ul>
      <p className="public-reference-note">Lịch tham khảo theo tuần, không giữ chỗ. Bạn và người dạy cần thống nhất lịch học.</p>
    </section>
  );
}
