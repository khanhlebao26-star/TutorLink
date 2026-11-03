# BE-K05 — Kiểm thử acceptance và bàn giao local

**Ngày:** 03/11/2026, Asia/Saigon  
**Owner:** Khanh · **Reviewer:** Hoàng · **Branch:** `be-k05`  
**Run ID:** `20261103-103218`  
**Kết quả:** kiểm thử ứng dụng và runtime Docker local đạt về chức năng; bàn giao kèm cảnh báo độ trễ health và các hạng mục Pending. Chưa commit, push, tạo PR hay cập nhật issue GitHub.

## 1. Phạm vi, source và môi trường

- Source được kiểm thử là working tree sau format, trên commit nền `71ef638`. Đây chưa phải commit chứa toàn bộ thay đổi. [Metadata và SHA-256 của 37 file code/config](evidence/be-k05.7/20261103-103218/source-metadata.json) ghi nhận chính xác input; đối chiếu cuối đợt không có file trong manifest thay đổi.
- Test tự động chạy trên PostgreSQL `tutorlink_test`, user `tutorlink_test_runner`, qua `compose.testing.yml` và TestDatabaseGuard. [Bằng chứng guard](evidence/be-k05.7/20261103-103218/test-database-guard.txt).
- Runtime kiểm tra thực tế: project `tutorlink-k05-prod`, PHP 8.3.36/FPM và Caddy 2.11.7, HTTPS nội bộ tại `https://localhost:8443`.
- Slack thật được kiểm tra trên backend development local, với namespace/cache marker riêng cho lần thử và queue smoke riêng. Không gửi từ production profile: cấu hình profile này hiện có `MONITORING_ALERTS_ENABLED=false`.
- [Image ID, restart policy và số restart ban đầu](evidence/be-k05.7/20261103-103218/runtime-images-restarts.txt), [tiến trình thực tế](evidence/be-k05.7/20261103-103218/runtime-processes.txt). PHP-FPM là bằng chứng runtime; các test HTTP kernel là bằng chứng kiểm thử ứng dụng.
- Metadata/test reports đã chọn nằm trong thư mục evidence; log thô, full coverage HTML và helper tạm nằm trong `outputs/be-k05.7/20261103-103218` hoặc `backend/build`, được Git ignore.

## 2. Format, full suite và coverage

Pint trên host với `--dirty` và Pint trong container với danh sách file BE-K05 đều trả `passed` trước khi chạy test. [Kết quả format](evidence/be-k05.7/20261103-103218/format-summary.json).

| Nhóm | Tests | Assertions | Failures / Errors / Skipped |
| --- | ---: | ---: | --- |
| Focused monitoring/health | 116 | 473 | 0 / 0 / 0 |
| D04 preflight | 7 | 27 | 0 / 0 / 0 |
| Regression ngoài D04 | 721 | 4.973 | 0 / 0 / 0 |
| D04 nghiệp vụ | 77 | 1.077 | 0 / 0 / 0 |
| D04 concurrency | 1 | 893 | 0 / 0 / 0 |
| **Tổng full suite** | **806** | **6.970** | **0 / 0 / 0** |

Full suite chạy qua `php scripts/test-suite.php --coverage`, exit code 0. Focused tests là tập con của full suite, không cộng thêm 116 vào tổng 806.

Coverage dòng toàn backend: **93,10% (8.200/8.808)**. Đây không phải chỉ số coverage riêng cho BE-K05 hoặc bằng chứng rằng mọi lỗi production đều đã được kiểm tra.

Bằng chứng: [tổng hợp](evidence/be-k05.7/20261103-103218/test-summary.json), [focused JUnit](evidence/be-k05.7/20261103-103218/focused-junit.xml), [từng focused case](evidence/be-k05.7/20261103-103218/focused-cases.json), [preflight](evidence/be-k05.7/20261103-103218/d04-preflight-junit.xml), [regression](evidence/be-k05.7/20261103-103218/regression-junit.xml), [D04](evidence/be-k05.7/20261103-103218/d04-junit.xml), [race](evidence/be-k05.7/20261103-103218/d04-race-junit.xml), [coverage](evidence/be-k05.7/20261103-103218/coverage.txt).

## 3. Ma trận acceptance

