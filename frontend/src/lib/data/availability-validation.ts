import type { Availability } from "./marketplace-types";

export const weekdayLabels = ["Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy", "Chủ Nhật"];
export function validateAvailability(value: Availability): Record<string, string[]> {
  const errors: Record<string, string[]> = {};
  try { new Intl.DateTimeFormat("vi-VN", { timeZone: value.timezone }); }
  catch { errors.timezone = ["Múi giờ không hợp lệ. Dùng tên như Asia/Ho_Chi_Minh."]; }
  if (!value.timezone.trim()) errors.timezone = ["Vui lòng nhập múi giờ."];
  if (/^[+-]/.test(value.timezone)) errors.timezone = ["Dùng tên múi giờ IANA, không dùng độ lệch UTC."];
  const clock = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
  value.rules.forEach((rule, index) => {
    if (!Number.isInteger(rule.weekday) || rule.weekday < 1 || rule.weekday > 7) errors[`rules.${index}.weekday`] = ["Ngày trong tuần không hợp lệ."];
    if (!clock.test(rule.start_time)) errors[`rules.${index}.start_time`] = ["Nhập giờ bắt đầu theo HH:mm."];
    if (!clock.test(rule.end_time) || rule.end_time <= rule.start_time) errors[`rules.${index}.end_time`] = ["Giờ kết thúc phải sau giờ bắt đầu trong cùng ngày."];
    value.rules.slice(0, index).forEach((previous, other) => {
      if (previous.weekday === rule.weekday && rule.start_time < previous.end_time && previous.start_time < rule.end_time) {
        errors[`rules.${index}.start_time`] = ["Khung giờ trùng với một khung giờ khác trong ngày."];
        errors[`rules.${other}.start_time`] = ["Khung giờ trùng với một khung giờ khác trong ngày."];
      }
    });
  });
  return errors;
}
