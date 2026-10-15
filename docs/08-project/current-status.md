# Current status

Đồng bộ **15/10/2026** theo W1 closeout và quyết định Settings/Reviews của Hoàng. Base commit `48dc7f857af71dec6ce90897d1cf82b531da67cf`, branch `codex/w1-c3-g1-acceptance`; implementation/evidence hiện là working-tree changes chưa commit/push. Không gọi base commit là revision đã chứa các sửa closeout.

## Đã triển khai và có evidence local

Auth/File/TutorProfile/Admin/Catalog, Listing public/owner/search, Tutor public/reviews GET và availability đã có. Owner status_counts/allowed_actions, tutor_slug, reviews UI, `/settings` và responsive đã được nối API. Auth giữ nguyên Laravel Sanctum session cookie/CSRF; Breeze/starter kit optional sau.

Settings W1 chỉ sửa full_name qua PATCH `/me` và đọc account qua GET `/me`; chưa password/avatar Settings. Reviews chỉ nhận Program completed; schema PostgreSQL chưa cho completed nên GET trả rỗng 200 đúng scope. W3-C3 mới thêm lifecycle/migration và Review write, tái dùng GET và chạy populated PostgreSQL tests; không làm lại endpoint.

[Closeout report](../06-acceptance/issue-16-closeout.md): SQLite 80 pass / 6 skip; PostgreSQL 84 pass / 2 skip; permission/transaction 8 tests / 151 assertions;93 browser thật,27 header và14 UI contract checks riêng. Có 21 ảnh visual settled. Số skip không tính Pass; hai PG skip là Completed reviews đã defer. Không coi simulation là dữ liệu PostgreSQL thật.

## Gate và việc tiếp theo

G1 functional trong scope đã Verified local; gate tổng còn Blocked tại đối chiếu visual/reviewer và CI remote, chưa Accepted. Settings thiếu reference riêng; các khác biệt Figma/DTO được ghi trong visual report. G2–G4 chưa được chứng nhận.

Có thể chuẩn bị/thực thi phần W2 độc lập trên nhánh cách ly, bắt đầu contract/schema delta Messaging/Program/Invitation. Giữ G1 dependency trước tích hợp/nghiệm thu G2; không tự miễn gate vì bắt đầu tuần mới. Messaging chưa có service/API chỉ vì đã có bảng; dùng [delta C3](../06-acceptance/evidence/issue-16/messaging/REPORT.md).

## Môi trường và thay đổi cần giữ

Closeout dùng DB `tutorlink_test` cho PostgreSQL suite và `tutorlink_browser_test` cho browser trong Compose riêng; SQLite memory có fail-fast guard trước connection. Fingerprint dev trước/sau không đổi; không khởi động stack dev chỉ để đo. Không suy health của stack dev từ runtime test.

Không reset dirty tree. Bốn deletion trong `docs/contracts/` ngoài scope closeout được giữ nguyên để kiểm tra riêng trước commit/PR. Các snapshot ngày 07–08/10 và kết quả34tests là lịch sử, không mô tả trạng thái hiện tại; baseline G1 và lượt retest được lưu riêng trong acceptance hub.
