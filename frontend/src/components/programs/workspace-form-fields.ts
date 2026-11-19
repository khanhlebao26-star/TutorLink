import type { FormField } from "@/components/programs/workspace-form";

export const title: FormField = {
  name: "title",
  label: "Tiêu đề",
  required: true,
  maxLength: 180,
};
export const body: FormField = {
  name: "body",
  label: "Nội dung",
  type: "textarea",
  required: true,
};
export const reason: FormField = {
  name: "reason",
  label: "Lý do",
  required: true,
  maxLength: 2000,
};
export const sessionFields: FormField[] = [
  title,
  {
    name: "planned_starts_at",
    label: "Bắt đầu dự kiến (giờ máy của bạn)",
    type: "datetime-local",
    required: true,
  },
  {
    name: "planned_ends_at",
    label: "Kết thúc dự kiến (giờ máy của bạn)",
    type: "datetime-local",
    required: true,
  },
  {
    name: "delivery_mode",
    label: "Hình thức",
    required: true,
    options: { online: "Trực tuyến", offline: "Trực tiếp", hybrid: "Kết hợp" },
  },
  { name: "location_text", label: "Địa điểm" },
  { name: "meeting_url", label: "Đường dẫn tham gia buổi học", hint: "Dán đường dẫn từ Google Meet, Zoom hoặc ứng dụng bạn dùng. Có thể để trống nếu chưa có." },
];
export const taskFields: FormField[] = [
  { ...title, label: "Tên bài tập", hint: "Ví dụ: Ôn tập từ vựng tuần 1." },
  { name: "description", label: "Hướng dẫn làm bài", type: "textarea", hint: "Mô tả rõ học viên cần làm gì và cách nộp bài." },
  {
    name: "due_at",
    label: "Hạn nộp",
    type: "datetime-local",
    hint: "Chọn ngày và giờ cần nộp. Có thể để trống nếu chưa có hạn.",
  },
];