| Tiêu chí | Kết quả | Phương pháp và bằng chứng |
| --- | --- | --- |
| Guest bị chặn ở dashboard | PASS AUTO + LIVE | MonitoringTest; HTTPS thực tế trả 401 cho [Telescope](evidence/be-k05.7/20261103-103218/guest-telescope-production.json) và [Pulse](evidence/be-k05.7/20261103-103218/guest-pulse-production.json). |
| Customer/Tutor/Admin thiếu quyền bị chặn; Admin active hợp lệ được phép | PASS AUTO | MonitoringTest qua HTTP kernel/session, có đầy đủ role/status/permission matrix; JUnit và focused-cases. |
| Session bị thu hồi; Pulse đang mở bị thu hồi quyền | PASS AUTO | Request dashboard và POST Livewire với snapshot thật bị kiểm tra lại. Không phải automation JavaScript trong trình duyệt. |
| Dữ liệu mẫu được redact | PASS AUTO + LIVE SCAN | Test persist request/query/exception/Pulse entries và kiểm tra canary; [runtime scan](evidence/be-k05.7/20261103-103218/runtime-redaction-scan.json) trả 0 match trên các cột kiểm tra của sáu bảng telemetry, ở cả hai môi trường. |
| Sampling | PASS CONFIG + BOUNDARIES | Runtime rate 0.1; test ranh giới 0/1 và vẫn giữ failed request/exception/slow query. Chưa đo tỷ lệ thống kê của tập request lớn. |
| Pruning | PASS AUTO + SCHEDULE | Test tạo dữ liệu cũ/mới trên DB test, xác nhận xóa/giữ đúng. [Scheduler thực tế](evidence/be-k05.7/20261103-103218/runtime-schedule.txt) có monitoring:prune mỗi giờ; Telescope 24 giờ, Pulse 7 ngày theo code/config. Không thực hiện xóa dữ liệu runtime để lấy evidence. |
| Critical alert thật | PASS LIVE DEV | Ba sự kiện tạo một intent và một tin được Slack API chấp nhận, có alert_id. [Receipt](evidence/be-k05.7/20261103-103218/slack-delivery.json). |
| Slow query threshold | PASS AUTO + LIVE DEV | Bốn lần đầu chưa alert; lần thứ năm alert count=5, max_ms=608; tám sự kiện tổng vẫn một slow-query alert trong cooldown. |
| Cooldown | PASS AUTO + LIVE DEV | Test clock kiểm tra TTL 600 giây và hết hạn; bốn tiến trình critical cùng fingerprint tạo một job. Live smoke lặp trong cùng run không tạo thêm intent. |
| Slack thất bại/retry/chống vòng lặp | PASS AUTO | HTTP 503, 429, Retry-After, attempts=10, retry window; database worker thật với HTTP giả ghi nhận lỗi nội bộ không phát sinh alert đệ quy. |
| Heartbeat/outbox lag/failed jobs | PASS AUTO + LIVE | Test 30/90 giây, ranh giới outbox 300/301 giây, phân loại replay/failed, queue counts. Live outbox quá hạn được phát hiện. [Metric cuối](evidence/be-k05.7/20261103-103218/runtime-final-metrics.json). |
| Health khi worker không sẵn sàng | PASS LIVE | [Readiness 503](evidence/be-k05.7/20261103-103218/worker-stale-ready.json): app/db ok, riêng outbox stale. [Metric](evidence/be-k05.7/20261103-103218/runtime-during-worker-stop.json) ghi age=97 giây. [Sau phục hồi](evidence/be-k05.7/20261103-103218/worker-recovered-ready.json) ready. |
| Health khi DB dừng | PASS STATUS, WARN LATENCY | DB được giữ dừng đến khi request hoàn tất: [/up 200](evidence/be-k05.7/20261103-103218/db-down-up.json), [readiness 503](evidence/be-k05.7/20261103-103218/db-down-ready.json), DB down và workers unknown. [Thời gian](evidence/be-k05.7/20261103-103218/db-outage-observations.json): 15,77s và 30,82s. |
| Phục hồi và restart | PASS LIVE | [Sau DB phục hồi](evidence/be-k05.7/20261103-103218/db-recovered-ready.json) ready; [restart counts](evidence/be-k05.7/20261103-103218/runtime-final-restarts.txt) outbox=2, slack=3, broadcasts=3; tất cả trạng thái cuối đã phục hồi. |
| Runtime không dùng dev server làm chứng cứ | PASS LIVE | Danh sách tiến trình có PHP-FPM master/pool, queue:work và schedule:work; profile dùng restart unless-stopped, image no-dev. |
| Reverse proxy/TLS local | PASS LIVE | [HTTP 308](evidence/be-k05.7/20261103-103218/http-redirect.txt) đến https://localhost:8443/up; HTTPS gọi bằng CA nội bộ, xác minh CA/hostname. |
| CPU/RAM/disk/network host và cảnh báo tài nguyên | PENDING | Chưa chọn host/công cụ theo quyết định của nhóm. [Docker stats](evidence/be-k05.7/20261103-103218/container-stats.txt) chỉ là snapshot container, không chứng minh host threshold alert; block I/O không phải disk occupancy. |
| Backup/restore/rollback | DOCUMENT PRESENT; DR NOT VERIFIED | [Runbook](BE-K05.6-backup-restore-rollback-runbook.md) đã có. Task 7 chưa diễn tập phục hồi dữ liệu/rollback giữa hai image và chưa có evidence diễn tập được cung cấp; không đánh dấu PASS restore. |
| Hoàng review, PR, issue và remote CI | PENDING USER/REVIEWER | Đã chuẩn bị [nội dung PR](BE-K05-pr-description.md) và [cập nhật issue](BE-K05-issue-update.md). Chưa có tác động GitHub từ xa. |

