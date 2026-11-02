# BE-D04 handover — Progress, Announcement và Fee trong Program Workspace

**Kết quả:** Hoàn tất scope BE-D04 trên test và môi trường TutorLink Docker local. D04-01 đến D04-24 đều PASS và có evidence; D04-25 tổng hợp bàn giao. Trạng thái này không chứng nhận staging hoặc production.

## Phạm vi API

BE-D04 đăng ký 16 operations mới trong OpenAPI: baseline 89 + 16 = 105 operations, không mất route nền.

| Nhóm | Operations |
|---|---|
| Overview | `GET /api/v1/programs/{program}/overview` |
| Progress | `GET /api/v1/programs/{program}/progress-records`; `POST /api/v1/programs/{program}/progress-records`; `PATCH /api/v1/progress-records/{progressRecord}` |
| Announcement | `GET /api/v1/programs/{program}/announcements`; `GET /api/v1/announcements/{announcement}`; `POST /api/v1/programs/{program}/announcements`; `PATCH /api/v1/announcements/{announcement}`; `POST /api/v1/announcements/{announcement}/publish` |
| Fee | `GET /api/v1/programs/{program}/fee-records`; `POST /api/v1/programs/{program}/fee-records`; `GET /api/v1/fee-records/{feeRecord}`; `PATCH /api/v1/fee-records/{feeRecord}`; `POST /api/v1/fee-records/{feeRecord}/mark-paid`; `POST /api/v1/fee-records/{feeRecord}/mark-unpaid`; `POST /api/v1/fee-records/{feeRecord}/void` |

API success payloads dùng `{data: ...}`; lỗi dùng error envelope chung và correlation header `X-Request-ID`. Owner/member/scope/lifecycle checks, expected version và response fields được đặc tả trong [contract](BE-D04-contract.md) và [generated OpenAPI](../api/openapi.json). BR-26 pagination áp dụng `per_page=20` mặc định, tối đa 50; Progress, Published Announcement và Fee có thứ tự ổn định. Owner có thể xem Draft Announcement; Customer chỉ đọc Published.

Các write Fee và Announcement publish dùng UUID `Idempotency-Key`, request hash và response snapshot. Progress và Draft Announcement không yêu cầu key. No-op cùng giá trị giữ nguyên version/audit/outbox; replay cùng key trả snapshot đã ghi nhận. Trạng thái/version, audit và outbox được phối hợp trong transaction theo thứ tự khóa đã kiểm tra trên PostgreSQL.

## Schema và tích hợp

Áp dụng 9 migration đã preflight và diễn tập: hai migration D04 cùng bảy migration nền còn pending ở target. Database kết thúc ở 34 migrations Ran, 0 Pending.

