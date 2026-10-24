# BE-D01 — Báo cáo tổng kết Issue #36

**Issue:** [BE-D01 — Khóa schema delta và migration nền tảng](https://github.com/khanhlebao26-star/TutorLink/issues/36)  
**Branch:** `codex/2w-be-d01`  
**Ngày tổng hợp:** 22/10/2026  
**Phạm vi:** M01–M10, migration nền tảng, PostgreSQL verification và ERD  
**Kết luận:** **Đạt bốn tiêu chí nghiệm thu kỹ thuật của Issue #36 trên PostgreSQL test.** Issue trên GitHub vẫn `open`, checklist Task 5 và các tiêu chí nghiệm thu chưa được cập nhật; PR và reviewer chưa nghiệm thu độc lập.

Đây là báo cáo chính thay cho năm báo cáo Task 1–5. Số liệu và kết quả bên dưới lấy từ các lần kiểm thử đã ghi trong năm báo cáo đó ở commit `6cc1d0b2dfbea701bae7a5617c9c313c5b75270c`; lần tổng hợp này không chạy lại migration, seed hoặc test và không kết nối database thật.

## 1. Công việc đã hoàn thành

| Task | Kết quả | Bằng chứng chính |
|---|---|---|
| 1 — Inventory và mapping | Kiểm kê tám domain User, Profile, Listing, Program, Invitation, Submission, Fee, Report; lập delta và owner | Baseline PostgreSQL 16.15: 39 bảng, 51 users, 18 tutor profiles, 24 files; các bảng nghiệp vụ mới liên quan Program/Submission/Notification chưa có dữ liệu |
| 2 — Program và lifecycle | Thêm `programs.customer_id` NOT NULL/FK, backfill theo đúng một Customer, cập nhật lifecycle CHECK và hai partial UNIQUE | PostgreSQL test suite 107 passed/551 assertions; fixture legacy có một row ở mỗi bảng Program, Member, Invitation, Program Session, Task, Submission được giữ số lượng và chuyển trạng thái theo mapping |
| 3 — Assignment, Outbox, Submission | Thêm assignment trên Tutor Profile/Report, `notification_outbox` và `event_key`, Submission version/idempotency/current pointer | Test tập trung trên PostgreSQL 3 passed/22 assertions; fixture Notification giữ payload; fixture Submission cũ kích hoạt safe-stop và giữ nguyên row |
| 4 — Quy ước database | Xác minh FK/index, VND integer, UTC `timestamptz`, weekday 0–6, phân biệt HTTP session và buổi học | Migration trên rehearsal thành công; PostgreSQL catalog xác nhận CHECK/FK/index; không còn cột instant kiểu `timestamp without time zone` trong lần kiểm tra |
| 5 — Xác minh cuối | Fresh migration, seed hai lần, restore backup vào database test, thử `completed`/UNIQUE/CHECK, cập nhật ERD | 23/23 migration `Ran`; seed 4/4/5 catalog rows và một local Admin ở cả hai lần; 51 users/18 profiles/24 files giữ nguyên số lượng và fingerprint; PostgreSQL chấp nhận `completed`, từ chối dữ liệu vi phạm |

## 2. Schema delta đã triển khai trong phạm vi issue

| Migration mới | Nội dung |
|---|---|
| [`2026_10_22_000000_align_program_schema_with_domain_design.php`](../../backend/database/migrations/2026_10_22_000000_align_program_schema_with_domain_design.php) | `programs.customer_id`, backfill có kiểm tra, lifecycle CHECK và partial UNIQUE cho pending Invitation/Customer membership |
| [`2026_10_22_073014_add_admin_assignment_columns_to_tutor_profiles_and_reports.php`](../../backend/database/migrations/2026_10_22_073014_add_admin_assignment_columns_to_tutor_profiles_and_reports.php) | Assignment nullable, FK Admin reference, `assignment_version >= 0`, queue indexes |
| [`2026_10_22_073015_create_notification_outbox_and_add_event_key_to_notifications.php`](../../backend/database/migrations/2026_10_22_073015_create_notification_outbox_and_add_event_key_to_notifications.php) | Outbox, notification event key/UNIQUE; notification legacy dùng marker `legacy:<UUID>` và giữ payload |
| [`2026_10_22_073016_add_submission_versioning_and_idempotency.php`](../../backend/database/migrations/2026_10_22_073016_add_submission_versioning_and_idempotency.php) | Submission version, UUID key, request hash, UNIQUE theo Task/Customer, current pointer với composite FK; dừng an toàn nếu có Submission legacy |
| [`2026_10_22_080000_enforce_utc_timestamps_and_vnd_currency.php`](../../backend/database/migrations/2026_10_22_080000_enforce_utc_timestamps_and_vnd_currency.php) | CHECK currency VND và chuyển các cột instant legacy sang `timestamptz` theo UTC |

Chỉ thêm migration mới; migration cũ không bị sửa để vượt kiểm thử. Task 2–4 đã ghi nhận các lần áp dụng riêng lẻ lên `tutorlink` sau phê duyệt lúc đó. Task 5 chỉ thao tác trên database test.

## 3. Bằng chứng nghiệm thu PostgreSQL

### Fresh migration và seed

- Database trống `tutorlink_be_d01_task5_fresh_20261022`: `php artisan migrate --force` với `DB_CONNECTION=pgsql` và `DB_DATABASE` trỏ rõ vào database test; `migrate:status` báo **23/23 Ran**.
- `db:seed --force` chạy hai lần với `APP_ENV=testing` và tài khoản local disposable. Sau từng lần: **4 categories, 4 subcategories, 5 specializations, 1 Admin**. Cả năm đường slug catalog giữ nguyên; không có key hoặc row tăng ngoài ý muốn.
- Task 2 cũng đã chạy toàn bộ Laravel suite trên PostgreSQL test: **107 passed, 551 assertions**.

### Restore và bảo toàn dữ liệu có thật

- Backup trước Task 4 `/tmp/tutorlink-live-pre-be-d01-task4-20261022.dump` tồn tại, SHA-256 `b61926ec5f89c847aaaf2113225da5de1afcbcceff5dd4f645b01d33481c535f`; `pg_restore --list` đọc được archive PostgreSQL 16.15 với **369 TOC entries**.
- Restore vào `tutorlink_be_d01_task5_legacy_20261022`. Bốn migration còn Pending trong snapshot (public slug, 2FA, Passkeys, Task 4) chạy xong; tổng cộng **23/23 Ran**.
- Trước/sau migration: **51 users, 18 tutor profiles, 24 files**. Fingerprint các trường nghiệp vụ ổn định trùng nhau: users `6fef1a10367ce8cbee4423aec602221f`, tutor profiles `6b52d2b1de53fdc1e0c12803726d0b22`, files `680f809e3d1c3052f792fc4809dc22e1`. Sau backfill, **18/18** tutor profiles có `public_slug`; kiểm tra quan hệ Task/File cho **0 orphan**.
- Trong backup này, `programs`, `program_invitations`, `program_members`, `tasks`, `task_submissions`, `notifications` đều **0 row** trước và sau. Đây là trạng thái dữ liệu hiện có, không phải lỗi hoặc yêu cầu phải thêm dữ liệu thật. Fixture tổng hợp của Task 2 đã kiểm tra backfill Program/Invitation/Member; Task 3 đã kiểm tra Notification payload và Submission safe-stop riêng.

### `completed`, UNIQUE và CHECK

Task 5 tạo fixture hợp lệ trong transaction PostgreSQL test rồi rollback toàn bộ. `programs.status = completed` được chấp nhận và đọc lại thành công. PostgreSQL từ chối:

| Ràng buộc | Trường hợp đã thử | Kết quả |
|---|---|---|
| Program status CHECK | `invalid_state` | `check_violation` |
| Invitation partial UNIQUE | Invitation `pending` thứ hai cho cùng Program | `unique_violation` |
| Membership partial UNIQUE | Customer membership thứ hai cho cùng Program | `unique_violation` |
| Submission UNIQUE | Trùng `(task, customer, version)` hoặc `(task, customer, idempotency_key)` | `unique_violation` cho cả hai |
| Notification UNIQUE | Trùng `(user, type, event_key)` | `unique_violation` |
| Assignment CHECK | `assignment_version = -1` | `check_violation` |
| VND CHECK | Fee âm hoặc currency `USD` | `check_violation` cho cả hai |
| Weekday CHECK | `-1` hoặc `7` | `check_violation` cho cả hai |

Các phép thử này xác minh schema PostgreSQL. Điều kiện nghiệp vụ để một Program được hoàn thành thuộc API/runtime và không được suy ra chỉ từ CHECK database.

## 4. Đối chiếu bốn tiêu chí của Issue #36

| Tiêu chí | Kết luận | Cơ sở |
|---|---|---|
| Không mất dữ liệu trong fixture legacy | **PASS trong phạm vi dữ liệu/fixture đã kiểm tra** | Backup có dữ liệu giữ nguyên count và fingerprint; Task 2 giữ row/mapping trên fixture Program; Task 3 giữ payload Notification và row khi Submission guard dừng |
| Schema hỗ trợ `completed` | **PASS** | PostgreSQL chấp nhận và lưu trạng thái `programs.completed` trong fixture hợp lệ |
| UNIQUE/CHECK xác minh trên PostgreSQL | **PASS** | Các thao tác vi phạm trả `unique_violation`/`check_violation`; fixture rollback |
| Mapping còn mở ghi Blocked | **PASS** | Các điều kiện chưa có mapping an toàn được ghi ở Mục 6; không tự gán giá trị lịch sử |

Với dữ liệu hiện tại, bốn tiêu chí kỹ thuật của issue có bằng chứng đạt. Đây là đánh giá từ báo cáo kiểm thử; reviewer chưa xác nhận trên GitHub.

## 5. ERD và ranh giới thiết kế/runtime

- [ERD Draw.io trong repository](../Architecture%20%26%20Technical%20Design/TutorLink%20Database%20Design.drawio) đã ghi quan hệ `programs.customer_id`, Assignment, Outbox, Submission version/idempotency, `tasks.current_submission_id`, các FK/UNIQUE quan trọng và nhật ký Task 5. File XML đã được kiểm tra hợp lệ.
- Thiết kế đích có **43 bảng**; fresh PostgreSQL test sau 23 migration có **40 bảng ứng dụng**. `notification_outbox` đã có. `tutor_profile_revisions`, `listing_moderation_holds`, `idempotency_receipts` còn là bảng đích, chưa có migration.
- ERD trong repository là bản cập nhật của lần nghiệm thu này. File Draw.io gốc trên Drive chưa được ghi lại; cần đồng bộ nếu nhóm dùng Drive làm nguồn trình bày chính.

## 6. Mapping còn mở và giới hạn

| Mục | Trạng thái | Tác động và việc tiếp theo |
|---|---|---|
| Submission legacy **có row** nhưng thiếu idempotency key/request hash lịch sử | **Blocked có điều kiện** | Migration chủ động dừng trước thay đổi schema; fixture Task 3 đã chứng minh không mất row. Database hiện có 0 Submission nên điều kiện này không chặn nghiệm thu dữ liệu hiện tại. Nếu gặp database khác có row cũ, phải duyệt phương án chuyển đổi trước khi triển khai. |
| Consent lịch sử của 51 users | **Blocked về mapping dữ liệu** | `terms_version` cũ NULL và chưa có bằng chứng đồng ý; không tự backfill `terms_accepted_at` từ `created_at`. Thuộc phần triển khai BR-12 tiếp theo. |
| Fee legacy `confirmed` | **Cần đối soát nếu có dữ liệu** | Baseline có 0 Fee rows; không suy `confirmed` là đã thanh toán. Mapping cho dữ liệu có thật phải được review trước migration Fee tương lai. |
| Public tutor slug trên database thật | **Pending theo lần kiểm tra Task 4; chưa kiểm tra lại trong Task 5** | Migration backfill đã PASS trên test với 18/18 profiles. Kế hoạch triển khai riêng và phê duyệt cần có trước khi chạy trên `tutorlink`. |
| Ba bảng thiết kế đích chưa có migration | **Chưa triển khai, ngoài phần schema nền tảng đã nghiệm thu** | Không trình bày 43 bảng đích là 43 bảng runtime. Các work item Tutor revision, Listing moderation và generic idempotency tiếp tục triển khai riêng. |
| Outbox worker và generic idempotency runtime | **Chưa triển khai runtime** | BR-25 và BR-23 đã chốt policy; database schema trong issue không chứng minh worker/API đã hoạt động. |

Các tài liệu cũ từng nêu policy outbox và D08 còn mở; quyết định BR-23/BR-25 ngày 21/10/2026 đã chốt **policy**. Phần còn lại là triển khai và kiểm thử runtime.

## 7. Trạng thái database thật, kiểm thử còn mở và bàn giao

- Theo các lần kiểm tra/triển khai có phê duyệt ở Task 2–4, migration Task 2 ở batch 2, ba migration Task 3 ở batch 4–6, 2FA/Passkeys ở batch 7–8 và Task 4 ở batch 9 đã `Ran` trên `tutorlink`; public slug còn `Pending` ở thời điểm Task 4. Task 5 không kiểm tra lại live. Bộ phê duyệt tự động đã từ chối cả truy vấn chỉ đọc vào `tutorlink` trong phạm vi Task 5, nên báo cáo này không khẳng định trạng thái live hiện tại.
- Các bản backup được ghi nhận trong lịch sử năm Task:

| Mốc | Archive | Bằng chứng đã ghi |
|---|---|---|
| Trước Task 2 | `backend/storage/app/private/backups/tutorlink_pre_task2_20261022.dump` | 139.877 byte, mode `0600`, 348 TOC entries; file private bị git-ignore |
| Trước Task 3 | `/tmp/tutorlink_pre_task3_live_20261022.dump` | 140.926 byte, SHA-256 `1a509dd486a3a8f764602dbc7ec65ae105ff0d7dfcb21867eb08d9c4de399fba` |
| Trước Task 4 | `/tmp/tutorlink-live-pre-be-d01-task4-20261022.dump` | 369 TOC entries, SHA-256 `b61926ec5f89c847aaaf2113225da5de1afcbcceff5dd4f645b01d33481c535f`; xác minh lại và restore vào test ở Task 5 |

Không có migrate, seed, restore hay thay đổi schema/data trên `tutorlink` trong Task 5.
- Full backend suite trên SQLite ở Task 3: **99 passed, 8 skipped, 3 failed**. Hai lỗi là so sánh date-only/date-time ở age boundary; một lỗi `ExampleTest` thiếu bảng `sessions`. Các lỗi được ghi là ngoài schema delta Task 3, nhưng **chưa có bằng chứng đã được sửa hoặc chạy lại**. Reviewer cần xem trước khi merge.
- Trước PR/merge: review migration và báo cáo này, xử lý hoặc chấp nhận rõ ba lỗi regression còn ghi, tạo PR/commit theo quy trình, đồng bộ ERD Drive nếu cần, rồi cập nhật checklist/bằng chứng ngay trên Issue #36. Việc triển khai public slug lên live cần kế hoạch backup, rollback và phê duyệt riêng.

**Trạng thái bàn giao:** Hoàn thành phần nghiệm thu kỹ thuật database của BE-D01 theo dữ liệu hiện có; chờ PR và review độc lập để đóng Issue #36. Báo cáo này không phê duyệt thao tác nào trên database thật.