Các kết quả AUTO nằm trong focused-cases/JUnit, không được mô tả thành kiểm tra thủ công trên account production. Ảnh giao diện cũ ở BE-K05.2 là evidence lịch sử; đợt này không chụp lại phiên trình duyệt.

## 4. Alert và privacy thực tế

[Slack receipt](evidence/be-k05.7/20261103-103218/slack-delivery.json) có:
- Critical: `c71ec0ee-d789-4759-a223-33d9b62f31d7`, API ok=true.
- Slow query: `a72baa24-a337-4356-859d-930cbabc38d6`, API ok=true, count=5, max_ms=608.
- Tổng hai tin; smoke queue không còn job pending hoặc failed.
- Payload được kiểm tra trước gửi và message không có synthetic canary, SQL hoặc token.

Smoke tái sử dụng helper Task 3: bắt intent bằng Bus fake để đếm chính xác, sau đó phát các job đã kiểm tra vào database queue riêng và chạy native worker gửi thật. Đây là bằng chứng collector + transport; không mô tả là regular slack-worker đã xử lý hai tin này hay người dùng đã đọc tin.

Critical log không kèm Throwable hiện dùng chung một fingerprint theo môi trường. Cooldown giới hạn tạo job; không phải bảo đảm exactly-once delivery khi kết quả gửi qua mạng không xác định. Đây là giới hạn hiện có của bộ phát hiện/transport, không được suy rộng từ hai receipt thành cam kết không bao giờ có tin trùng.

Runtime scan chỉ đếm hai chuỗi canary; không đọc/xuất nội dung bản ghi riêng tư. Số lượng bản ghi được lưu cùng kết quả để nhận biết bảng rỗng. Canary=0 không thay thế kiểm thử toàn bộ loại PII hoặc kiểm tra log ứng dụng thô.

## 5. Health và độ trễ cần xử lý

Trong phép thử DB-down hợp lệ, DB chỉ bật lại trong finally sau khi đã có hai phản hồi. Các thời gian dưới đây đo end-to-end bằng curl trên Windows, chưa tách riêng thời gian TLS/client và server. Kết quả trạng thái đúng, nhưng /up mất 15,77 giây và readiness 30,82 giây khi container DB không sẵn sàng.

Cần điều tra và giới hạn thời gian chờ kết nối/resolve dependency, đồng thời bảo đảm liveness ít phụ thuộc dependency trước khi cấu hình probe timeout ngắn trên hosting. Chưa xác định một nguyên nhân duy nhất và chưa thay đổi timeout trong Task 7.

Lượt thử đầu được bật lại DB trước khi request hoàn tất; các file `db-transition-*` chỉ ghi nhận quá trình chuyển trạng thái, không được dùng làm proof DB-down. Một request worker ban đầu chưa ghi nhận stale, được giữ tên `worker-within-timeout-ready.json`; proof stale là lượt sau.

Trạng thái cuối: app/DB healthy; outbox/slack/broadcast/scheduler có heartbeat ok; outbox pending=0, failed unresolved=0, database queue=0, failed_jobs=0. [Snapshot cuối](evidence/be-k05.7/20261103-103218/runtime-final-ps.txt).

## 6. OpenAPI và delta

