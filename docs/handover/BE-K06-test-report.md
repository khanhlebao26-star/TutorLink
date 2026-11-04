# BE-K06 — Task 1–4: môi trường, PostgreSQL tests và live smoke

Commit chứa test và công cụ smoke: 2ba90470e47629f218da85b99ef59b7361ee8a55

Ngày: 04/11/2026 (Asia/Ho_Chi_Minh). Branch: `be-k06`.
Issue: https://github.com/khanhlebao26-star/TutorLink/issues/85

## Kết quả

Task 1–4 đã thực hiện trên Docker local. Bộ backend cuối đạt **827 tests / 7.227 assertions, 0 failure/error/skip**, exit 0. Smoke API thật đạt 25 request checks và 14 assertions; notification được outbox-worker đang chạy xử lý. Một tin thử Slack thật được gửi qua database queue riêng và native Laravel worker, Slack trả HTTP 200 / `ok=true` cùng timestamp; permalink đã lấy thành công.

Chưa commit, push, tạo PR hoặc đăng bình luận GitHub theo yêu cầu của Khanh. Chưa hoàn tất review/Gate BE hoặc toàn bộ bàn giao BE-H06 chỉ bằng báo cáo Task 1–4 này.

Baseline: `fb54886617524e43727705f7f6ee86bed4135ef9`. Test bổ sung và công cụ smoke hiện là working tree chưa commit. [Manifest SHA-256](evidence/be-k06/20261104-151838/source-manifest.json) và [patch test](evidence/be-k06/20261104-151838/test-changes.patch) nhận diện đúng nội dung đã chạy. Sau khi Khanh commit, cần bổ sung SHA đó vào issue/PR; không mô tả working tree hiện tại là một commit mới đã có.

## Task 1 — Môi trường

- HEAD/main/origin/main cùng baseline sau fetch; 5/5 commit triển khai/bàn giao K01–K05 có trong ancestry origin/main. [Evidence ban đầu](evidence/be-k06/task1-20261104-1506/dependencies.json).
- User xác nhận channel Slack đang cấu hình là channel thử được nhóm cấp và đồng ý gửi tin BE-K06.
- PostgreSQL local `tutorlink`: 38 migrations Ran, 0 Pending; không reset/migrate/seed toàn bộ database local.
- Runtime backend/db healthy; Reverb, broadcast/outbox/Slack worker và scheduler running. Health cuối HTTP 200, app/db và bốn worker đều ok.
- Phát hiện runtime local cũ thiếu Filament và `ext-intl`, dù health trước đó xanh. Đã build lại backend/reverb/broadcast/worker bằng Dockerfile hiện tại, cài đúng `composer.lock` vào vendor volume rồi recreate/restart các tiến trình liên quan. Không thay đổi lock file, bỏ platform requirement hoặc thêm dependency mới.
- Cấu hình production không được khởi động hoặc thay đổi trong đợt này.
- Snapshot cuối: jobs pending=0, failed_jobs=0, outbox pending=0; đúng một message, một actual Session và một outbox đã xử lý trong bộ demo đạt. PostgreSQL test tạm đã dừng sau khi thu đủ evidence; runtime local tiếp tục chạy.

Evidence: [runtime cuối](evidence/be-k06/20261104-151838/runtime-final.txt), [migration](evidence/be-k06/20261104-151838/migrations-final.txt), [health](evidence/be-k06/20261104-151838/health-final.txt), [composer install cuối](evidence/be-k06/20261104-151838/runtime-composer-install-final.log), [extensions](evidence/be-k06/20261104-151838/php-extensions.txt).

## Task 2 — Regression/integration trên PostgreSQL

Test dùng project riêng `tutorlink-k06-test`, `compose.testing.yml`, PostgreSQL `tutorlink_test`, user `tutorlink_test_runner`. Script `backend/scripts/test-suite.php --coverage` kiểm tra identity, chuẩn bị legacy preflight, regression, fixtures D04 và race. Không chạy suite phá dữ liệu trên runtime backend hoặc database local `tutorlink`.

| Đợt chạy                 | Tests | Assertions | Failure / error / skip |
| ------------------------ | ----: | ---------: | ---------------------- |
| Baseline                 |   822 |      7.187 | 0 / 0 / 0              |
| Cuối, gồm 5 test bổ sung |   827 |      7.227 | 0 / 0 / 0              |

| Nhóm cuối                                                | Tests | Assertions |
| -------------------------------------------------------- | ----: | ---------: |
| Regression, gồm auth/file/chat/session/outbox/Slack fake |   742 |      5.230 |
| D04 migration preflight                                  |     7 |         27 |
| D04 integration                                          |    77 |      1.077 |
| D04 race harness                                         |     1 |        893 |

