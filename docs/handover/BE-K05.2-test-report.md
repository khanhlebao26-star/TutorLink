# BE-K05.2 — Cấu hình giám sát ứng dụng và bảo vệ dữ liệu

**Ngày:** 02/11/2026 (Asia/Ho_Chi_Minh)  
**Owner:** Khanh · **Reviewer:** Hoàng · **Branch:** `be-k05`  
**Kết quả:** PASS trên local/test; sẵn sàng review. Chưa commit/push hoặc triển khai production.

## 1. Phạm vi và môi trường

- PHP 8.3 trong Docker, Laravel 13.35.0, Telescope 5.27.0, Pulse 1.8.1 và Livewire 4.4.8.
- Test tự động chạy trên PostgreSQL `tutorlink_test`, host `test-db`, qua `TestDatabaseGuard` kiểm cấu hình và connected identity. Không đổi tài khoản/session hoặc chạy reset/pruning trên database ứng dụng để kiểm thử.
- Kiểm tra runtime local gồm đọc lịch scheduler, cấu hình sampling, danh sách route và số bản ghi chứa canary. Quét canary chỉ trả số lượng, không xuất nội dung riêng tư.
- Các thay đổi được kiểm tra là working tree; base commit và dependency versions nằm trong [source metadata](evidence/be-k05.2/source-metadata.json).

## 2. Cấu hình đã triển khai

| Hạng mục | Cấu hình cuối |
| --- | --- |
| Dashboard | `/telescope`, `/pulse`; dùng session `web` |
| Quyền | Admin active, chưa soft-delete và có `admin.monitoring.view` |
| Session | Tái sử dụng kiểm tra session bị thu hồi của BE-K02/K03 |
| Sampling request thông thường | `0.1` trong Telescope |
| Query chậm | Từ 500 ms; lưu 100%, chỉ giữ fingerprint và metadata |
| Request chậm trong Pulse | Từ 1.000 ms; sample rate 1 |
| Exception / request HTTP 5xx | Giữ exception và request 5xx trong Telescope khi ordinary sampling bằng 0 |
| Watchers Telescope | Request, Query, Exception; các loại khác bị loại khỏi filter |
| Recorders Pulse | Slow Queries, Slow Requests, Exceptions; bỏ dữ liệu định danh người dùng |
| Retention Telescope | 24 giờ |
| Retention Pulse | 7 ngày |
| Pruning | `monitoring:prune`, mỗi giờ, `withoutOverlapping(10)` |
| Cache serialization | Chỉ cho phép Collection, stdClass, CarbonImmutable phục vụ dashboard Pulse |

Telescope loại headers, cookies/session, request payload, response body/headers, thông tin user và IP. URI được thay bằng route template. SQL/bindings và exception message/context/trace/source preview không được lưu nguyên bản. Tags định danh `Auth:*` được loại bỏ.

Đã gỡ route local `/__monitoring/probe` và import không còn sử dụng. Runtime xác nhận [không còn route probe](evidence/be-k05.2/runtime-probe-removed.txt).

## 3. Lỗi phát hiện và sửa trong quá trình kiểm thử

### Cache Pulse không khôi phục được Collection

`cache.serializable_classes=false` làm object trong kết quả cache thành incomplete object. Cấu hình hiện cho phép đúng ba lớp cần thiết. Test render cả ba thẻ Pulse hai lần qua database cache, bao gồm Collection chứa stdClass và CarbonImmutable, đã đạt.

### Livewire không dừng khi middleware trả JSON 401/403

Test snapshot Pulse phát hiện trường hợp tài khoản bị suspend hoặc session bị thu hồi vẫn trả 200. `EnsureActiveApiActor` trả JSON khi từ chối; cơ chế persistent middleware của Livewire chỉ tự dừng với exception/redirect, nên response này chưa ngăn xử lý component.

Đã thêm `EnsureMonitoringActor`, tái sử dụng kiểm tra actor/session và ném `HttpResponseException` khi response từ chối. Hai dashboard dùng middleware này. Middleware dùng chung của các API nghiệp vụ giữ nguyên hành vi. Các test request Livewire tiếp theo đã đạt: bỏ quyền → 403; suspend → 403; thu hồi session → 401.

