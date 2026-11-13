import {
  type Program
} from "@/lib/data/collaboration";

export const programStatuses: Record<Program["status"], string> = {
  draft: "Bản nháp",
  active: "Đang hoạt động",
  paused: "Tạm dừng",
  completed: "Hoàn thành",
  cancelled: "Đã hủy",
};