Không lấy số test harness D04 làm số trường hợp race riêng. Các race K03/K04/outbox có testcases trong nhóm regression và inventory.

Coverage toàn source `backend/app`: **93,41% (8.302/8.888)** → **93,58% (8.317/8.888)**. Mẫu số 8.888 không đổi; `backend/phpunit.xml`, source include, test runner và Compose test không bị sửa.

Coverage theo nhóm file: mapping regex và danh sách từng file được ghi minh bạch trong [summary JSON](evidence/be-k06/20261104-151838/test-coverage-summary.json). Đây là thống kê các nhóm file trong báo cáo từ Clover toàn backend, không phải thay filter source để nâng điểm; các file khác vẫn có trong coverage toàn source.

| Nhóm     | Baseline |   Cuối | Dòng cuối |
| -------- | -------: | -----: | --------: |
| Auth     |   97,28% | 97,28% |   322/331 |
| Files    |   92,12% | 92,12% |   526/571 |
| Chat     |   94,52% | 94,52% |   741/784 |
| Sessions |   88,94% | 90,50% |   867/958 |
| Outbox   |   91,62% | 91,62% |   547/597 |
| Slack    |   92,27% | 92,27% |   167/181 |

Evidence: [log cuối](evidence/be-k06/20261104-151838/final/full-suite.log), [exit 0](evidence/be-k06/20261104-151838/final/exit-code.txt), [coverage text](evidence/be-k06/20261104-151838/final/coverage/coverage.txt), [Clover](evidence/be-k06/20261104-151838/final/coverage/clover.xml), [case inventory](evidence/be-k06/20261104-151838/final/case-inventory.csv), [danh sách test lỗi, hiện rỗng](evidence/be-k06/20261104-151838/final/failed-tests.csv).

HTML coverage và serialized coverage trung gian được giữ ở `outputs/be-k06/20261104-151838/{baseline,final}/coverage/` (Git ignored), tránh đưa hàng nghìn file sinh tự động vào commit.

## Task 3 — Gap và test bổ sung

Đối chiếu source với test hiện có cho thấy các guard sau chưa được kiểm trực tiếp; bộ baseline cũng chưa chạy qua các dòng guard này. Đã thêm 5 case vào hai file Session hiện có:

1. PATCH thay nội dung khi version đã bằng 2.147.483.647: 409 `session_version_exhausted`, không đổi Session/audit/receipt/actual/outbox.
2. PATCH không đổi nội dung ở version tối đa: vẫn 200, không tăng version hoặc tạo audit, chỉ lưu receipt hợp lệ.
3. Complete ở version tối đa: 409, không ghi một phần.
4. Cancel ở version tối đa: 409, không ghi một phần.
5. Scheduled Session đã có actual: 409 `session_actual_conflict`, không ghi đè actual hoặc tạo receipt/audit mới.

File: `backend/tests/Feature/SessionApiTest.php`, `backend/tests/Feature/SessionTransitionApiTest.php`.
Focused check: **44 tests / 317 assertions**, exit 0. Sau đó chạy lại toàn suite với hai file test được bind read-only vào image test baseline. Source ứng dụng không thay đổi. Pint trên dirty files và danh sách PHP thay đổi đều passed.

| Tình huống acceptance                                             | Evidence test hiện có được chạy lại                                                              |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Race tạo chat/gửi tin/read cursor                                 | `OpenConversationConcurrencyTest`, `SendTextMessageConcurrencyTest`, `ReadCursorConcurrencyTest` |
| Race complete/cancel/edit Session và Program                      | `SessionConcurrencyTest`                                                                         |
| Auth/session revocation, role và cross-user                       | `IdentitySecurityTest`, `IdentitySessionTest`, `PersonalAccessTokenTest`, Session/chat API tests |
| Outbox rollback, SKIP LOCKED, worker chết, retry/replay           | `NotificationOutboxWriterTest`, `ProcessNotificationOutboxTest`, `NotificationOutboxRuntimeTest` |
| Slack timeout, 429/Retry-After, permanent error, retry exhaustion | `SendSlackOperationalAlertTest` — HTTP fake, không phải Slack thật                               |

Không phát hiện lỗi source ứng dụng chặn lane trong các đợt test đã chạy. Lỗi runtime cũ đã xử lý như Task 1. Một lần smoke dừng vì công cụ so sánh mảng PHP theo thứ tự key; đã sửa phép so sánh JSON object, vì PostgreSQL JSONB có thể đổi thứ tự key. Đây là sửa observer, không phải lỗi replay nghiệp vụ; giữ lại evidence lần dừng để phân biệt.

## Task 4 — Smoke API thật, notification và Slack

