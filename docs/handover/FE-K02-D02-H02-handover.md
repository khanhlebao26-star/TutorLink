# FE phase 2 — code đang triển khai, chưa nghiệm thu

Đã thêm route sáu tab Program, workspace client/form chung; Session dùng planned ID và actual từ server; Task/submission/history/feedback theo version; Progress/Announcement/Fees API thật; fee void tách payment mark; review sau completed và report owner UI; file bài nộp có progress/retry. Mutation giữ UUID theo cùng payload, 409 giữ form và khóa gửi đến khi tải lại. Program complete hiển thị blockers từ API.

Không chạy test/build/E2E theo yêu cầu người dùng. Test retry identity/fee semantics đã để lại, chưa chạy. Không có bằng chứng coverage, performance hoặc CI; chưa commit/push/PR/reviewer sign-off.

## Còn thiếu trước khi đóng issue

- FE-K02: không có notification list/read/unread API trong routes hiện tại, chỉ có Admin outbox. Chờ backend contract; không nối endpoint đoán. Session edit hiện hỗ trợ title/hình thức/địa điểm/link; cần bổ sung chỉnh giờ. File UX mới áp dụng bài nộp.
- FE-D02: lịch sử submission hiện trang đầu; cần thêm phân trang lịch sử, kiểm trạng thái/permission và toàn bộ validation qua RTL. Chưa đo bundle trước/sau; prefetch=false không phải bằng chứng tối ưu.
- FE-H02: chưa thiết lập/chạy Pest Browser + Playwright, chưa có E2E/coverage >70% hai codebase, chưa demo Telescope/Pulse/host alerts. Admin moderation/evidence tiếp tục dùng Filament phase 1 và policy backend.
- Không đánh dấu Done ba issue. Tiếp tục hoàn thiện các phần trên và lấy reviewer nghiệm thu sau khi người dùng test.

Người dùng tự chạy FE: npm ci, npm run typecheck, npm run lint, npm run test:coverage, npm run build. Backend dùng runner PostgreSQL test riêng với TestDatabaseGuard; không reset DB làm việc.

Comment issue: Đã bắt đầu code FE phase 2 cho workspace và trust UI. Chưa nghiệm thu; notification contract đang Blocked, E2E/coverage/monitoring và test cuối Pending. Xem handover này để phân công tiếp.
