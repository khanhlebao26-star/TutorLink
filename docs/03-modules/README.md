# Module index

Ranh giới này để giao việc và đọc context, không yêu cầu refactor sang `app/Modules`. Mỗi README nói điểm vào; PRD nói việc triển khai/AC. Trạng thái là snapshot ngày 08/10/2026, không tự cập nhật.

- [M01 Identity](M01-identity-access/prd.md): code đã có, acceptance chưa đóng toàn bộ.
- [M02 Tutor approval](M02-tutor-approval/prd.md): code đã có, Admin/browser/seed evidence còn thiếu.
- [M03 Marketplace](M03-catalog-marketplace/prd.md): schema/FE shell/contract có, business API chưa có; W1-A3/B3.
- [M04 Messaging](M04-messaging/prd.md): schema có, API/UI chưa có; C3 chuẩn bị, W2 triển khai.
- [M05 Program](M05-program-invitation/prd.md): schema nền cần delta; W2.
- [M06 Session](M06-session-workspace/prd.md): schema có, workspace API/UI chưa có; W3.
- [M07 Task Progress](M07-task-progress/prd.md): schema có, version/feedback delta; W3.
- [M08 Operations](M08-fee-announcement-notification/prd.md): schema có, API/worker chưa có; W2–W3.
- [M09 Trust](M09-review-report-audit/prd.md): Admin approval audit có; Review/Report API chưa có; W3.
- [M10 File](M10-files/prd.md): local API có, resource/complete/historical policy còn cần hoàn thiện.

Owner cụ thể từng tuần xem [ownership](../08-project/ownership.md). Một module có thể có nhiều người qua các slice; không giao chung một file cho hai người.