## 4. Kết quả kiểm thử tập trung

**27 tests / 118 assertions, không failure/error/warning.**

Nguồn chạy: `backend/tests/Feature/MonitoringTest.php`, dùng `MonitoringTestCase` bật Telescope/Pulse trước khi ứng dụng boot rồi khôi phục biến môi trường sau boot. Database guard vẫn hoạt động. Test không phụ thuộc route probe đã gỡ.

| Kiểm tra | Kết quả |
| --- | --- |
| Guest tại Telescope, API dữ liệu Telescope và Pulse, kể cả `app.env=local` | 401 |
| Customer tại ba endpoint trên | 403 |
| Admin active có quyền, dùng session đăng nhập thật trong HTTP test | 200 |
| Gate matrix: Admin có/không có quyền, suspended/deleted Admin, Customer/Tutor có permission giả | Đúng cả ba gate |
| Session Admin bị thu hồi tại ba endpoint | 401 |
| Snapshot Livewire đã được dùng thành công, sau đó bỏ quyền / suspend / revoke session | 403 / 403 / 401 |
| Headers, cookie, nested request payload, response, SQL binding và exception có canary | Không lộ canary trong dữ liệu lưu |
| Request lưu route template, bỏ user và tags định danh | Đạt |
| Ordinary sampling = 0 / 1 | Không ghi / ghi request thông thường |
| Ordinary sampling = 0 nhưng có request 5xx, exception và slow-query event | Vẫn ghi dữ liệu quan trọng |
| Pulse render thẻ Slow Queries / Slow Requests / Exceptions hai lần qua database cache | Đạt |
| Telescope: bản ghi 25 giờ bị xóa, 23 giờ còn lại | Đạt |
| Pulse entries/values/aggregates: bản ghi 8 ngày bị xóa, 6 ngày còn lại | Đạt |
| Lịch pruning mỗi giờ và tránh chạy chồng | Đạt |

Metric trong test tự động sử dụng sự kiện thời gian có kiểm soát để kiểm tra pipeline recorder/filter/storage. Bằng chứng độ trễ truy vấn/request thực tế đến từ route probe local mà người dùng đã chạy trước khi gỡ.

- [Log test tập trung](evidence/be-k05.2/focused-tests.txt)
- [JUnit test tập trung](evidence/be-k05.2/focused-junit.xml)
- [Format trước kiểm thử và xác nhận cuối](evidence/be-k05.2/format.txt)

## 5. Bằng chứng runtime local

| Bằng chứng | Kết quả |
| --- | --- |
| [Ảnh Pulse do người dùng cung cấp](evidence/be-k05.2/pulse-runtime-user.png) | Slow query 618 ms; request probe 1.394 ms; RuntimeException 1 lần; query hiển thị fingerprint |
| [Ảnh guest bị chặn do người dùng cung cấp](evidence/be-k05.2/guest-denied-user.png) | JSON `unauthenticated`; người dùng cũng cung cấp kết quả curl 401 cho hai dashboard |
| [Quét canary trên các bảng giám sát local](evidence/be-k05.2/runtime-canary-scan.txt) | 0 ở telescope_entries, telescope_entries_tags, pulse_entries, pulse_aggregates, pulse_values |
| [Cấu hình runtime](evidence/be-k05.2/runtime-monitoring-config.txt) | Sampling 0.1; slow query 500 ms |
| [Lịch runtime](evidence/be-k05.2/runtime-schedule.txt) | `0 * * * * monitoring:prune` |
| [Danh sách route probe](evidence/be-k05.2/runtime-probe-removed.txt) | `[]` |

Ảnh runtime và kết quả curl là bằng chứng người dùng cung cấp; các log tự động, truy vấn chỉ đọc và kiểm tra cấu hình được thực hiện trong lượt bàn giao này. Không lưu cookie/token từ output curl vào báo cáo.

## 6. Regression toàn backend

Chạy theo quy trình chuẩn bị schema/fixture BE-D04 tuần tự trong `scripts/test-suite.php`:

