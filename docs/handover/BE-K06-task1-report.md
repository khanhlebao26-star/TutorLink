# BE-K06 — Task 1: xác nhận môi trường

Ngày kiểm tra: 04/11/2026, khoảng 15:06 (Asia/Ho_Chi_Minh).
Issue: https://github.com/khanhlebao26-star/TutorLink/issues/85

**Cập nhật sau Task 1–4:** user đã xác nhận channel thử; Slack thật gửi thành công qua native worker/queue riêng. Đã sửa runtime cũ thiếu Filament/intl, health cuối ready. Xem [báo cáo hiện hành](BE-K06-test-report.md). Nội dung bên dưới giữ snapshot ban đầu; việc đăng GitHub do user tự thực hiện theo yêu cầu.

## Kết quả

Môi trường local sẵn sàng cho regression/integration. Preflight Slack xác thực token thành công; chưa xác minh channel thử và chưa gửi thông báo thật. Task 1 còn điều kiện xác nhận channel, không đánh dấu toàn bộ BE-K06 hoàn thành.

- Branch: `be-k06`.
- Baseline: `fb54886617524e43727705f7f6ee86bed4135ef9`.
- Đã `git fetch origin`; HEAD, local main và origin/main cùng baseline, không cần merge hay tạo commit rỗng.
- Các commit triển khai/bàn giao BE-K01–BE-K05 đều nằm trong ancestry của origin/main (5/5). Đây là xác nhận code đã tích hợp, không chứng nhận toàn bộ acceptance/review/deployment của các issue phụ thuộc.
- Runtime `tutorlink-src` đã chạy trước khi kiểm tra; không cần khởi động lại hoặc thay đổi môi trường production.
- PostgreSQL `tutorlink`: accepting connections; 38 migration Ran, 0 Pending. Không chạy migration, reset hoặc seed.
- Backend và DB healthy; Reverb và các worker/scheduler đang chạy.
- `GET http://localhost:8000/up`: HTTP 200.
- `GET http://localhost:8000/health/ready`: HTTP 200, ready; app/db/outbox-worker/slack-worker/broadcast-worker/scheduler đều ok.
- Route inventory: 36 entries liên quan auth/file/conversation/session/notification-outbox. Chỉ xác nhận đăng ký API; chưa smoke nghiệp vụ hay chạy test suite.

## Slack và điều kiện còn thiếu

- Cấu hình runtime có token và channel; không ghi giá trị secret vào evidence. `backend/.env.docker` được Git bỏ qua.
- Slack API thật `auth.test`: HTTP 200, `ok=true`.
- Slack API thật `conversations.info`: HTTP 200, `ok=false`, `error=missing_scope`; chưa xác minh membership/archive của channel qua API này.
- Cần nhóm xác nhận channel đã cấu hình là channel thử được cấp và bot đã được thêm vào đó. Có thể xác nhận trực tiếp trong Slack; không cần tự ý mở rộng quyền token chỉ để đọc metadata.
- Chưa gửi tin, chưa chứng nhận `chat:write` hay delivery. `missing_scope` ở API đọc channel không đồng nghĩa gửi tin sẽ thất bại.
- Có thể tiếp tục Task 2 local; phần xác nhận channel cần giải quyết trước smoke Slack thật ở Task 4.

## Evidence

Thư mục: [task1-20261104-1506](evidence/be-k06/task1-20261104-1506/).

- `baseline-commit.txt`: commit được kiểm tra.
- `dependencies.json`: commit từng dependency và kết quả ancestry.
- `runtime.txt`: trạng thái container.
- `db-readiness.txt`, `migrations.txt`: PostgreSQL và migration.
- `health-up.txt`, `health-ready.txt`: kết quả health API.
- `routes.json`: method/path/action/middleware thuộc phạm vi.
- `slack-preflight.json`: kết quả preflight Slack đã loại secret.

Các file evidence cũ trực tiếp trong `evidence/be-k06/` được giữ nguyên. Đợt kiểm tra này dùng thư mục riêng để không ghi đè dữ liệu trước đó.

## Tiếp theo

Chạy regression auth/file và integration chat/session/outbox trên PostgreSQL; ghi riêng fake tests với Slack thật. Chưa thay đổi source ứng dụng, chưa commit/push hoặc tạo PR trong Task 1.

## Cập nhật issue

Đã đọc đúng issue #85 và thử đăng kết quả. GitHub connector từ chối POST comment với HTTP 403 `Resource not accessible by integration`. Trình duyệt dự phòng đang ở trạng thái chưa đăng nhập, nên chưa đăng được cập nhật lên issue. Nội dung sẵn để đăng tại [BE-K06-issue-update.md](BE-K06-issue-update.md). Đây là blocker của việc cập nhật issue, không phải lỗi runtime/backend.