Công cụ tái chạy: `backend/scripts/be-k06-smoke.php`.
Script chỉ cho phép APP_ENV local, PostgreSQL thật `current_database()=tutorlink`. Kiểm tra guard trong môi trường test đã từ chối trước khi tạo fixtures, exit 1 đúng kỳ vọng.

Đợt đạt: `be-k06-20261104-082755-qxs2`.
Fixtures: Tutor 89, Customer 90, outsider 91, Program 2, Conversation 7, Session 2. Password sinh ngẫu nhiên chỉ giữ trong bộ nhớ; không lưu vào evidence.

- Fixtures chủ động đặt verified accounts, approved identity metadata và active Program để cô lập lane; không tuyên bố smoke registration/approval/download identity file.
- 25 request checks qua HTTP thật tới backend đang chạy, có cookie jar riêng và CSRF bootstrap/header cho từng actor; không dùng `actingAs` cho smoke.
- Login/me của ba actor, chat create/send/read, same-key replay và changed-payload conflict, outsider 404.
- Session create/read, outsider 404, Customer mutate 403, stale version 409, complete và replay cùng kết quả, đúng một actual.
- Session không phát notification ngoài contract. Pause Program phát `program_paused`; regular outbox-worker xử lý event `793530eb-08aa-423b-9670-6b5beadd62fe`, `event_key=program:2:pause:35`; đúng một notification cho recipient.
- Logout rồi truy cập chat trả 401.
- 14 assertions kiểm DB/identity/replay/outbox/Slack đều đạt.

Slack thật: dùng job ứng dụng `SendSlackOperationalAlert`, cảnh báo tổng hợp synthetic `outbox_pending` với marker `[TEST_BE-K06]`; môi trường label chỉ đổi trong process smoke, không đổi `.env`. Queue riêng `k06-smoke-ac3p3evz`, native `queue:work --once` chạy job. Đây không phải bằng chứng regular `slack-worker` đã tiêu thụ queue `slack`.

- Alert ID: `a8515d9d-90a9-4837-a9f1-b25309354459`.
- Slack API: HTTP 200, `ok=true`, timestamp `1793780886.888319`; marker có trong message trả về; queue đã rỗng, không failed job của queue smoke.
- [Tin Slack thật](https://laraveltelesc-cjr9660.slack.com/archives/C0C8BM660TB/p1793780886888319), permalink API trả `ok=true`.
- Không chụp hoặc xác nhận người dùng đã đọc trong UI Slack; evidence xác nhận message API được Slack chấp nhận và có permalink thật.
- Lỗi metadata `conversations.info: missing_scope` ở Task 1 không chặn gửi thật; không mở rộng quyền token.

Evidence: [smoke JSON](evidence/be-k06/20261104-151838/smoke.json), [exit 0](evidence/be-k06/20261104-151838/smoke-exit-code.txt), [Slack permalink](evidence/be-k06/20261104-151838/slack-permalink.json), [guard](evidence/be-k06/20261104-151838/smoke-test-environment-guard.json).

## API, payload và event delta

Không đổi endpoint, schema hoặc event contract. Auth dùng session cookie và CSRF; Chat gửi `peer_user_id`, rồi `client_message_id` UUID/text/body. Session create có `Idempotency-Key` UUID; complete có `expected_version` và key riêng. `program_paused` ghi outbox trong transaction, notification chống trùng theo recipient/type/event_key. Slack operational alert là đường riêng, không suy diễn mọi notification đều được gửi Slack.

Payload, method/path và assertions tái hiện nằm trong công cụ smoke; contract nghiệp vụ tiếp tục dùng tài liệu K02/K03/K04 và OpenAPI hiện hành.

## Giới hạn và việc tiếp theo

- Kết quả local/PostgreSQL, không chứng nhận production, performance/load hay UI/realtime WebSocket trong đợt K06 này.
- Coverage là line coverage, không chứng minh đã vét cạn mọi interleaving hoặc nhánh lỗi.
- Dữ liệu demo của lần thành công và lần observer dừng được giữ có prefix `be-k06-*` để đối chiếu; không xóa dữ liệu ứng dụng. Mỗi lần chạy smoke tạo một bộ mới.
- Script cần dev dependencies để tái sử dụng fixture helpers. Không dùng trong production hoặc thay cho flow đăng ký/duyệt tài khoản.
- Chưa xác minh regular Slack queue end-to-end ở Task 4; có test fake của worker, real native worker với queue riêng và runtime heartbeat riêng biệt như đã ghi.
- Khanh tự commit/push, bổ sung SHA cuối và đăng nội dung cập nhật issue; Hoàng review. Không đóng toàn issue trước khi bàn giao BE-H06 và review.
- Không còn blocker được quan sát trong phạm vi các kiểm tra đã chạy.