| Nhóm | Tests | Assertions | Failures / errors / skipped |
| --- | ---: | ---: | --- |
| D04 migration preflight trên schema legacy | 7 | 27 | 0 / 0 / 0 |
| Regression nền, bao gồm 27 test giám sát mới | 672 | 4.830 | 0 / 0 / 0 |
| D04 nghiệp vụ | 77 | 1.077 | 0 / 0 / 0 |
| D04 PostgreSQL concurrency | 1 | 893 | 0 / 0 / 0 |
| **Tổng** | **757** | **6.827** | **0 / 0 / 0** |

Coverage dòng tổng hợp: **92,96% (7.928 / 8.528 dòng)**. Đây là coverage toàn backend; không phải riêng module giám sát.

- [Log regression đầy đủ](evidence/be-k05.2/full-regression.txt)
- [Tổng hợp JUnit](evidence/be-k05.2/regression-summary.json)
- [Coverage summary](evidence/be-k05.2/coverage-summary.txt)
- JUnit: [preflight](evidence/be-k05.2/d04-preflight-junit.xml), [regression](evidence/be-k05.2/regression-junit.xml), [D04](evidence/be-k05.2/d04-junit.xml), [race](evidence/be-k05.2/d04-race-junit.xml).

## 7. Cách chạy lại

Chạy từ thư mục gốc TutorLink. Format trước test:

```cmd
docker compose exec backend php vendor/bin/pint app/Http/Middleware/EnsureMonitoringActor.php app/Providers/MonitoringServiceProvider.php app/Support/MonitoringPrivacy.php app/Support/AdminPermissions.php bootstrap/providers.php config/cache.php config/monitoring.php config/pulse.php config/telescope.php routes/console.php routes/web.php database/migrations tests/Feature/MonitoringTest.php tests/Support/MonitoringTestCase.php --format agent
```

Test tập trung:

```cmd
docker compose -f compose.testing.yml run --rm --build -T backend-test php vendor/bin/pest tests/Feature/MonitoringTest.php --compact --display-warnings --fail-on-warning
```

Regression toàn bộ và coverage:

```cmd
docker compose -f compose.testing.yml run --rm --build -T backend-test
```

## 8. Delta, giới hạn và bàn giao

- Không thêm hoặc sửa API nghiệp vụ `/api/v1`. Bổ sung route dashboard/package và quyền `admin.monitoring.view`; quyền mới không tự cấp cho các Admin đang tồn tại.
- Hai migration tạo sáu bảng telemetry: `telescope_entries`, `telescope_entries_tags`, `telescope_monitoring`, `pulse_entries`, `pulse_aggregates`, `pulse_values`. Không đổi schema bảng nghiệp vụ.
- Dữ liệu giám sát chỉ có metadata/fingerprint; không thể dùng dashboard để đọc SQL/bindings hoặc nội dung lỗi gốc.
- Sampling 0.1 được xác nhận ở cấu hình runtime. Test kiểm tra ranh giới 0/1 và giữ dữ liệu quan trọng; không đưa ra kết luận thống kê từ một tập request nhỏ.
- Test Livewire chạy request qua HTTP kernel với snapshot thật; không bổ sung automation JavaScript trong trình duyệt. UI local có bằng chứng do người dùng cung cấp.
- Phạm vi kiểm tra là local/test. Backend local dùng dev server, không được dùng làm bằng chứng triển khai production.
- Hosting/công cụ metric host chưa được nhóm chốt; theo dõi ở BE-K05.4/BE-K05.5. Không chặn kết quả giám sát ứng dụng ở BE-K05.2.
- Chưa kiểm thử alert Slack critical/slow-query, cooldown mới hoặc tải production; các phần đó thuộc task tiếp theo.

**Trạng thái đề xuất trong issue:** BE-K05.2 hoàn thành triển khai và kiểm thử local/test, chờ Hoàng review. Đính kèm báo cáo và evidence; bước tiếp theo là BE-K05.3. Commit/PR và cập nhật issue sẽ thực hiện khi bàn giao thay đổi.