Các kiểm tra backend contract của CI đã chạy local:
- Export spec bằng script có TestDatabaseGuard.
- Route parity **122 operations**, exit 0.
- Redocly CLI **2.60.0**, cùng config CI, exit 0.
- Export khác byte do CRLF/LF; sau chuẩn hóa newline thì giống spec hiện có, Git normalized diff exit 0. Không sửa `docs/api/openapi.json`.

[Chi tiết OpenAPI](evidence/be-k05.7/20261103-103218/openapi-checks.json): có **59 warning có sẵn trong spec hiện tại**:
- info-license: 1.
- no-illogical-composition-keywords: 2.
- no-server-example.com: 1.
- operation-4xx-response: 7.
- operation-summary: 48.

Delta BE-K05 so với nhánh main có trong checkout:
- Thêm route hệ thống `GET /health/ready` (200/503, app/db/workers); `/up` đã tồn tại và được dùng làm liveness.
- Thêm Telescope/Pulse cùng route dashboard/package, quyền `admin.monitoring.view`, kiểm tra session và active Admin.
- Hai migration tạo sáu bảng telemetry: telescope_entries, telescope_entries_tags, telescope_monitoring, pulse_entries, pulse_aggregates, pulse_values.
- Heartbeat dùng bảng cache hiện có; CLI mới `monitoring:runtime` và lịch heartbeat/prune.
- Job Slack thêm loại critical/slow_query với context allow-list, fingerprint và cooldown; không thêm cột nghiệp vụ.
- Dockerfile/Compose runtime PHP-FPM/Caddy, storage/TLS volumes, restart policy và template environment production.
- Điều chỉnh config Scramble để app boot được khi không cài dev dependency; cấu hình Reverb origins qua env.
- Không thêm route hay thay đổi schema response nghiệp vụ trong OpenAPI /api/v1.

Chưa chạy lại frontend lint/build vì không sửa frontend. Chưa có kết quả GitHub Actions cho lần bàn giao này; người dùng push rồi kiểm tra CI.

## 7. Giới hạn và công việc tiếp theo

1. Điều tra độ trễ health khi DB outage trước khi đặt probe timeout ngắn.
2. Chọn host/công cụ rồi hoàn thiện CPU/RAM/disk/network, thresholds và cảnh báo bên ngoài DB.
3. Bổ sung domain/DNS/TLS công khai và kiểm tra restart khi host reboot.
4. Bật/cấu hình và kiểm tra Slack trên deployment thật; production-profile local hiện đang tắt bộ phát hiện alert.
5. Diễn tập backup/restore/rollback theo runbook, lưu số liệu/checksum/image ID và thời gian RTO; xác nhận off-host storage, retention và RPO/RTO.
6. Xử lý hoặc chấp nhận 59 warning tài liệu API theo chính sách nhóm.
7. Người dùng review thay đổi, commit/push, tạo PR; Hoàng review và cập nhật issue. Không đánh dấu toàn bộ BE-K05 đã triển khai production.

Topology heartbeat hiện theo tên vai trò và môi trường, phù hợp một container cho mỗi vai trò; cần định danh instance nếu scale nhiều replica. Restart policy của Docker xử lý tiến trình thoát, không tự restart chỉ vì heartbeat stale hoặc health unhealthy.

## 8. Lệnh tái hiện chính

Format đã chạy trên danh sách file BE-K05, trước test. Test tập trung:

```cmd
docker compose -f compose.testing.yml run --rm --build -T backend-test php artisan test --compact --filter="ProductionHealthTest|MonitoringRuntimeTest|MonitoringAlertsTest|MonitoringTest|NotificationOutboxRuntimeTest|SendSlackOperationalAlertTest"
```

Full suite dùng script chuẩn bị schema/fixture tuần tự:

```cmd
docker compose -f compose.testing.yml run --rm --build -T -e TELESCOPE_ENABLED=false -e PULSE_ENABLED=false -e MONITORING_ALERTS_ENABLED=false -e MONITORING_ALERTS_SUPPRESSED=true backend-test php scripts/test-suite.php --coverage
```

Health HTTPS local:

```cmd
curl.exe --ssl-revoke-best-effort --cacert outputs\be-k05.5\caddy-root.crt -H "Accept: application/json" -i https://localhost:8443/up
curl.exe --ssl-revoke-best-effort --cacert outputs\be-k05.5\caddy-root.crt -i https://localhost:8443/health/ready
```

Evidence mới có thể được tạo bằng volume mount như hướng dẫn Task 7. Chạy các nhóm dùng cùng DB test tuần tự. Chỉ thử outage trên stack local được phép gián đoạn, luôn khởi động lại service trong finally.
