# Benchmark Laravel origin

Bộ đo dùng Caddy và image PHP-FPM production của repo, PostgreSQL 16 và một worker outbox. Compose tạo mạng nội bộ, database, storage và tài khoản dùng riêng cho phép thử. Các cổng API/FPM chỉ mở trong mạng benchmark. Dữ liệu là fixtures tổng hợp. Tải từ Next.js, CDN, file tĩnh, Reverb, mail và Slack nằm ngoài phạm vi lượt đo.

## Chạy cục bộ

Cần Docker Desktop hoặc Docker Engine với Compose V2, Node.js và OpenSSL trên máy điều khiển. Chạy lần lượt từ thư mục gốc của repo.

```sh
docker/performance/run.sh up
docker/performance/run.sh measure smoke mixed 10 25
docker/performance/run.sh measure read25 read 25 45
docker/performance/run.sh measure mix50 mixed 50 45
docker/performance/run.sh measure write10 write 10 30
PERF_SEARCH_PROFILE=diverse docker/performance/run.sh measure diverse50 mixed 50 45
PERF_SPIKE_RPS=500 docker/performance/run.sh measure spike500 mixed 100 50
docker/performance/run.sh down
```

- `up` tạo một bộ fixture mới gồm 100.000 listing và 20 cặp Tutor/Customer. Có thể đặt `PERF_LISTINGS` từ 100 đến 1.000.000 trước `up`. Với ít hơn 44.000 listing, yêu cầu trang 2.200 có thể trả trang trống hợp lệ. `down` xóa volume của project benchmark và giữ báo cáo. Dùng `down` trước lần seed tiếp theo.
- Mỗi `RUN_NAME` là một thư mục mới. Chạy các phép thử tuần tự vì bộ đếm SQL được đặt lại đầu mỗi lượt. Giữ nguyên image, dữ liệu, cấu hình, thứ tự làm nóng cache và nguồn phát tải khi so sánh.
- `read` gồm 75% đọc public và 25% đọc xác thực. `mixed` gồm 75% đọc public, 20% đọc xác thực và 5% tạo Session. `write` gồm 35% đọc public, 30% đọc xác thực và 35% tạo Session. Các cookie Sanctum thuộc tài khoản tổng hợp với role và Program riêng. Bootstrap cookie và tạo thử replay chạy trước cửa sổ đo.
- `PERF_SEARCH_PROFILE=diverse` thay đổi bộ lọc giá ở các lượt tìm kiếm để tăng tỷ lệ cache miss. Hồ sơ mặc định `repeated` lặp lại một tập nhỏ bộ lọc. `PERF_SPIKE_RPS` tăng tải trong một cửa sổ 10 giây giữa lượt, sau đó trở về tải gốc. Cần thời lượng ít nhất 30 giây và chia hết cho 10. Cả hai lựa chọn giữ nguyên tỷ trọng endpoint.
- Exit `0` khi đạt ngưỡng lượt chạy và đối chiếu dữ liệu. Exit `1` khi setup hoặc đối chiếu dữ liệu có lỗi. Exit `2` khi vượt giới hạn tải, lỗi hoặc latency. Báo cáo nhanh yêu cầu phát đủ lượt, thành công ≥99% tải cấp, lỗi tổng hợp dưới 1% và ngưỡng p95/p99 300/1.000 ms. Kiểm tra thêm p95/p99 cho mỗi endpoint đọc, xem số liệu ghi riêng.

## Đọc kết quả

- `http.json` chứa số lượt dự kiến, lượt bỏ, mã HTTP, lỗi gửi/nhận, p50/p95/p99, thời gian thực tế gồm drain, cache HIT/MISS, độ trễ bộ phát và hàng đợi FPM. Mẫu FPM nhanh chụp trạng thái tại từng thời điểm. `peak_waiting` và `max_children_reached` là bộ đếm tích lũy từ lúc FPM khởi động.
- `postgres.ndjson` chứa mẫu kết nối, lock, deadlock, WAL, hàng đợi và tuổi sự kiện outbox cũ nhất. `queries.json` chứa thống kê truy vấn sắp theo tổng thời gian cùng fingerprint, số lần gọi, dữ liệu đọc và WAL. `resources.ndjson` chứa mẫu Docker CPU, RAM, I/O đĩa và mạng. Block I/O và network I/O của Docker là số tích lũy. Lấy chênh lệch hai mẫu theo thời gian khi cần tốc độ.
- `integrity.json` đối chiếu số Session thực tế, slot và tiêu đề duy nhất, audit create và biên nhận idempotency. Một request timeout có thể đã commit. Chênh lệch số dòng phải được đối chiếu với kết quả replay và log trước khi kết luận mất hoặc nhân đôi dữ liệu.

## So sánh cache response PostgreSQL và Redis

Dùng một bộ fixture mới và export `PERF_REDIS_EXPERIMENT=1` cho cả hai biến thể. Cả hai chạy cùng image benchmark có PhpRedis và một Redis 64 MB trong mạng nội bộ. Khởi động mặc định với cache PostgreSQL. Việc đổi store chỉ tạo lại container API. Generation và expiry watermark tiếp tục đọc từ PostgreSQL mỗi request, và session, queue cùng cache khác chọn store mặc định.

