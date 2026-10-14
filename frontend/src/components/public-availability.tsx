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
  const rules = availability?.rules.slice().sort((a, b) => a.weekday - b.weekday || a.start_time.localeCompare(b.start_time)) ?? [];

  return (
    <section className="card public-availability" aria-labelledby="availability-title">
      <div className="public-section-heading">
        <h2 id="availability-title">Khung giờ thường dạy</h2>
        {availability?.timezone ? <span className="public-timezone">Múi giờ: {availability.timezone}</span> : null}
      </div>
      {rules.length ? (
        <ul className="availability-list">
          {rules.map((rule, index) => (
            <li key={`${rule.weekday}-${rule.start_time}-${index}`}>
              <strong>{weekdays[rule.weekday] ?? `Thứ ${rule.weekday}`}</strong>
              <span>{rule.start_time}–{rule.end_time}</span>
            </li>
          ))}
        </ul>
      ) : <p className="muted public-no-availability">Chưa có khung giờ công khai.</p>}
      <p className="public-reference-note">Lịch tham khảo theo tuần, không giữ chỗ. Bạn và người dạy cần thống nhất lịch học.</p>
    </section>
  );
}