Migration set: `2026_10_27_074546_add_h02_approval_support`, `2026_10_28_034616_align_messaging_contract`, `2026_10_28_140000_prepare_message_sending`, `2026_10_28_200000_add_file_scan_evidence`, `2026_10_30_180000_create_realtime_sessions_table`, `2026_11_01_014629_add_version_and_terminal_replay_to_program_sessions`, `2026_11_01_023131_preserve_program_session_timestamp_precision`, `2026_11_01_233000_add_be_d04_workspace_schema`, `2026_11_02_000000_enforce_d04_fee_currency`. Xem [actual migration output](evidence/be-d04/consolidated/actual-local.md#d04-22).

- `progress_records`: `value` thành `smallint`, thêm `version`, metric/value/version checks và index theo Program/thời gian/ID.
- `announcements`: thêm `version`, checks và index riêng cho Published/Draft.
- `fee_records`: đổi `declared_status` thành `status` (`declared`/`voided`); thêm `tutor_payment_mark varchar(20) NOT NULL DEFAULT 'unmarked'` (`unmarked`/`marked_paid`/`marked_unpaid`), `version integer NOT NULL DEFAULT 1`, `voided_at timestamptz NULL`, `voided_by bigint NULL` (FK `users.id`, `ON DELETE RESTRICT`), `void_reason text NULL`, `payment_marked_at timestamptz NULL`, `payment_marked_by bigint NULL` (FK `users.id`, `ON DELETE RESTRICT`), `payment_mark_reason text NULL` và `last_change_reason text NULL`. Currency hiện có bị ràng buộc `VND`; checks khóa amount trong miền safe integer, kỳ tháng, status/mark, version, zero-amount mark và tính nhất quán metadata void/payment.
- Tổng cộng có 13 D04 database constraints. File Announcement dùng `file_links` hiện có; POST/PATCH gắn/thay/tháo file Draft trong transaction, Published giữ bất biến và quyền đọc/tải được kiểm tra ở mỗi request.

Audit bao phủ Progress update, Announcement publish, Fee create/update/mark/void và file link/unlink. Notification outbox chỉ giao sau commit. Announcement publish receipt có TTL 30 ngày; Fee receipts giữ `expires_at=NULL` đến khi Fee và lịch sử liên quan đủ điều kiện purge. Không thêm payment transaction hay `customer_confirmed_at`; Fee luôn mang disclaimer rằng khai báo của Tutor không phải bằng chứng thanh toán.

## Test và release candidate

- PostgreSQL test tách biệt: `tutorlink_test`; guard ngăn nhầm database ứng dụng. D04-19 đạt **96/96 HTTP cases**, bao phủ **16/16 operations**, session/CSRF, lifecycle, no-op/stale/replay, file integration/download và Fee transitions.
- Regression trước release: 712 tests / 6,542 assertions, 0 failure/error/skipped; PostgreSQL race, rollback/fault, worker delivery và retention có test riêng. Migration preflight/rehearsal đạt; `tutorlink_test` được restore byte-identical về checkpoint D04-18 và test API dừng.
- Candidate D04-20 ban đầu (`sha256:95916de4…`) được supersede sau khi sửa ranh giới thời gian microsecond của Overview. Candidate cuối D04-22 (`sha256:8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411`) được retest đầy đủ trước khi áp dụng: 627 backend regression tests / 4,545 assertions; 77 D04 tests / 1,077 assertions; race 1/893; migration preflight 7/27; HTTP 96/96.
- Contract SHA-256 `1eff7f60414be02eabafdc4701409acd11e251e876dd3af513725fe45334e703`; OpenAPI SHA-256 `c8cb008537c474ccd53c2ba234565957e7a93be89d28267b1939136099103cf2`.

Chi tiết theo task trong [test evidence index](evidence/be-d04/test/evidence-index.md) và [BE-D04 test report](BE-D04-test-report.md).

## Áp dụng và kiểm chứng local

- D04-21 xác nhận target là local Docker Compose, database `tutorlink` trên PostgreSQL 16.15. Backup là database dump ngoài repo; storage volume không nằm trong backup này.
- D04-22 áp dụng đúng 9 pending migrations, kết thúc 34 Ran/0 Pending; route parity 105/105 và 16/16 D04. Candidate application image dùng dưới đây là local-only, không push registry.
- D04-23 gọi **16/16 operations** bằng 63 HTTP requests với ba actor tổng hợp và hai Program. Kiểm tra đủ Progress, Draft/Published Announcement, Fee transitions, Overview, quyền file, audit, outbox và replay.
- D04-24 giữ runtime ổn định **622 giây**, cùng snapshot đầu/cuối; log filter không thấy lỗi trong cửa sổ, mọi outbox/receipt/file check đạt.

### Dữ liệu nghiệm thu còn giữ

| Resource | Trạng thái cuối |
|---|---|
| Synthetic users 52–54 | Owner, member, outsider |
| Program 1 / 2 | Active / cancelled |
| Progress 1 | `value=55`, version 2 |
| Announcement 1 | Published, version 4, liên kết private File 25 |
| Fee 1 | 2026-11, 600000 VND, `voided`, `marked_unpaid`, version 5 |
| File/outbox/receipt | File 25 giữ checksum đã đối chiếu; 7/7 event processed một lần; 8 Fee/Announcement receipts còn nguyên |

Không sửa hồ sơ người dùng có sẵn và không xóa Fee, Published Announcement, file hoặc history để dọn acceptance records.

## Source và trạng thái vận hành

- Branch `be-d04`; base commit [`135efcc8ec5c4c73cff8d58cabc62a17772234bf`](https://github.com/khanhlebao26-star/TutorLink/commit/135efcc8ec5c4c73cff8d58cabc62a17772234bf). Worktree còn dirty; không có implementation commit/PR được ghi trong evidence. Đây là base commit, không đại diện riêng cho toàn bộ D04 diff.
- Candidate build context có 359 file, SHA-256 `4c2a8aa4fece5be88017ea299c93fb8b243a531eb1c512b8099b5514e3fed2de`; tại D04-25 kiểm tra lại **359/359 file hash khớp**. Application image hiện chạy là `sha256:8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411`; image vẫn local-only.
- Kiểm tra hiện tại: backend và PostgreSQL healthy; outbox/broadcast/Slack workers và scheduler running; database `tutorlink`, migrations 34/0. Runtime là `APP_ENV=local`, `APP_DEBUG=true`, queue `database`, filesystem `local`, session `database`.
- Frontend Workspace chưa được nghiệm thu trong D04; evidence UI không được tuyên bố từ API transcript. D04 actual chỉ chứng nhận local Compose; không có kết quả staging/production.

## Failure history và kết quả cuối

- D04-19 có harness/config/fixture lỗi ban đầu; đã sửa và giữ diagnostic notes. Chỉ lượt cuối 96/96 được tính là accepted result.
- D04-23 có một probe tải file trước khi attach và nhận 403; trình tự đúng sau đó đạt Draft/member 404 và Published/member 200.
- Đầu D04-24 runtime đã dừng và được khởi động lại bằng image/volume hiện có, không reset/migrate DB. Observer ban đầu dùng XSRF token cũ sau login và nhận 419; helper được sửa để đọc cookie hiện hành, replay cuối đạt 200/201/200. Không có lỗi ứng dụng còn mở.

## Evidence và issue update

- [Master plan](BE-D04-plan.md) · [Test index](evidence/be-d04/test/evidence-index.md) · [Actual index](evidence/be-d04/actual/evidence-index.md)
- [Test report](BE-D04-test-report.md) · [D04-25 report/evidence](evidence/be-d04/consolidated/actual-local.md#d04-25)
- Issue #78: https://github.com/khanhlebao26-star/TutorLink/issues/78

### Nội dung sẵn dùng để cập nhật issue #78

> BE-D04 đã hoàn tất scope trên môi trường test và TutorLink Docker local; D04-01..25 đều hoàn thành. Test candidate cuối được định danh bằng image SHA-256 `8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411`, build context SHA-256 `4c2a8aa4fece5be88017ea299c93fb8b243a531eb1c512b8099b5514e3fed2de`; 359/359 file khớp. Test đạt 712 tests / 6,542 assertions, 96/96 HTTP cases; actual local đạt 16/16 operations, 34 migrations Ran/0 Pending và cửa sổ stability 622 giây. Acceptance Fee, Published Announcement, file và receipts được giữ theo policy. Đây là kết quả local-only, chưa chứng nhận staging/production hoặc UI Workspace. Báo cáo: `docs/handover/BE-D04-handover.md`, `docs/handover/BE-D04-test-report.md`; plan và evidence index nằm trong `docs/handover/`.
