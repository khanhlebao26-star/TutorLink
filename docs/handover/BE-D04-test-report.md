# BE-D04 — Test report tổng hợp

**Kết quả: PASS.** Môi trường test PostgreSQL tách biệt `tutorlink_test`; không dùng database local `tutorlink` để chạy test suite, reset, seed hay fault injection.

## Acceptance cuối

- D04-18 regression: **712 tests / 6,542 assertions**, 0 failure/error/skipped; bao gồm D04 feature tests, PostgreSQL race, migration preflight và backend regression.
- D04-19 HTTP qua Sanctum session/CSRF: **96/96 cases**, **16/16 D04 operations**; có status/body/header, scoped DB effects, audit/outbox/receipt, file upload/complete/attach/publish/download và permission checks.
- D04-22 final candidate được kiểm lại sau fix Overview boundary: backend regression 627/4,545; D04 regression 77/1,077; race 1/893; migration preflight 7/27; HTTP matrix 96/96. Diễn tập chính xác 9 migration trên clone test đạt.
- `tutorlink_test` được restore byte-identical về D04-18 checkpoint; test API dừng. Các thao tác time-travel/fault/race chạy trên test, không trên actual.

## Source/build đã kiểm thử

- Base branch/commit: `be-d04` / `135efcc8ec5c4c73cff8d58cabc62a17772234bf`.
- Final candidate: image `sha256:8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411`; build context 359 files, SHA-256 `4c2a8aa4fece5be88017ea299c93fb8b243a531eb1c512b8099b5514e3fed2de`.
- Contract/OpenAPI SHA-256 lần lượt `1eff7f60414be02eabafdc4701409acd11e251e876dd3af513725fe45334e703` và `c8cb008537c474ccd53c2ba234565957e7a93be89d28267b1939136099103cf2`.
- D04-20 image ban đầu là `sha256:95916de4c50255c4694cfd2dafa20878a2939f09fe46272101fe113f13a371ad`; không dùng nó làm identity của artifact cuối. D04-22 ghi nhận source fix cho Overview và re-test candidate cuối trước áp dụng.

## Môi trường và dữ liệu

- `APP_ENV=testing`, `APP_DEBUG=false`, PostgreSQL 16.15, database `tutorlink_test`, network test riêng; session/CSRF thật, storage file test tách biệt.
- Test DB sau cuối được restore từ checkpoint D04-18 byte-identical. Sau D04-22 test record còn 29 migrations, 4 Programs, 59 Fees, 55 Progress records, 4 Announcements và 1 Announcement file link; evidence chi tiết ở D04-22 test report.
- Lịch sử lỗi harness/config/fixture được giữ trong D04-19 diagnostic notes; kết quả cuối được phân biệt rõ và là 96/96.

## Giới hạn

Test chứng nhận API/backend và các integration test đã liệt kê. Không tuyên bố UI Workspace đã được xây dựng/nghiệm thu. Chi tiết từng bước và artifact trong [test evidence index](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01); release/retest manifest trong [D04-20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-20) và [D04-22](evidence/be-d04/consolidated/actual-local.md#d04-22).
