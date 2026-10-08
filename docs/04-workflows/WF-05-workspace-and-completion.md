# WF05 Workspace tới Completed

Actor: Tutor owner và Customer member; outsider để kiểm quyền. Tiền điều kiện: Program Active từ WF04, capability server và file policies.

1. Overview hiển thị next Session/task counts/progress/announcement từ aggregate thật.
2. Tutor tạo Session UTC/timezone, Task giao Customer; Customer submit attachment/content.
3. Tutor feedback đúng submission version; ghi Progress, Announcement và Fee tháng nhập tay có disclaimer.
4. Pause: UI bỏ action tạo Session/Task/Announcement/Fee, còn đọc/submit/feedback/progress theo policy; Resume về Active.
5. Resolve/cancel Scheduled Session, feedback Submitted Task; xác nhận hủy Not Started còn lại rồi Complete.
6. Completed readonly, không còn Pause/Resume; Customer đủ điều kiện có Review action.

Nhánh lỗi: outsider, resubmit/feedback race, stale lifecycle 409/refetch, invalid schedule, fee trùng tháng, Task Completed bị gắn overdue sai, Complete khi còn việc chưa xử lý. Cancel Program riêng phải readonly và không Review.

Evidence AC-G3-01 đến 05: từng tab thật, trạng thái/role matrix và browser error/retry. Không lấy screenshot sáu tab rỗng làm chứng minh Workspace hoạt động.