```sh
export PERF_REDIS_EXPERIMENT=1
export PERF_STATE_DIR="$PWD/outputs/performance-audit/redis-ab"
docker/performance/run.sh up
docker/performance/run.sh measure pg160 mixed 160 30
docker/performance/run.sh cache-store redis
docker/performance/run.sh measure redis160 mixed 160 30
docker/performance/run.sh down
```

Ghi nhận mức làm nóng của mỗi lượt. `cache-store.txt` xác nhận biến thể, `cache-sql.json` đếm truy vấn PostgreSQL vào bảng cache và generation, còn `redis.txt` ghi bộ đếm Redis tích lũy. Tính chênh lệch mẫu trong từng lượt khi so sánh. Build PhpRedis và Redis Compose được dùng cho benchmark. [Laravel Redis](https://laravel.com/docs/13.x/redis) · [Redis eviction](https://redis.io/docs/latest/develop/reference/eviction/)

## Kiểm tra hai bản sao Laravel sau Caddy

Cả hai app dùng chung `APP_KEY`, PostgreSQL, session, cache store, queue và volume thử cho tệp private. Caddy luân phiên các request qua hai FPM upstream. Header `X-Benchmark-Upstream` chỉ nằm trong proxy thử và cho biết bản sao thực sự xử lý phản hồi. Lệnh đổi số bản sao tạo lại proxy thử. Chạy các lệnh tuần tự giữa hai lượt đo.

```sh
export PERF_STATE_DIR="$PWD/outputs/performance-audit/replicas"
docker/performance/run.sh up
docker/performance/run.sh measure one160 mixed 160 30
docker/performance/run.sh replicas 2
docker/performance/run.sh measure two160 mixed 160 30
docker/performance/run.sh replicas 1
docker/performance/run.sh down
```

`upstreams.txt` ghi các FPM upstream đã cấu hình. `http.json` đếm phản hồi thành công theo từng upstream và lấy mẫu FPM riêng. Smoke xác nhận cùng session đọc đúng Program qua mọi upstream và hai POST đồng thời dùng chung khóa idempotency trả cùng ID. PostgreSQL đối chiếu Session, slot, audit và receipt. Để so sánh hiệu năng, giữ cùng kiểu cache, dữ liệu và thời lượng làm ấm cho từng lượt.

Tại staging nhiều host, đặt ingress HTTP trước các proxy ở từng app host, giới hạn cổng FastCGI trong mạng từng host và kiểm health của từng backend. API dùng chung secrets và PostgreSQL. Kiểm quyền đọc file private và signed file download sau khi cấu hình storage dùng chung. Dùng job migration chạy một lần trước rollout, kiểm khả năng đọc schema trong lúc chuyển phiên bản và tách health API khỏi cảnh báo worker. [Caddy load balancing](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy#load-balancing)

## Giám sát bằng Prometheus

Phần mở rộng dùng mạng benchmark hiện có. Caddy xuất histogram ingress, collector đọc trạng thái từng FPM upstream và PostgreSQL. Tài khoản collector dùng một mật khẩu sinh riêng, role thống kê và quyền SELECT trên các cột dùng để đếm queue. Tên route theo URI, cookie, user ID và payload công việc nằm ngoài nhãn metric. `http.json` của bộ phát tải vẫn cung cấp số liệu theo endpoint.

```sh
export PERF_STATE_DIR="$PWD/outputs/performance-audit/monitoring"
docker/performance/run.sh up
docker/performance/monitoring/run.sh up
docker/performance/run.sh measure baseline mixed 25 20
docker/performance/monitoring/run.sh status
docker/performance/monitoring/run.sh down
docker/performance/run.sh down
```

- `status` trả trạng thái alert và một số metric dưới dạng JSON. Nếu đổi số replica, chạy lại `monitoring/run.sh up` để collector theo cấu hình upstream mới.
- Cảnh báo thử dùng cửa sổ 30 giây, scrape mỗi 5 giây và chờ 10 giây trước khi báo. Có thể thử tải quá mức trong Docker, xem `status` khi tải đang chạy, rồi kiểm alert biến mất sau khi hồi phục. Phép tính p95 ingress xấp xỉ từ histogram và tỷ lệ 5xx tính trên phản hồi qua Caddy. Timeout và lượt chưa phát vẫn nằm trong báo cáo của máy phát tải.
- Kiểm tra trước staging gồm chọn cửa sổ đủ dài và ngưỡng theo loại API, giữ metrics sau khi restart, xác thực người đọc metrics và kiểm nhận cảnh báo qua kênh vận hành. Lưu bằng chứng trước khi chạy `down`. [Caddy metrics](https://caddyserver.com/docs/metrics) · [Prometheus alerting rules](https://prometheus.io/docs/prometheus/latest/configuration/alerting_rules/)

## Kiểm tra điều kiện Phase E

```sh
node docker/performance/phase-e.mjs outputs/performance-audit/phase-e/hold100-10m
node docker/performance/phase-e.mjs outputs/performance-audit/phase-e/staging-mixed --require-target
```

`--require-target` trả mã 2 nếu chưa đạt một trong các điều kiện định lượng. Bộ kiểm tra yêu cầu ít nhất 60 phút tải liên tục có ghi nghiệp vụ, ít nhất 1.000 phản hồi thành công mỗi giây tính trên toàn lượt và từng cửa sổ hoàn tất 60 giây, lỗi tổng hợp dưới 1% và đúng ngưỡng p95/p99 theo endpoint đọc. Công suất tính từ lúc phản hồi đã nhận xong. Vì vậy request còn chờ ở mốc phút cuối được tính vào số liệu toàn lượt khi drain, nhưng nằm ngoài cửa sổ hoàn tất đã định. Cần thêm đánh giá độ ổn định tài nguyên và mức đại diện của workload trước khi nghiệm thu.

`writes.ndjson` ghi lần thử POST và ID trả về theo idempotency key. `session-rows.ndjson` ghi Session cùng khóa receipt tương ứng từ PostgreSQL. Harness so sánh khóa, ID, Program, tiêu đề và khung giờ; `integrity.json` kiểm thêm audit và biên nhận. Nếu có commit mà client thiếu ACK, `phase-e.mjs` ghi chính xác các lần thử đó vào `pending-replay.ndjson`. Gửi lại đúng body và khóa bằng `replay-receipts.mjs`, lưu kết quả thành `receipt-replays.ndjson`, rồi chụp lại SQL sau khi replay. Kết quả cũ trước khi bổ sung ledger đọc ở `http.json` và `integrity.json` của từng lượt.

Trong Docker, `run.sh measure` chờ PHP-FPM xử lý hết hàng đợi sau khi máy phát tải kết thúc rồi mới chụp dữ liệu kiểm chứng. Khoảng chờ này nằm ngoài phép đo HTTP. Dùng `docker/performance/run.sh replay RUN_NAME` trong cùng môi trường để gửi lại các khóa cần xác nhận và cập nhật `phase-e.json`. Trên staging, xác nhận máy phát đã drain và app đã xử lý hết công việc của lượt rồi mới lấy snapshot PostgreSQL.

## Nghiệm thu trên staging cô lập

Chạy cùng source và cấu hình dự kiến triển khai trên môi trường thử có dữ liệu tổng hợp hoặc được phép sử dụng. Dùng máy phát tải riêng và fixture Sanctum được tạo cho staging. Tách Laravel origin, Next HTML và CDN thành các lượt đo. `load.mjs` nhận `PERF_ORIGIN`, `PERF_FRONTEND_ORIGIN`, `PERF_FIXTURES`, `PERF_MAX_PENDING` và yêu cầu `PERF_TARGET_ACK=isolated-staging` với origin ngoài mạng Compose. Giữ cookie/khóa trong kho bí mật và đối chiếu SQL ở máy quản trị DB. Phải có sự cho phép trước khi phát tải đến môi trường chia sẻ.

1. Smoke cả đọc/ghi và gửi song song cùng Idempotency-Key. So khớp response, Session, audit và receipt. Bổ sung fixture Login, chat, Admin và payment theo workload thực tế, gồm hạn mức đăng nhập và tình huống tranh cùng bản ghi.
2. Tăng bậc 50, 100, 250, 500 và 1.000 RPS. Giữ từng bậc đủ lâu để quan sát CPU, hàng đợi, cache và connection pool. Giữ lại kết quả các bậc vượt ngưỡng.
3. Sau khi bậc 1.000 ổn định, giữ ít nhất 60 phút. Cấp dư nhỏ để đạt ≥1.000 response thành công/giây sau khi tính lỗi và drain. Kiểm tra theo cửa sổ, error rate dưới 1%, p95 <300 ms và p99 <1.000 ms theo endpoint thông thường. Chốt mục tiêu riêng cho ghi nghiệp vụ trước khi chạy. Khi phát tải ở máy riêng, đặt `PERF_WRITE_LEDGER` trỏ đến `writes.ndjson`, ghi stdout của `load.mjs` vào `http.json`, và dùng `integrity.sql` cùng `session-rows.sql` với tham số `run_id` từ lượt chạy để lấy dữ liệu đối chiếu.
4. Đo spike 250 → 2.000 → 250 RPS và thời gian phục hồi. Thử stress vượt 1.000 RPS và tăng dần tới 5.000–10.000 sau khi các bậc thấp qua ngưỡng. Chạy soak 4–8 giờ ở tải chấp nhận, kiểm tra xu hướng RAM, kết nối, đĩa, queue và failed jobs.
5. Theo dõi ingress RPS/4xx/5xx/latency theo route, FPM listen queue và active children, PostgreSQL connections/locks/WAL/slow queries, Redis ops/hit/evictions nếu có, CPU/RAM/disk/network và tuổi công việc chờ. Kiểm tra `php artisan monitoring:runtime` cho heartbeat và outbox. Đặt alert khi error rate ≥1%, p95 API ≥300 ms kéo dài, pool DB ≥80% hoặc oldest outbox >60 giây kéo dài. Ghi rõ nơi lấy số liệu và độ dài cửa sổ khi duyệt kết quả.
