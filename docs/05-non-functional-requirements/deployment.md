# Deployment

Compose hiện định nghĩa db/frontend/backend/mailpit; đây là config được đọc, không xác nhận service đang healthy. Mailpit nhận SMTP local ở 1025, inbox localhost:8025; không chuyển mail thật và không là queue worker.

Local FE localhost:3000, BE localhost:8000, DB host port 5433. Bảo toàn `.env`/`.env.docker`; không copy secret vào docs. `--no-reload` trên artisan serve giữ Compose env cho subprocess theo thay đổi hiện tại; đó không phải production server.

Demo: chọn host/domain/storage/email provider; HTTPS và Sanctum origins/session/CORS đúng, secrets riêng, APP_DEBUG=false. Worker/scheduler thêm cho notification/expiry; Reverb chỉ nếu được scope/kiểm thử. Migration non-destructive, cùng reviewed commit FE/BE.

W4-A1/A3: clean setup/build, backup, migration, process health, smoke login/search/message/accept/download, restore thử môi trường riêng và rollback runbook. Không deploy/push/chỉnh DNS chỉ vì docs đã ghi kế hoạch.
