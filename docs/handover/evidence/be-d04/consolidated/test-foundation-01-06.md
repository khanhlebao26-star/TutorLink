# Test foundation · D04-01–06

Test: baseline, cô lập DB, fixtures, contract cases, migration/backfill và nền validation/quyền/khóa/response.

- **Gói lưu trữ:** [test-foundation-01-06.zip](test-foundation-01-06.zip)
- **Manifest đường dẫn/kích thước/hash từng artifact:** [test-foundation-01-06.manifest.json](test-foundation-01-06.manifest.json)
- **SHA-256 ZIP:** `69536964f7b3ba5d250d08e7dfe456091501eb0b39c029906692541bfe94db15`
- **Nội dung:** 68 artifact · 301,050 byte trước nén.
- Từng thành viên đã được đối chiếu SHA-256 với nguồn trước khi gom. Để khôi phục cấu trúc gốc, giải nén ZIP vào `docs/handover/evidence/be-d04/`; manifest giữ đường dẫn tương đối ban đầu.

## Evidence test theo bước

<a id="d04-01"></a>
### D04-01 — Ghi nhận baseline và giới hạn thay đổi.md
01/11/2026 · branch `be-d04` · HEAD `135efcc8ec5c4c73cff8d58cabc62a17772234bf`. Test config được đọc; PostgreSQL test chạy healthy và kết nối vào `tutorlink_test` thành công; baseline ban đầu là DB rỗng (0 bảng, chưa có migration table); sau đó theo yêu cầu đã copy DB `tutorlink` sang `tutorlink_test`, đối chiếu đủ 43 bảng và số dòng từng bảng khớp nguồn. Runtime Docker local có 89 route entries/120 method atoms, OpenAPI 89 operations, 0 route D04, 25 migration Ran và 7 Pending. Sáu dependency commit có trong ancestry. Chưa thay đổi DB ứng dụng.

<a id="d04-02"></a>
### D04-02 — Dựng môi trường test cô lập và chặn nhầm database.
01/11/2026 · preflight identity đạt; guard suite đạt 8 tests/45 assertions. Test DB giữ nguyên 43 bảng, 154 dòng, 25 migration và 51 user như nguồn; không chạy migration/seed/reset. DB ứng dụng không được dùng để thử guard.

<a id="d04-03"></a>
### D04-03 — Tạo bộ dữ liệu test có kết quả kỳ vọng rõ.
01/11/2026 · tạo fixture tổng hợp trên `tutorlink_test`: 8 actors, 4 Programs, 4 memberships, 59 Fees, 55 Progress, 4 Announcements, 5 Files, 3 Program Sessions, 5 Tasks và 2 Task Submissions. Có kỳ activation/current, tháng nhuận, pagination >50, tie-break, Draft, lifecycle, member revoke/late join, file states và Overview oracle. Seed chạy hai lần; snapshot byte-identical. Guard unit tests đạt 8 tests/57 assertions. Không chạy migration/reset; DB nguồn không đổi.

<a id="d04-04"></a>
### D04-04 — Viết case kiểm chứng contract trước khi hoàn thiện writer.
01/11/2026 · 16 route probes và 13 schema assertions chạy trên PostgreSQL 16.15, tất cả fail đúng do 16 route chưa đăng ký và schema Fee/Progress/Announcement chưa đạt contract. Laravel kết nối đúng `tutorlink_test`; không lỗi cấu hình. Ma trận bao gồm 7 nhóm PostgreSQL constraint, 7 race case và 4 rollback case với expected response/DB side effects. DB giữ 43 bảng/25 migrations và fixture counts không đổi; không chạy migration/reset/truncate/seed.

<a id="d04-05"></a>
### D04-05 — Hoàn thiện migration/backfill và diễn tập trên test.
01/11/2026 · migration đạt trên `tutorlink_test`; giữ 59 Fee, 55 Progress, 4 Announcement và 1 file link; 26 migration đã chạy, gồm D04 đúng một lần. DB rỗng chạy đạt toàn chuỗi 33 migration. Fail-fast đạt 7 tests/27 assertions; PostgreSQL constraints 18/33; schema contract 13/19. Restore drill fingerprint khớp; side effects audit/outbox/receipt/notification không đổi. DB `tutorlink` không bị thay đổi.

<a id="d04-06"></a>
### D04-06 — Làm nền chung cho validation, quyền, khóa và response.
02/11/2026 · focused HTTP suite đạt 11 tests/79 assertions trên `tutorlink_test`. Transcript xác nhận 401/403/404/409/422, normalization CRLF/trim có chọn lọc, omission khác null, integer string/float/bool bị từ chối, unknown body/query bị từ chối. PostgreSQL lock trace đạt thứ tự contract. DB row counts và audit/outbox/receipt/notification giữ nguyên. Route D04 sản phẩm vẫn để các bước D04-07 trở đi đăng ký; D04-06 dùng route probe chỉ trong test.
