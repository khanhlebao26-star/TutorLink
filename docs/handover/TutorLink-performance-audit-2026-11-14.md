# TutorLink · Kiểm toán hiệu năng và khả năng mở rộng

Ngày 14/11/2026 · baseline commit `49d14e5` · nhánh `codex/2w-fe-integration`

## Phase A · Hiện trạng đã kiểm tra

- **Kiến trúc.** Next.js 16.4.5 App Router gọi trực tiếp API Laravel 13.35 qua Axios. Laravel phục vụ API và có khu vực admin Filament/Blade dùng chung action/policy. Docker dev dùng `artisan serve` và `next dev`. Compose production dùng Caddy → PHP 8.3 FPM → PostgreSQL 16, có Reverb, worker outbox, hai worker database queue và scheduler. Next.js chưa có service trong Compose production. Caddy đã nén gzip/zstd. Image production đã có OPcache và entrypoint cache config, route, view. Nginx, Octane, PgBouncer, CDN và Redis runtime không có trong Compose hiện tại.
- **Backend.** Route groups dùng Sanctum stateful/CSRF và các middleware kiểm tra session, trạng thái, role/ability theo từng nhánh. PAT chỉ dùng trên nhóm hỗ trợ token, có cập nhật `last_used_at`. Cookie session thông qua PostgreSQL, có đọc user, kiểm revocation và ghi session ở cuối request. PHP-FPM dùng cấu hình image, kiểm trong benchmark thấy 5 child, `pm.max_requests=0`. Các object response marketplace có eager loading và page limit, test hiện có kiểm số SQL không tăng theo số item cho marketplace và admin profile. Admin dashboard tự tổng hợp nhiều bảng, inbox dùng cursor và count tương quan, Listing dùng offset và total. Chưa có profiling từng service, PHP allocation hay bằng chứng để đổi application server.
- **Dữ liệu và nhất quán.** Invitation/Program/Session/Task/Fee có transaction, khóa theo entity, version hoặc idempotency ở các đường ghi đã kiểm tra. FeeRecord là ghi nhận học phí và trạng thái đánh dấu, chưa thấy API thanh toán qua cổng, wallet, earnings ledger hoặc booking marketplace riêng trong route/schema. Kiểm riêng trùng Session trong phạm vi rule hiện hành và replay khi tải đồng thời. Bảo toàn quyền và thứ tự khóa. Migrations có khóa chính, foreign key, unique và composite indexes, trong đó có index marketplace theo bộ lọc và index expiry dạng partial. Khóa toàn bảng, lock theo row, index trùng/ít dùng và deadlock ở quy mô thật cần đối chiếu catalog/statistics trên staging. Không suy index dư chỉ từ tiền tố trùng nhau.
- **Cache.** Store mặc định trên PostgreSQL. `MarketplaceCacheControl` chỉ cache JSON 200 của allow-list public với request không mang Authorization/Cookie. TTL 60 giây. Trước mỗi lookup, đọc generation và expiry watermark. Trigger statement-level cập nhật generation trong cùng transaction. Không thấy single-flight, giới hạn cardinality theo bộ lọc hoặc warming. Vary theo Cookie/Authorization và buộc revalidate. Giữ invalidate ngay sau commit và đúng thời điểm hết hạn tài liệu. Redis có cấu hình Laravel; Compose production thiếu Redis và Dockerfile chưa cài phpredis.
- **Queue và quan sát.** Outbox ghi cùng transaction nghiệp vụ và worker dùng `FOR UPDATE SKIP LOCKED`, savepoint, retry và notification event key. Worker ngủ 2 giây giữa batch, mỗi event một transaction. Slack đi database queue, có timeout/retry, Reverb broadcast có queue riêng. Xác minh/quên mật khẩu còn gọi luồng gửi mail đồng bộ. Pulse/Telescope lọc dữ liệu và sample request mặc định 10%, /up và /health/ready có sẵn. Readiness hiện gộp heartbeat worker với DB, healthcheck container chỉ kiểm cổng FPM. Slack alert tắt mặc định trong ví dụ production. Chưa có chuỗi tổng hợp CPU/RAM, DB wait, Redis, outbox lag và SLO ingress theo route ở Compose. Nên export metrics ít cardinality, tránh định danh và payload. Tách API readiness khỏi tình trạng dịch vụ nền khi thiết kế LB.
- **Frontend.** Build production thành công. Toàn bộ route được liệt kê là dynamic vì root layout đọc cookie session hint. Route public có HTML khung, nhiều dữ liệu lấy sau hydration. Đã có `next/image`, chunks theo route, một số dynamic imports và hook polling có abort/backoff trong chat. Chưa thấy `next/font`, ISR, cache data dùng chung hoặc RUM. Các điểm phát sinh request chính là hai khối featured, review/profile enrichment, nhóm request sau hydrate, poll badge 5 giây, CSRF bootstrap trên mutation và workspace kéo toàn bộ trang. Marketplace lọc/phân trang qua API, một số biểu mẫu đã debounce. Chưa đo LCP/INP/CLS, cache CDN, heap browser và độ trễ mobile thật.

## 10 bottleneck theo thứ tự xử lý

| Component | Current Status | Bottleneck và ảnh hưởng | Severity | Recommended Solution |
|---|---|---|---|---|
| 1 · PHP-FPM / Caddy | Caddy + FPM, 5 child trong runtime đo | Các route cùng tăng p95 lên khoảng 3,4 giây khi tải 100 RPS; hàng chờ chung, chưa quy được toàn bộ delay cho FPM | Critical | Đo listen queue, active/idle, max children, CPU/RSS. Thử 5/10/20 child theo CPU và ngân sách DB, đo A/B bật/tắt Telescope; tách máy phát tải |
| 2 · PostgreSQL search | Hai query chính cho một search miss | 247 query COUNT và 247 query result đứng đầu tổng thời gian thực thi SQL thu thập được; LIKE chứa chuỗi, eligibility joins, sort và OFFSET | High | EXPLAIN hai câu độc lập trên term phổ biến/hiếm, page sâu và price sort; giữ total contract, thử pg_trgm hoặc query shape sau khi có plan |
| 3 · Marketplace cache | Truy vấn DB cả khi HIT | Generation, expiry và đọc payload vẫn qua PostgreSQL; key tự do tăng MISS và TTL đồng loạt tạo tải | High | Gộp generation/expiry vào một snapshot SQL, đo lại. Sau đó thử Redis cho payload, kiểm TTL, single-flight, giới hạn admission và fail-open về controller |
| 4 · PostgreSQL write contention | Mọi trigger trên 14 bảng ghi cùng row generation | Có thể serialize các giao dịch ghi không cùng user/program; chưa có tải ghi đủ để lượng hóa | Critical | Dùng pg_locks/pg_stat_activity và benchmark writes; thu hẹp invalidation theo dependency chỉ khi test suspend/moderation/rollback/expiry qua nhiều instance |
| 5 · Next.js public rendering | Root đọc cookie, featured có enrichment riêng | HTML động; mỗi lượt mở trang có thêm listing, profile và review. Feed/related/favorites có fan-out tương tự | High | Dedup call public trùng, đo request/view. Tách public shell và auth island. Chỉ ISR cho nội dung có cơ chế hết hạn/invalidation đáp ứng ẩn tức thì |
| 6 · Authenticated API / Next.js | DB sessions + badge poll mỗi 5 giây | 5.000 tab đang mở tạo khoảng 1.000 RPS chỉ từ badge trước traffic chat; unread lấy từ query inbox | High | Dùng single-flight, backoff, abort; đo query badge riêng và triển khai signal realtime có polling fallback. Giữ kiểm tra revocation và role |
| 7 · PostgreSQL sessions / connections | Database cache, sessions, queue, telemetry cùng primary | Kết nối ngắn lặp lại và session read/write theo GET đã xuất hiện trong thống kê | High | Tách cache/rate limiter có điều kiện, kiểm PgBouncer transaction mode với PDO và transaction semantics. Không đổi sang Redis session trước khi sửa dependency revocation |
| 8 · Outbox / integrations | Một worker, mỗi event một transaction, pace 2 giây | Backlog có thể tăng nhanh hơn drain khi write-heavy; email đồng bộ giữ child | High | Đo oldest-event age và drain rate; scale worker theo event rate; giữ event key, retry/dead letter, retry-safe email sau commit; staging gián đoạn worker/Slack |
| 9 · Next.js private workspace | Dashboard tải nhiều trang song song và tập dữ liệu lớn vào browser | Traffic tăng theo lịch sử, có burst cho mỗi lần mở Progress/Tasks; admin dashboard aggregate riêng | High | Dùng lọc/phân trang và summary DTO hiện có hoặc endpoint bounded; đo request/page, bytes, heap; quyền luôn do Laravel xác thực |
| 10 · Docker / phân tán | Một proxy/app/DB, local volume, thiếu resource budgets | Tắc nghẽn và lỗi theo host; replica khác host không đọc chung file; monitoring đồng bộ làm tăng tải | Critical | LB, shared private object storage sau kiểm truy cập, API/Next replicas độc lập, queue/metrics riêng, resource budgets, readiness và restore drill |

### Baseline HTTP cục bộ

Source `49d14e5`, PHP-FPM 8.3.36, Caddy HTTP nội bộ, PostgreSQL 16 với dữ liệu trên tmpfs. Docker Desktop dành 4 vCPU và 3,83 GiB cho tất cả container. Cùng máy chạy bộ phát tải Node. Dataset tổng hợp 20 tutor, 5.000 listing, 100 customer/program và 20 phiên riêng. Mail array và broadcast null. Code và vendor của lượt chính nằm trong Docker volume, Telescope/Pulse bật. Vì vendor dev có Scramble, `config:cache` báo object security scheme không serialize được. Image production cài `--no-dev` nên giới hạn này thuộc harness hiện tại; dữ liệu đo chưa thay thế benchmark image production hoàn chỉnh.

| Workload | Cấp tải | Hoàn thành | Lỗi HTTP | Lượt không phát | p50 / p95 / p99 |
|---|---:|---:|---:|---:|---:|
| 80% public read, 20% authenticated read | 100 RPS × 25 s | 86,35 RPS · 2161 HTTP 200 | 0/2161 | 339/2500 (13,56%) | 732 / 3387 / 3560 ms |
| Cùng workload, source bind-mount trên macOS | 100 RPS × 25 s | 76,67 RPS · 2057 HTTP 200 | 0/2057 | 443/2500 (17,72%) | 2098 / 2845 / 2935 ms |

Tỷ lệ thực hiện thành công trên lượt dự kiến ở lần đo chính là 86,44%. Throughput được chia theo thời gian thực tế gồm drain. Mọi request được đo là Laravel với PostgreSQL. Public đạt 69,05 RPS, authenticated đạt 17,31 RPS. Cookie login/CSRF bootstrap để chuẩn bị fixture được thực hiện ngoài cửa sổ đo. Ghi nghiệp vụ, CDN, JS/CSS, Next HTML, tải end-to-end và sức chịu tải bền vững cần các lượt riêng. GET có session vẫn ghi session, telemetry cũng ghi PostgreSQL. Search có 195 HIT và 235 MISS. Trong 10 mẫu mọi container dùng chung VM, PHP-FPM đạt đỉnh mẫu 219% CPU / 75,47 MiB; PostgreSQL 116% / khoảng 149 MiB; Caddy 25% / khoảng 52 MiB. Mẫu DB thấy tối đa 2 kết nối ứng dụng tại lúc lấy mẫu và 0 lock wait; độ phân giải thấp, không đại diện peak. 247 COUNT mất tổng 2591ms, 247 query kết quả mất 2098ms. 207 insert Telescope được quan sát. Không ghi nhận deadlock trong DB tổng hợp. Chưa đánh giá I/O lưu trữ thật, Redis ops hoặc tốc độ drain worker.

| Endpoint Laravel | Hạng mục | RPS thành công | p95 ms | p99 ms |
|---|---|---:|---:|---:|
| GET /listings | Public read | 35,08 | 3374 | 3560 |
| GET /listings?q=… | Public search | 17,18 | 3391 | 3556 |
| GET /tutors/{slug} | Public detail | 12,51 | 3388 | 3550 |
| GET /catalog/categories | Public catalog | 4,28 | 3366 | 3550 |
| GET /programs | SPA auth read | 8,59 | 3455 | 3571 |
| GET /conversations/unread-count | SPA auth read | 4,40 | 3378 | 3562 |
| GET /notifications | SPA auth read | 4,32 | 3394 | 3522 |

### Kiểm tra frontend ban đầu

- Next production build thành công. Manifest có 4 root JS files tổng 449.581 B, khoảng 133.380 B gzip cục bộ. Route chunks, ảnh, CSS và RSC thêm vào số này. Đây là kích thước file build dùng chung, chưa phải transfer đo từ trình duyệt.
- Vitest 56 pass / 8 fail, trong App Shell session menu, Workspace surfaces, Collaboration UI retry. Kết quả có trước thay đổi hiệu năng. Cần phân biệt expectation UI đã cũ với lỗi runtime bằng browser regression.

## Kiến trúc và các bước tiếp theo

1. **Phase B · Laravel và PostgreSQL.** Sửa theo query đo được, chạy test marketplace/cache, đo hot và cold riêng bằng cùng fixture; EXPLAIN với BUFFERS cho search COUNT và data, plan trên volume lớn hơn và term phân bố thực. Kiểm session/authorization/realtime trước đổi store, thử PhpRedis riêng cho cache và limiter, giữ outbox cùng transaction. Theo dõi hit/eviction/retry. Đo bottleneck FPM trước cân nhắc Octane; audit mutable singleton, timezone/request/guard/log context trên worker sống lâu. [Laravel Redis](https://laravel.com/framework/docs/redis) · [PostgreSQL EXPLAIN](https://www.postgresql.org/docs/16/sql-explain.html)
2. **Phase C · Offload Next.js.** Homepage và trang marketing dùng SSG cho shell. Listing public và tutor detail cần ẩn đúng tức thì khi moderation/session policy, chỉ đưa vào ISR/CDN sau khi invalidation được kiểm ở nhiều replica hoặc giới hạn cache cho dữ liệu không nhạy về độ tươi. Search tương tác client và phân trang server. Dashboard hybrid/CSR, dữ liệu Program/Session/Admin do Laravel xác thực với `private, no-store`. Đưa hashed bundles, ảnh và font tĩnh lên CDN. Đo API/view, TTFB, FCP, mobile/desktop LCP ≤2,5s, INP ≤200ms, CLS ≤0,1 ở p75, long tasks, heap và bytes. [Next.js self-hosting](https://nextjs.org/docs/app/guides/self-hosting)
3. **Phase D · Hạ tầng modular monolith.** CDN/WAF → LB → Next.js replicas và Laravel API replicas, Caddy ở ingress hoặc trước FPM. PostgreSQL primary với backup/PITR, PgBouncer khi có số liệu connection churn, read replica khi xác định rõ route chấp nhận lag. Reverb/queue/worker scale độc lập. Phiên dùng DB chung và key/cookie domain nhất quán. File private chuyển storage chia sẻ khi đã kiểm signed access và quyết định revoke. Shared Redis cache có prefix môi trường; job durability được kiểm riêng khỏi cache eviction. Kiểm graceful drain, rollback deploy và failover trước chuyển traffic. Tách liveness, DB readiness và worker alerts.
4. **Phase E · Benchmark staging cô lập.** 100k–1M listing/actors có lịch sử phân bố lệch, fixtures Program/Session/chat/notifications/admin. Arrival rate mở: smoke 10 RPS, ramp 50→100→250→500→1.000, hold 1.000 ≥60 phút, spike 250→2.000, stress bậc 5.000 rồi 10.000 sau khi qua ngưỡng trước, soak 4–8 giờ ở tải chấp nhận. Đề xuất read-heavy 75% public read, 20% authenticated read, 5% write/login; write-heavy 35/30/35. Phát login theo nhiều tài khoản/IP kiểm rate-limit thật, dùng nhiều bộ dữ liệu độc lập cho Program/Session/Fee và thêm luồng tranh cùng record. Tính 2xx RPS, 4xx/5xx/timeouts/dropped, p50/p95/p99 theo method+route+lane. So khớp event, khóa, idempotency, replay và số row cuối lượt. Tiêu chí 1.000 RPS ổn định, lỗi <1%, p95 <300 ms và p99 <1.000 ms cho API thường, SLA theo thao tác write, không duplicate/loss. Tách static/CDN, FE HTML, Laravel origin và SQL/Redis khỏi tổng, lấy mẫu tài nguyên cả load generator. [k6 arrival-rate](https://grafana.com/docs/k6/latest/using-k6/scenarios/executors/constant-arrival-rate/) · [k6 dropped iterations](https://grafana.com/docs/k6/latest/using-k6/scenarios/concepts/dropped-iterations/)
5. **Phase F · Vận hành.** Prometheus/Grafana hoặc nền tảng tương đương. Ingress latency/error/request rate theo route, FPM queue/max children/RSS, CPU memory I/O/network, DB connections/locks/deadlocks/WAL/slow query fingerprints, Redis ops/hit/eviction, queue depth/oldest age/failed jobs. Alert khởi điểm: API errors ≥1%/5 phút với đủ traffic, p95 ≥300 ms/10 phút, DB pool ≥80% hoặc oldest outbox >60 giây/5 phút; điều chỉnh sau baseline. Pulse dùng sample có giới hạn và Telescope theo đợt chẩn đoán, DB credentials/exporter quyền đọc tối thiểu. [PostgreSQL pg_stat_statements](https://www.postgresql.org/docs/16/pgstatstatements.html)

### Ngân sách thử nghiệm

Ước tính lập ngân sách cho máy cloud vùng Singapore, USD/tháng, giả định tự vận hành và chưa gồm thuế, egress, backup, WAF/CDN và chi phí nhân sự. Đây là các cấu hình để thử tải. Công suất từng node cần đo độc lập trước khi tính số replica.

- Baseline 1.000 RPS: 2–4 app nodes × 4–8 vCPU, DB 8–16 vCPU / 32–64 GB, Next x2, Redis, worker, LB và load generator riêng. Dự trù 400–1.200 USD/tháng.
- Stretch 5.000 RPS: 6–12 app nodes, primary 16–32 vCPU / 64–128 GB, storage NVMe, Redis và worker riêng, thêm read replica khi có bằng chứng. Dự trù 1.200–4.000 USD/tháng.
- Stretch 10.000 RPS: 12–24 app nodes, primary 24–48 vCPU / 96–192 GB, HA/failover, nhiều worker và máy phát tải. Dự trù 2.500–8.000 USD/tháng.
- Tính lại với measured steady-state: `ceil(target origin RPS / RPS mỗi node tại SLO × 1,3)`, kiểm riêng giới hạn DB/Redis/queue. Ví dụ payload truyền 5 KB × 1.000/s × 30 ngày ≈ 13 TB outbound nếu duy trì 24/7. [Bảng sản phẩm Singapore](https://www.hetzner.com/cloud-singapore/)

## Triển khai và kiểm chứng sau Phase A

### Phase B · Một snapshot SQL cho khóa cache marketplace

`backend/app/Support/PublicCatalogCache.php` đọc generation và watermark expiry bằng một statement PostgreSQL. Key vẫn chứa cả hai giá trị. Test sẵn có kiểm rollback, ẩn ngay sau commit từ kết nối khác, hết hạn giấy tờ, bypass với request riêng tư và fallback khi cache lỗi. Trong microbenchmark HTTP catalog, `pg_stat_statements` cho thấy 1.050 lần đọc generation + 1.050 lần đọc expiry trước tối ưu, sau đó 1.050 lần đọc hợp nhất. Con số gồm 60 warmup và 990 request trong cửa sổ tải. Hai lượt cùng 45 RPS cấp có 990/990 HTTP 200. p50 gần như tương đương, 21,93 → 21,60 ms. p95 của hai lượt là 566 → 135 ms, p99 là 908 → 272 ms. Biến động CPU/hàng chờ cục bộ có thể ảnh hưởng chênh lệch latency. Số truy vấn khóa cache là kết quả có thể kiểm trực tiếp.

`EXPLAIN (ANALYZE, BUFFERS)` trên 5.000 listing cho thấy COUNT quét tuần tự bảng với term hiếm, term phổ biến, trang sâu và price sort. Kết quả query lấy dữ liệu theo term phổ biến trang đầu dùng backward scan của PK (~0,28 ms). Trang 180 thực hiện scan/sort (~11,49 ms), còn COUNT ~5,28 ms. Term hiếm có COUNT ~5,26 ms và query data ~4,41 ms. Dataset có 20 tutor, mọi listing synthetic nên đây là tín hiệu để thử query/index trên phân bố staging, cần thêm dữ liệu trước khi sửa migration.

### Phase C · Gộp request đang chạy ở trình duyệt

`frontend/src/lib/data/marketplace.ts` chia sẻ pending promise cho danh mục, danh sách ứng viên featured và review public cùng slug/trang. Hai component featured dùng chung loader. Request đã hoàn tất hoặc lỗi được giải phóng để lần sau tải mới. Bản build Chrome có cùng ba tutor/service từ API giả lập, số GET trên homepage giảm 14 → 9 (~36%). Catalog 2 → 1, Listing 2 → 1, review 6 → 3. Ba profile GET và một kiểm tra phiên vẫn xuất hiện. Lựa chọn này đo được request/view với rủi ro thấp. Chưa đánh giá Core Web Vitals hoặc hiệu quả cache SSR/CDN từ fixture giả lập.

`frontend/src/tests/shared/marketplace-pending-reads.test.ts` kiểm gộp request, refresh sau thành công, phân biệt slug/trang và retry sau lỗi.

### Phase D–E · So sánh HTTP trước và sau

| Lượt đo | Cấp tải | RPS thành công | p50 / p95 / p99 ms | Lỗi HTTP / timeout | Lượt không phát |
|---|---:|---:|---:|---:|---:|
| Baseline mixed read | 100 × 25 s | 86,35 | 732 / 3387 / 3560 | 0 / 0 | 339 |
| Sau vá mixed read · lượt riêng | 100 × 25 s | 99,89 | 320 / 1288 / 1410 | 0 / 0 | 0 |
| Sau vá mixed read · tải thấp hơn | 50 × 25 s | 49,99 | 48 / 992 / 1126 | 0 / 0 | 0 |
| Sau vá mixed Favorite write smoke | 15 × 20 s | 15,03 | 77 / 1516 / 1680 | 0 / 0 | 0 |
| Baseline catalog HIT | 45 × 22 s | 44,95 | 22 / 566 / 908 | 0 / 0 | 0 |
| Sau vá catalog HIT | 45 × 22 s | 45,00 | 22 / 135 / 272 | 0 / 0 | 0 |

Ở lượt mixed 100 sau vá, public 80% đạt khoảng 79,91 RPS và private 20% đạt khoảng 19,98 RPS. Lượt mixed chạy đồng thời với Pest trên cùng VM có 60,83 RPS thành công, 99 timeout và 711 lượt không phát. Lượt đó không được dùng làm cặp so sánh do chia sẻ tài nguyên với test. Cỡ mẫu và số lần lặp hiện tại giới hạn suy luận về mức cải thiện p95 hệ thống. Tất cả các lượt HTTP trực tiếp qua Caddy đến Laravel. Browser/CDN/Next SSR không nằm trong throughput này.

Write smoke gồm 30% public read, 30% authenticated read và 40% POST/DELETE Favorite từ nhiều session thật. 120 thao tác ghi thành công, tương ứng ~6,01 RPS, và kiểm dữ liệu cuối lượt thấy 2 dòng với 2 cặp khóa duy nhất. Đây là smoke cho ghi idempotent nhẹ. Program/Invitation/Session/Fee chịu tải khác và có bộ kiểm tra đồng thời riêng.

### Kiểm thử và phạm vi triển khai

- Frontend production build, typecheck và ESLint phần thay đổi đạt. Vitest chạy với hai worker đạt 58/66 và giữ 8 lỗi đã có ở baseline. Một lượt chạy nhiều worker đồng thời với Docker còn có ba timeout 5 giây, không lặp lại khi giới hạn worker.
- Backend Pint đạt. Nhóm marketplace, Invitation và Session trên PostgreSQL đạt 57/59 với 749 assertion. Hai test Session không quan sát được blocker được kỳ vọng trước deadline trong lượt nhóm. Chạy riêng Session sau khi dừng tải đạt 17/17 với 329 assertions. Một test tổng hợp D04 có 893 assertions đạt sau khi nạp fixture đúng, bao gồm replay và cạnh tranh trên Fee/Program. Các kiểm tra trên phạm vi tổng hợp chưa đủ để chứng nhận tính nhất quán dưới tải production.
- Thay đổi nằm trong 4 tệp ứng dụng và 1 tệp test frontend. Không đổi route/API contract, dependency, schema, session driver hoặc quy tắc ghi nghiệp vụ. Cấu hình FPM và monitoring chỉ thử tại Docker benchmark. Triển khai/rollback production, Redis, dashboard metrics, load balancer và soak test staging vẫn cần hoàn thành trước nghiệm thu.
- Evidence JSON, thống kê tài nguyên và execution plans cục bộ nằm ở `outputs/performance-audit/`. Bộ phát tải cục bộ dùng Node với lịch arrival-rate, tải từ Docker network riêng, khởi tạo cookie trước measurement và bảo toàn thống kê timeout/dropped. Dataset và key được tạo riêng cho môi trường thử.

### Thử thay đổi cấu hình FPM và cấp tải 1.000 RPS

Chỉ ở môi trường benchmark, thử 10 child, recycle sau 1.000 request và tắt Telescope, giữ Pulse. Hai biến FPM/Telescope đổi cùng lúc nên phép đo này đánh giá cấu hình kết hợp. Ở mức cấp 100 RPS trong 30 giây, cấu hình đó cho 80,66 RPS thành công, 116 timeout, bỏ 461/3.000 lượt và p95 4,85 giây. Kết quả kém hơn lượt 5 child đã đo, nên cấu hình thử không được đưa vào code deploy.

Ở mức cấp 1.000 RPS trong 15 giây, đo được 98,21 RPS thành công, p95 3,69 giây, p99 3,74 giây. 1.675/15.000 lượt trả 200, 13.325 lượt không phát do giới hạn đồng thời/hàng chờ bộ phát tải cùng máy. Đây là stress test ngắn nhằm chỉ ra saturation cục bộ. Năng lực tối đa tuyệt đối của cụm production và khả năng chạy 1.000 RPS liên tục vẫn cần đo trên máy riêng với đầy đủ telemetry FPM và DB. Redis/CDN không tham gia vào các lượt đo.

Các tệp `load.mjs`, `load-writes.mjs`, `seed.php` và `frontend-requests.cjs` ở thư mục evidence ghi lại workload. Harness dùng một Docker network riêng có alias `caddy`, `api`, `test-db`, checkout backend ở `/bench` khi seed và thư mục dùng chung `/fixtures`. Trước khi seed, chạy migration vào PostgreSQL tổng hợp `tutorlink_test`, bật `pg_stat_statements` và thiết lập Sanctum stateful cho `frontend.test`. Sau khi seed, ví dụ lệnh từ container Node là `node /fixtures/load.mjs mixed 100 25`. Dùng `load-writes.mjs writes 15 20` cho Favorite smoke. Tệp dữ liệu phiên/tài khoản phát sinh khi chạy harness được giữ riêng và loại khỏi evidence.

## Vòng tiếp theo · PostgreSQL 100.000 listing và đo hàng đợi FPM

### Kết quả đo trực tiếp

Đo trên cùng Docker Desktop 4 vCPU / 3,83 GiB, PHP-FPM 5 child, PostgreSQL 16 và 100.000 listing tổng hợp. Listing có nhóm từ khóa phổ biến, chọn lọc, khớp mô tả và không khớp. Actor và dữ liệu vẫn tổng hợp, PostgreSQL dùng tmpfs. Bộ phát tải chạy trong container riêng trên cùng máy. PHP-FPM xuất trạng thái qua listener riêng ở mạng Docker, lấy mẫu mỗi 500 ms. Cổng trạng thái không publish ra host. Chỉ cấu hình nhóm benchmark dùng listener này. [PHP-FPM status](https://www.php.net/manual/en/fpm.status.php)

- **Tìm kiếm 25 RPS cấp trong 24 giây, Telescope bật ở cả hai lượt.** Trước index, 308/600 request trả 200, 292 timeout và p95 5.011 ms. Có index, 600/600 trả 200, p95 934 ms, p99 1.224 ms và thông lượng thành công 24,91 RPS. Backlog FPM tối đa 210 → 23, cả hai lượt đều chạm 5 child. Mỗi lượt có 60 request lặp do cách warmup; 540 lượt còn lại dùng khóa cache mới. Workload có cả từ khóa rất rộng và page 2.200.
- **Tìm kiếm chọn lọc, 20 RPS cấp trong 18 giây, cache lạnh.** Dùng hai cụm từ chọn lọc và một cụm không khớp, với khóa riêng cho warmup và phần đo. Trước index, 177/360 trả 200, 183 timeout, p95 5.007 ms và backlog đỉnh 121. Có index, 360/360 trả 200, 20,01 RPS thành công, p95 95 ms và p99 158 ms. FPM không ghi nhận backlog. Đây là kết quả cho nhóm query chọn lọc.
- **Tắt Telescope trong một lượt riêng, giữ index và 5 child.** Vẫn 600/600 HTTP 200. p95 1.342 ms, backlog đỉnh 40. Lượt đơn này không chứng minh tắt Telescope giảm latency và không dẫn tới đổi cấu hình deploy.
- **Đọc hỗn hợp trong 3 phút, cấp tải 15 RPS.** Telescope tắt, Pulse bật, index có mặt, 80% public và 20% xác thực qua cookie thật. 2.699/2.700 HTTP 200, 1 lượt không phát, 14,98 RPS thành công. p50/p95/p99 toàn nhóm 28/282/850 ms. Riêng tìm kiếm p95 **378 ms** và p99 **1.182 ms**, vượt cả hai ngưỡng. FPM chạm 5 child, backlog đỉnh 12. Trong 18 mẫu tài nguyên lấy giữa lượt chạy, FPM khoảng 64 MiB, PostgreSQL khoảng 267 MiB. Max client connections quan sát được là 2, số mẫu phát hiện DB đang chờ lock là 0. Đây là tải đọc ngắn cục bộ, chưa phải endurance 60 phút hay chứng nhận không có memory leak.

### Migration đã kiểm chứng

`backend/database/migrations/2026_11_14_154208_add_active_listing_text_search_index.php` dùng GIN đa cột với `pg_trgm` cho `title` và `description` của listing active/VND. Query vẫn dùng cùng điều kiện `ILIKE`, kiểm quyền hiển thị, thứ tự và exact total. Không thêm endpoint hay đổi hợp đồng phân trang. PostgreSQL dùng bitmap index scan cho các truy vấn chọn lọc và dùng scan tuần tự khi từ khóa khớp phần lớn dữ liệu. [PostgreSQL pg_trgm](https://www.postgresql.org/docs/16/pgtrgm.html)

- `EXPLAIN (ANALYZE, BUFFERS)` trên cùng dữ liệu ghi nhận COUNT từ khóa hiếm **47,69 → 0,71 ms**, từ khóa không khớp **41,15 → 0,16 ms**, và từ khóa `English` khoảng 11.940 kết quả **113,00 → 23,10 ms**. `Mathematics` khớp khoảng 83.080 row, page 2.200, vẫn dùng scan và mất trên 100 ms cho cả COUNT và data. Không thêm B-tree cho page sâu nếu chưa có kế hoạch query và phân bố staging phù hợp.
- Index chiếm **11 MB** so với bảng 24 MB trên dataset này. Một lệnh chèn 1.000 listing trong transaction rollback tăng **29,2 → 52,5 ms**, WAL khoảng **0,55 → 1,78 MB**. Đây là chi phí cho ghi listing tổng hợp, chưa đại diện traffic ghi thật. Các bảng Session/Fee không dùng index này.
- Đã kiểm `migrate → rollback → migrate`. Rollback bỏ index và giữ đủ 100.000 listing. Lần chạy lại xác nhận index valid. Pest marketplace/schema **43/43, 233 assertions** và Pint đạt.
- Khi áp dụng staging, xác nhận gói extension có sẵn và tài khoản migration có quyền cài `pg_trgm`, hoặc cài extension trước qua quản trị PostgreSQL. `CREATE INDEX CONCURRENTLY` chạy ngoài transaction, giảm thời gian chặn ghi nhưng thêm chi phí quét/IO. Nếu chạy lỗi, kiểm `pg_index.indisvalid` và tên index rồi xử lý invalid index trước khi chạy lại. Rollback bằng migration chỉ xóa index, giữ extension để tránh ảnh hưởng dịch vụ khác. [PostgreSQL CREATE INDEX](https://www.postgresql.org/docs/16/sql-createindex.html)

Evidence bổ sung ở `outputs/performance-audit/scale-100k/`. Bước xác nhận tiếp theo là lặp phép đo trên staging có dataset phân bố giống thực tế, tách máy phát tải, đo p95 theo endpoint, FPM backlog, CPU, WAL và độ trễ ghi trong ít nhất 60 phút. Mục tiêu 1.000 RPS vẫn chưa đạt.

## Vòng tiếp theo · Tối ưu tìm kiếm ở trang sâu

### Đo và chọn cách truy vấn

So sánh các dạng SQL của `GET /api/v1/listings` trên cùng PostgreSQL 16, 100.000 listing tổng hợp và GIN đã áp dụng. Mỗi số SQL là trung vị 5 lần `EXPLAIN (ANALYZE, BUFFERS)`. Từ khóa rộng khớp 83.080 listing. Với thứ tự mới nhất ở trang 2.200, hai câu COUNT và lấy dữ liệu hết **217,5 ms** cộng lại. CTE materialized chỉ chứa ID và cột sort, dùng chung điều kiện lọc cho COUNT và page, hết **131,8 ms**. Với sort giá ở trang tương đương, tổng hai câu **244,3 ms** giảm còn **140,1 ms**. Quét tập rộng vẫn tốn chi phí tuyến tính trên số bản ghi phù hợp.

Dùng CTE cho mọi trang làm chậm trang đầu xếp mới nhất (**97,3 → 124,2 ms**). Dùng `COUNT(*) OVER ()` trên trang đầu còn chậm hơn (**210,4 ms**), và cần xử lý thêm khi trang ngoài phạm vi. Tại offset 2.000 và 5.000, paginate cũ nhanh hơn. Tại offset 20.000, từ khóa rộng mất **143,4 ms** với hai query và **122,4 ms** với CTE. Đó là cơ sở thử ngưỡng 20.000, tương đương từ trang 1.001 với 20 listing/trang. [PostgreSQL LIMIT/OFFSET](https://www.postgresql.org/docs/16/queries-limit.html) · [PostgreSQL CTE materialization](https://www.postgresql.org/docs/16/queries-with.html)

### Bản vá và xác minh

`backend/app/Http/Controllers/Api/V1/ListingController.php` dùng cùng bộ lọc và quy tắc hiển thị với paginate cũ. Trang sâu dùng một snapshot SQL cho exact total và tập ID. Chỉ lấy trường đầy đủ của listing trên trang yêu cầu, sau đó dùng eager loading và resource hiện có. Khoá phụ để sắp theo giá là ID như trước. Trang vượt phạm vi trả `data: []` và total thực. Ngưỡng dựa trên phép đo local, cần lặp với dataset staging. Không đổi API, frontend hay schema trong vòng này.

- **Trang sâu, cache lạnh, 12 RPS cấp trong 30 giây.** Cả hai lượt 360/360 HTTP 200, không timeout hoặc lượt bỏ. p50 **242 → 135 ms**, p95 **570 → 326 ms** và p99 **657 → 453 ms**. FPM backlog đỉnh 2 ở cả hai lượt. So sánh giữ nguyên mix năm query và cấu hình chạy.
- **Tìm kiếm hỗn hợp cache lạnh, 25 RPS cấp trong 24 giây.** Sau bản vá 600/600 HTTP 200, **24,94 RPS** thành công, p95 **168 ms**, p99 **255 ms**, không ghi nhận FPM backlog. Lượt này là xác nhận riêng, tránh so trực tiếp với phép đo cache có 60 HIT ở vòng trước.
- **Tải đọc 80% public, 20% xác thực, 25 RPS cấp trong 90 giây.** Trước và sau lần lượt **24,76 → 24,96 RPS** thành công, p95 toàn nhóm **1.468 → 840 ms**, p99 **2.480 → 1.770 ms**. p95 riêng search **1.535 → 987 ms**, hàng đợi FPM đỉnh **61 → 39**. Lượt bỏ **10 → 1** trên 2.250 lượt dự kiến. Toàn bộ request đã phát trả HTTP 200. Cặp đo này vẫn vượt ngưỡng p95 300 ms và p99 1.000 ms.
- Đối chiếu HTTP trực tiếp trên cùng dataset trả JSON giống hệt ở 10 query, gồm newest, price asc/desc, từ khóa hiếm, trang ngoài phạm vi và page lớn nhất được validation chấp nhận.
- **Hồi quy PostgreSQL:** 44/44 test, 248 assertions. Test mới có 20.001 kết quả phù hợp, bao gồm giá bằng nhau, listing draft, title không khớp, kết quả dùng quan hệ eager-load và trang vượt phạm vi. Pint đạt. Scramble export thử ở môi trường cô lập có 139 operation khớp route. So với OpenAPI đang lưu, `GET /listings` và schema `ListingResource` giống nhau. Full spec lưu trong repo có 123 operation trước thay đổi này. CI có bước kiểm full spec khớp bản export. Mình giữ việc đồng bộ 16 operation ngoài phạm vi bản vá phân trang. Redocly trên bản export xác nhận hợp lệ với 70 warning.
- Một biến thể đổi cách loại listing có moderation hold cho thấy lợi ích SQL trên bộ dữ liệu có bảng hold rỗng. Mình giữ nguyên predicate vì benchmark chưa đại diện cardinality moderation. Evidence có các execution plan của thử nghiệm.

Raw benchmark và query plan nằm trong `outputs/performance-audit/deep-pagination/`.


## 15/11/2026 · Giảm tải polling của inbox

Bộ đếm tin nhắn toàn trang trước đây chạy mỗi 5 giây bằng interval, kể cả khi request trước chưa trả kết quả. Tái sử dụng `usePolling` ở bộ đếm và dùng chung chu kỳ inbox **15 giây** với danh sách hội thoại, theo PC-06. Cuộc hội thoại đang mở dùng chu kỳ hiện hữu. Mỗi lần thăm dò tiếp theo chờ lần trước hoàn tất. Tab ẩn hoặc offline hủy lần thăm dò đang chạy. Nếu quay lại tab trước khi công việc bị hủy kết thúc, hook đợi công việc đó kết thúc rồi mới gửi lần tiếp theo. Khi API lỗi, dùng các bước **5 → 10 → 20 → tối đa 30 giây** và giữ thời điểm sớm nhất mà `Retry-After` cho phép ngay cả khi đổi trạng thái tab. Badge có timeout 10 giây, giữ số gần nhất khi lỗi tạm thời và xóa khi hết phiên, mất quyền hoặc đổi tài khoản. [PC-06](../Non-functional%20Requirements/Performance%20%26%20Capacity.md#pc-06--polling-backlog-và-tài-nguyên)

### Kiểm tra

- Chrome, cùng tài khoản giả lập và cửa sổ đo **46 giây**. API nhanh giảm **10 → 4 request**, còn API trễ 6,5 giây giảm **10 → 3 request** và số request chồng nhau tối đa giảm **2 → 1**. Lượt đổi trạng thái tab với `429 Retry-After: 8` gửi lại sau **8,02 giây**. Response 401 sau đó xóa badge, hook không lên lịch lần nữa. Bài đo Chrome quan sát thêm 5,5 giây, test đồng hồ giả xác nhận không gửi thêm trong 60 giây.
- Với 5.000 tab đang hiển thị, chỉ tính badge và giả định API phản hồi ngay, nhịp đều 5 giây tạo khoảng 1.000 request/giây và nhịp 15 giây tạo khoảng 333 request/giây. Đây là phép tính theo chu kỳ, chưa đo trên Laravel. Mở trang, quay lại tab hoặc lỗi có thể làm tần suất thực tế khác.
- Next.js production build và TypeScript đạt.
- Vitest **63/71**, có 5 ca hồi quy mới đạt và đúng 8 ca lỗi đã thấy ở baseline **58/66**. Các ca mới kiểm pacing, chuyển trạng thái tab, backoff, đổi tài khoản và hết phiên.
- ESLint đạt cho hook, inbox và test sửa trong lần này. AppShell giảm từ 3 lỗi / 3 cảnh báo xuống 2 lỗi / 2 cảnh báo. Phần còn lại thuộc hiệu ứng notifications và favorites có sẵn.
- Không đổi server, PostgreSQL hoặc hợp đồng API. Lượt Chrome dùng API giả lập tại localhost để đo nhịp request của một tab. Tải Laravel thực tế tại 1.000 RPS vẫn cần xác minh trên staging bằng máy phát tải riêng.

## 15/11/2026 · Đo tải trên image production với đọc và ghi thật

Môi trường chạy một Caddy, một PHP-FPM, một PostgreSQL 16 trên volume Docker, một worker outbox và một bộ phát Node trong mạng Docker nội bộ. Máy M1 dùng chung 4 vCPU và 3,83 GiB cho các container, gồm ứng dụng và bộ phát tải. PHP dùng image `Dockerfile.production` cài `--no-dev`, bật OPcache và cache config/route/view. Build `sha256:75fcac28bf2c3e2f59cd50dfc722a0a8be8c6d5e2c124c2884a19c2b0ccb21ce` lấy source `49d14e5` cùng bản vá trong workspace. Pulse bật và Telescope tắt trong các lượt này. Mẫu dữ liệu gồm 100.000 listing tổng hợp, 20 tutor, 20 customer, 20 Program và cookie Sanctum riêng từng vai trò. Hỗn hợp chính gồm 75% đọc public, 20% đọc xác thực và 5% tạo Session.

### Kết quả vòng cấp tải

- **25 RPS trong 45 giây.** 1.125/1.125 lượt thành công, p95/p99 **30/71 ms**.
- **50 RPS trong 45 giây.** 2.250/2.250 lượt thành công, p95/p99 **28/48 ms**.
- **100 RPS trong 30 giây.** 3.000/3.000 lượt thành công, **99,99 RPS** tính theo thời gian thực tế gồm drain, p95/p99 **31/71 ms**. p95 của cả tám endpoint đọc dưới 300 ms. Tìm kiếm có 584 HIT trên 600 lượt, p95/p99 **28/77 ms**. Tạo Session p95/p99 **40/89 ms**.
- **Giữ tải 100 RPS trong 3 phút.** **18.000/18.000** lượt thành công, p50/p95/p99 **16/36/93 ms**. 18 cửa sổ 10 giây đều xử lý đủ 1.000 lượt, p95/p99 cửa sổ cao nhất **124/189 ms**. Nhóm public đạt 75 RPS, đọc xác thực 20 RPS và ghi Session 5 RPS. Search có **3.552 HIT / 48 MISS**, p95 33 ms. Session create p95 51 ms, 900 lượt ghi thành công và 901 Session trong DB khi tính cả smoke. Số mẫu PG quan sát tối đa 4 kết nối, 0 lock waiter và 0 deadlock. FPM active tối đa 4 với 13 lượt chờ tại mẫu cao nhất. Máy phát cùng VM, Pulse bật, chưa đủ thời gian để đánh giá soak.
- **250 RPS trong 20 giây.** **164,13 RPS** thành công, p95/p99 **3.439/3.477 ms**, bỏ **1.194/5.000** lượt. Cả tám endpoint đọc vượt ngưỡng latency. FPM đạt 5 process active với listen queue 509. CPU mẫu cao nhất của app **200,55%** và PostgreSQL **114,27%** trên Docker VM 4 vCPU. Mẫu PostgreSQL ghi nhận 0 lock waiter và 0 deadlock.
- **1.000 RPS trong 20 giây.** **169,84 RPS** thành công, p95/p99 **3.014/3.470 ms**, bỏ **16.132/20.000** lượt. Tất cả 3.868 lượt đã phát đều có mã 200/201. Tỷ lệ bỏ **80,66%** vượt xa ngưỡng lỗi dưới 1%. Kết quả là stress ngắn trên một máy, với trần 512 request đang chờ phía bộ phát. Hàng đợi FPM và kết quả bậc 250 cho thấy origin chậm hơn lượng tải cấp. Tách máy phát để xác định giới hạn từng tầng khi đo lại.
- **Ghi nặng · 10 RPS trong 30 giây, 35% tạo Session.** 300/300 lượt thành công, p95/p99 **49/104 ms** toàn nhóm. SQL đối chiếu đúng **106** Session, gồm một Session smoke được tạo qua hai POST song song có cùng khóa. Mỗi Session có đúng một audit create và một biên nhận idempotency. Các bậc tải hỗn hợp cũng khớp số Session, slot, audit và biên nhận với HTTP 201. Luồng payment, login trong cửa sổ đo và khả năng drain outbox dưới tải ghi sự kiện cần fixture và lượt đo riêng.
- **Bộ lọc search đa dạng · 50 RPS trong 45 giây.** 2.250/2.250 lượt thành công. Cả 450 lượt tìm kiếm đều MISS khi thay đổi bộ lọc giá. p95/p99 toàn nhóm **109/183 ms**, search **133/228 ms**. Với bộ lọc lặp ở cùng mức cấp, 16/450 lượt search MISS và p95/p99 search **22/109 ms**. Chi phí miss ảnh hưởng trực tiếp đến headroom của tìm kiếm. Hai lượt chạy dùng cùng image và phân bố endpoint, một hồ sơ có thêm bộ lọc giá.
- **Tăng tải đột ngột · 100 → 500 → 100 RPS.** Trong 10 giây cấp 500, hoàn thành 2.106/5.000 lượt, p95 **3.491 ms** và FPM có tối đa 508 lượt chờ tại mẫu quan sát. Cửa sổ 10 giây đầu sau khi giảm tải vẫn p95 **2.693 ms**, bỏ 1/1.000 lượt. Cửa sổ thứ hai về p95 **68 ms** và hoàn thành đủ 1.000 lượt. Tất cả 310 Session, gồm smoke, có đúng một audit và một receipt.

Tập bộ lọc lặp tạo tỷ lệ HIT cao, do đó số liệu trên là mức đo cho cache ấm ở mix đã nêu. Phép đo cũ dùng cấu hình, fixture và mức đa dạng bộ lọc khác. Dùng các cặp trước/sau cùng điều kiện ở các phần trên để định lượng tác động của từng bản vá.

Bộ chạy, hướng dẫn benchmark và các ngưỡng kiểm nằm trong [docker/performance](/Users/ttcenter/TutorLink/docker/performance/README.md). Kết quả từng lượt gồm HTTP theo endpoint/cửa sổ, FPM, PostgreSQL, Docker CPU/RAM/I/O/network và đối chiếu SQL. Redis, CDN và static không được tính vào số Laravel origin. [Evidence cục bộ](/Users/ttcenter/TutorLink/outputs/performance-audit/capacity)

### Thay đổi và phạm vi phát hành

- **Laravel.** `PublicCatalogCache.php` lấy generation và mốc hết hạn từ cùng snapshot. `ListingController.php` dùng một tập ID phù hợp cho phân trang sâu và exact total. `MarketplaceApiTest.php` kiểm hồi quy cho trang sâu. Migration `2026_11_14_154208_add_active_listing_text_search_index.php` tạo GIN concurrent trên `pg_trgm`, kèm rollback index. Mã nguồn đã kiểm hợp đồng OpenAPI cho listing và 44/44 test backend mục tiêu trên PostgreSQL. Bản OpenAPI checked-in cần quy trình đồng bộ riêng trước phát hành toàn bộ API.
- **Next.js.** `marketplace.ts`, `featured-services.tsx` và `featured-tutors.tsx` gộp request public giống nhau đang chờ. `app-shell.tsx`, `use-polling.ts`, `use-unread-messages.ts` và `use-inbox-data.ts` điều chỉnh polling. 9/9 test mục tiêu và typecheck đạt. Toàn bộ Vitest đạt 63/71, với cùng 8 lỗi có trước bản vá polling ở AppShell, màn hình workspace và chat. Next build đã đạt; ESLint trên AppShell còn hai lỗi có sẵn.
- **Runtime.** `docker/performance/` thêm Compose, seed, bộ phát tải và các truy vấn kiểm. Source ứng dụng giữ nguyên dependency. `pg_trgm` là extension PostgreSQL cho index ứng dụng. `pg_stat_statements` chỉ được bật trong môi trường benchmark. Hạ tầng triển khai đang chạy chưa thay đổi.
- **Phát hành.** Triển khai từng thay đổi trên staging cùng snapshot dữ liệu tổng hợp có mật độ lịch sử gần thực tế. Kiểm quyền/cookie, rollback và số liệu sau migration trước khi tăng tải. Dùng log Caddy/ingress cùng metrics FPM, PostgreSQL, Redis và worker để tách điểm nghẽn. Thử Redis riêng cho cache payload public và đo độc lập tác động. Với tìm kiếm nhiều miss, ưu tiên giảm chi phí query/điều chỉnh admission cache trước khi tăng replica công khai. Tính số replica từ tải đạt SLO trên mỗi node staging và đo lại khi dùng chung PostgreSQL, theo ngân sách 1.000 và 5.000–10.000 RPS ở trên.
- **Trạng thái nghiệm thu.** Ngưỡng khoảng 100 RPS với workload đã làm ấm cache đạt trong lượt 3 phút. Lượt cấp 1.000 RPS cục bộ vượt ngưỡng lỗi và latency. Hiện chưa có staging riêng. Cần đo lại trên staging có máy phát tải riêng, đủ 60 phút ở ≥1.000 RPS thành công, kèm soak và đối chiếu các luồng nghiệp vụ trước khi xác nhận mục tiêu. Monitoring tập trung và alert trên runtime triển khai vẫn cần cấu hình và kiểm tra tại staging.

## 15/11/2026 · Phase B và E · Đo tác động của Redis cho marketplace

`MARKETPLACE_CACHE_STORE` cho phép chọn riêng nơi lưu phản hồi public marketplace. Nếu biến này trống, Laravel vẫn lấy store mặc định. PostgreSQL tiếp tục quyết định generation và mốc hết hạn giấy tờ. Các request có cookie hoặc Authorization đi qua luồng API riêng. Khi thao tác đọc cache lỗi, API gọi controller một lần, trả `BYPASS` và bỏ lần thử ghi cache trong cùng request. `REDIS_CACHE_CONNECT_TIMEOUT` và `REDIS_CACHE_READ_TIMEOUT` cho phép giới hạn thời gian chờ kết nối cache.

Kiểm tra HTTP trên PhpRedis 6.3.0 và Redis 7.4 trong mạng cô lập xác nhận `MISS → HIT`. Gửi cookie cho cùng URL trả `private, no-store`. Cập nhật trạng thái listing sang inactive trong PostgreSQL làm URL đã cache trả 404 ngay ở lần đọc tiếp theo. Khôi phục listing làm cache trả `MISS → HIT`. Dừng Redis cho HTTP 200 và `BYPASS`; khởi động lại cho `MISS → HIT` và đúng slug. [Cấu hình Laravel Redis](https://laravel.com/docs/13.x/redis)

Hai biến thể dùng cùng image PHP-FPM có PhpRedis, cùng một PostgreSQL với 100.000 listing, cùng redis container và cùng workload 75% đọc public, 20% đọc xác thực, 5% tạo Session. Chỉ đổi store response public và khởi động lại FPM để lấy cấu hình tương ứng. Mỗi lượt bắt đầu với cache marketplace đã dọn rồi chạy cùng smoke làm nóng tối thiểu. Bộ đếm `pg_stat_statements` gồm cả smoke. Nhóm test marketplace, compatibility, transaction và schema đạt **44/44, 249 assertions**, Pint đạt.

- **160 RPS cấp · bộ lọc lặp, 30 giây mỗi lượt.** PostgreSQL → Redis → PostgreSQL xử lý đủ 4.800 yêu cầu mỗi lượt, tương ứng **159,96 → 159,95 → 157,17 RPS** tính cả drain. p95 **438 → 840 → 1.151 ms**. Cả ba lượt đều vượt ngưỡng p95 300 ms, và độ trễ hai lượt PostgreSQL cũng chênh đáng kể. Redis đưa số `SELECT` vào bảng `cache` từ **3.604 xuống 0** và số lần insert vào bảng này từ 38–40 xuống 1. Cả hai cấu hình vẫn truy vấn generation **3.604 lần**. Ở mức này chưa thấy Redis tăng thông lượng ổn định hoặc giúp vượt ngưỡng latency.
- **75 RPS cấp · bộ lọc đa dạng, 25 giây mỗi lượt.** Hai lần chạy PostgreSQL có p95 **303 và 164 ms**, còn Redis **115 và 120 ms**, kể cả khi đảo thứ tự. p95 riêng tìm kiếm là **330 và 213 ms** với PostgreSQL, **135 và 141 ms** với Redis. Mỗi lượt có 375 search MISS và xử lý đủ 1.875 yêu cầu. Redis qua ngưỡng đọc ở cả hai lượt, PostgreSQL qua ở một lượt. Lợi ích quan sát là giảm latency cho workload này, với cùng thông lượng. Cả bảy lượt khớp số Session, slot, audit và biên nhận.

Giữ PostgreSQL làm mặc định. Redis là lựa chọn opt-in để kiểm tại staging, nơi có thể đo độc lập chi phí mạng và dữ liệu thật hơn. Tiếp theo ở Phase D là tách máy phát tải và đo các API replica dùng chung PostgreSQL, sau đó Phase E kiểm cả giới hạn thông lượng và độ trễ khi đổi mỗi tầng. Mục tiêu 1.000 RPS và giám sát runtime triển khai vẫn cần nghiệm thu.

[Số đo từng lượt](/Users/ttcenter/TutorLink/outputs/performance-audit/redis-ab) · [Cách chạy lại](/Users/ttcenter/TutorLink/docker/performance/README.md)

## 15/11/2026 · Phase D · Hai bản sao Laravel sau Caddy

Bản thử chạy một hoặc hai container PHP-FPM sau Caddy với chính sách `round_robin`. Các container dùng cùng image, APP_KEY và PostgreSQL, gồm database session và cache response public. Trong Docker thử, tên upstream xuất hiện ở `X-Benchmark-Upstream` để đối chiếu nơi thực sự xử lý request. Cổng FPM, diagnostics và API đều trong mạng nội bộ. [Caddy load balancing](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy#load-balancing)

- **Phiên và phân quyền.** Smoke gửi cùng cookie đến hai bản sao và cả hai trả cùng Program của Customer. Cuộc gọi ẩn danh nhận 401 ở cả hai. Cookie hợp lệ đọc Program thuộc Customer khác nhận 404 ở cả hai. Hai POST tạo Session dùng cùng idempotency key và body được xử lý trên hai upstream, đều trả 201 và cùng ID. DB có một dòng Session smoke, một audit và một receipt tương ứng.
- **Cache và ẩn listing.** Bản sao đầu tiên đọc listing trả MISS, bản sao thứ hai trả HIT. Sau khi commit cập nhật inactive, cả hai trả 404 ở lượt tiếp theo.
- **Mất một bản sao.** Sau khi dừng hẳn `app-replica`, 12/12 GET xác thực tiếp theo trả HTTP 200 từ `app`; độ trễ lớn nhất quan sát là 298 ms. Khi khởi động lại, hai upstream cùng phục vụ. Phép thử đo việc chuyển tuyến sau khi process đã dừng. Khả năng xử lý request đang chạy, chuyển DB, chuyển ingress và mất cả host thuộc bài kiểm thử riêng.
- **Tệp private.** Hai app dùng chung một Docker volume cho `storage/app/private`. Bản sao thứ hai nhận PDF tổng hợp, bản đầu hoàn tất. Owner tải được đúng 47 byte từ cả hai bản sao; Customer khác nhận 403 từ cả hai. Kiểm tệp được chạy riêng sau các lượt tải hỗn hợp. [Kết quả kiểm tệp](/Users/ttcenter/TutorLink/outputs/performance-audit/replicas/private-file.json)

[Lượt kiểm thử chuyển tuyến](/Users/ttcenter/TutorLink/outputs/performance-audit/replicas/failover.ndjson) · [Smoke và phân bổ upstream](/Users/ttcenter/TutorLink/outputs/performance-audit/replicas/smoke-two/http.json)

### Đo một và hai bản sao trên cùng máy

Docker Desktop có 4 vCPU và 3,83 GiB. Caddy, PostgreSQL, worker, FPM và máy phát tải cùng chia tài nguyên. Mỗi FPM pool có tối đa 5 child, nên hai bản sao tăng tổng số child khả dụng lên 10. Hỗn hợp 75% public read, 20% authenticated read, 5% tạo Session. Trước mỗi lượt chạy, dọn cache response public của benchmark và làm ấm bằng 200 lượt đọc. Cả năm lượt đều đối chiếu đúng Session, slot, audit và receipt.

- **160 RPS cấp trong 30 giây.** Cả ba lượt đều xử lý đủ 4.800 yêu cầu. Hai lượt một bản sao có p95 **618 và 218 ms**, tải thành công **159,96 và 159,82 RPS**. Lượt hai bản sao có p95 **171 ms**, tải thành công **159,95 RPS**, mỗi bản trả 2.400 phản hồi. Hàng đợi FPM lớn nhất quan sát giảm từ **113 và 38** ở hai lượt một bản sao xuống **14 và 16** trên từng pool ở lượt hai bản sao. Một lượt một bản sao và lượt hai bản sao đạt ngưỡng. Chênh lệch giữa hai lượt một bản sao phản ánh độ dao động trong VM thử.
- **250 RPS cấp trong 20 giây.** Một bản sao đạt **165,55 RPS**, p95 **3.725 ms**, bỏ **1.213/5.000** lượt. Hai bản sao đạt **157,13 RPS**, p95 **3.829 ms**, bỏ **1.417/5.000** lượt. Các pool đều chạm 5 child, queue quan sát lên 509 ở một pool hoặc 261 và 256 ở hai pool. PostgreSQL đạt đỉnh mẫu khoảng 1,1–1,2 lõi ở hai lượt. Kết quả xác nhận thêm pool trên cùng VM không nâng thông lượng ở bậc này. Gom app, DB và máy phát trên một VM giới hạn khả năng suy ra thông lượng khi tách host.

### Trước lượt đo nhiều máy

- Đặt ingress trước từng app proxy, đặt app replicas và máy phát tải trên tài nguyên riêng. Dùng cùng image, APP_KEY, cookie cấu hình nhất quán, một PostgreSQL primary và job migration chạy một lần. Đo công suất của một node trước rồi kiểm phần tăng thông lượng khi thêm node. Theo dõi hàng đợi FPM, CPU và PostgreSQL tại từng bậc.
- Với file private, upload hiện chọn `Storage::disk('local')` trực tiếp và lưu `disk=local` trong hàng File. Cấu hình `FILESYSTEM_DISK=s3` một mình chưa chuyển được luồng này. Staging cần filesystem dùng chung tại thư mục private hoặc cần triển khai adapter, di chuyển và kiểm lại quyền tải xuống với file hiện hữu.
- Giữ health API theo từng node và đo tình trạng worker riêng. `/health/ready` hiện gộp PostgreSQL với heartbeat của bốn tiến trình nền. Phép thử Caddy dùng chuyển tuyến thụ động trước lỗi kết nối FPM. Cần diễn tập rollout, mất host và phục hồi database trên staging.

Phase D đã xác minh phân tải, phiên, lưu trữ và quyền truy cập trong Docker cục bộ. Phase E còn phép đo nhiều máy và nghiệm thu ≥1.000 RPS trong 60 phút. Production chưa được triển khai từ cấu hình thử này.

[Số liệu so sánh](/Users/ttcenter/TutorLink/outputs/performance-audit/replicas/summary.json) · [Cách chạy lại](/Users/ttcenter/TutorLink/docker/performance/README.md)

## 15/11/2026 · Phase F · Giám sát và diễn tập cảnh báo

Đã dựng Prometheus trong mạng thử Docker. Caddy ghi RPS, histogram độ trễ và tỷ lệ HTTP 5xx của origin. Collector đọc trạng thái từng pool PHP-FPM và thu thống kê PostgreSQL về kết nối, lock, deadlock, WAL và outbox. Tài khoản collector có quyền thống kê và quyền đọc giới hạn trên các cột dùng để đếm queue. Phép kiểm quyền xác nhận tài khoản được đọc ID job, bị từ chối đọc payload job và bảng `users`. Các cổng metrics và Prometheus ở trong mạng nội bộ. Promtool xác nhận cấu hình và 12 rules hợp lệ, gồm 3 recording rules và 9 alert rules. [Caddy metrics](https://caddyserver.com/docs/metrics) · [Prometheus alerting rules](https://prometheus.io/docs/prometheus/latest/configuration/alerting_rules/)

- **Bình thường · cấp tải 25 RPS trong 20 giây.** 500/500 lượt thành công, p95 phía máy phát **36 ms**, p95 Caddy xấp xỉ **46 ms** tại snapshot và hàng đợi FPM bằng 0. Prometheus không có alert active.
- **Quá tải · cấp 250 RPS trong 42 giây.** Snapshot trong lúc tải ghi nhận Caddy p95 xấp xỉ **4,99 giây**, **9,08%** phản hồi 5xx và **508** lượt chờ FPM. Prometheus chuyển cả cảnh báo độ trễ, lỗi ingress và hàng đợi FPM sang `firing`. Máy phát xử lý thành công 4.851/10.500 lượt, đạt 105,42 RPS tính cả drain. Có 4.575 lượt chưa phát và 1.074 lượt đã phát nhưng lỗi hoặc chưa nhận phản hồi hoàn chỉnh.
- **Toàn vẹn ghi khi máy phát timeout.** 230 yêu cầu tạo Session nhận HTTP 201, 34 yêu cầu thiếu phản hồi hoàn chỉnh và 26 nhận HTTP 503. DB có 265 Session gồm một bản ghi smoke, tức 34 bản ghi nhiều hơn tổng 231 phản hồi thành công và smoke. Chênh lệch bằng số yêu cầu ghi thiếu phản hồi; báo cáo tải chưa gắn từng kết quả HTTP với ID giao dịch. Phát lại cả **265** receipt bằng cùng khóa và payload trả đúng ID ở cả 265 lượt. Tổng Session, slot, audit và receipt giữ nguyên. Kết quả cho thấy cần dùng cùng khóa khi retry một thao tác tạo có kết quả HTTP chưa rõ.
- **Dừng app và thử outbox.** Khi PHP-FPM dừng, **420/420** GET thử qua Caddy trả 502/503 và cảnh báo PHP-FPM không phản hồi cùng tỷ lệ 5xx đều `firing`. Một sự kiện outbox tổng hợp quá hạn làm cảnh báo backlog bật ở tuổi **133 giây**. Chuyển sự kiện đó sang thất bại làm cảnh báo failed job bật. Sau khi xóa fixture và khởi động lại app, cảnh báo biến mất.
- **Dừng DB và collector.** Dừng PostgreSQL làm `tutorlink_db_up` giảm về 0 và cảnh báo DB bật. Dừng riêng collector làm Prometheus báo mất nguồn metrics. Sau khi khôi phục cả hai, `tutorlink_db_up` và FPM trở về 1, không còn alert active. Tổng cộng **8** cảnh báo đã được kích hoạt thực tế. Cảnh báo thứ chín về mức dùng kết nối vượt 80% qua kiểm thử bằng Promtool, gồm kiểm thời gian chờ trước khi `firing`.

Phase F đã có cấu hình và bằng chứng chạy cục bộ. Triển khai staging còn cần ngưỡng theo tải thật, lưu trữ chuỗi metrics lâu dài và cấu hình nơi nhận thông báo vận hành. Phép đo PgBouncer được tạm gác khi chuyển phase. Nghiệm thu 1.000 RPS vẫn mở.

[Tổng hợp diễn tập](/Users/ttcenter/TutorLink/outputs/performance-audit/monitoring/summary.json) · [Cấu hình và cách chạy lại](/Users/ttcenter/TutorLink/docker/performance/README.md)

## 15/11/2026 · Phase E · Tải duy trì và tiêu chí nghiệm thu

Đã thêm bộ kiểm tra định lượng cho ≥60 phút, ít nhất 1.000 phản hồi thành công/giây theo toàn lượt và từng phút hoàn tất, lỗi dưới 1%, p95/p99 từng endpoint đọc và đối chiếu ghi. Bộ phát ghi riêng từng POST qua idempotency key. Bộ xác minh so khóa, ID, Program, tiêu đề và khung giờ với PostgreSQL; các trường hợp server đã commit nhưng client thiếu ACK có danh sách replay riêng. Ba kiểm thử của bộ xác minh đạt, gồm trường hợp tổng đúng nhưng sai khóa và trường hợp số trung bình đạt nhưng một phút hụt ngưỡng.

- **Tải duy trì · 100 RPS trong 10 phút.** Một PHP-FPM, PostgreSQL 16 và máy phát tải chung VM 4 vCPU, 3,83 GiB, 100.000 listing tổng hợp. Hỗn hợp 75% public read, 20% authenticated read và 5% tạo Session. Xử lý đủ **60.000** lượt, **99,99 RPS** thành công tính cả drain, p95/p99 **55/199 ms**. Phút thấp nhất hoàn tất **5.996** lượt. Search p95 **51 ms**, tạo Session p95 **75 ms**. Đối chiếu đúng từng **3.001** bản ghi, gồm lượt smoke, với đủ audit và receipt. [Báo cáo lượt giữ tải](/Users/ttcenter/TutorLink/outputs/performance-audit/phase-e/hold100-10m/phase-e.json)
- **Tài nguyên trong lượt giữ tải.** 57 mẫu PostgreSQL ghi nhận tối đa 4 kết nối đồng thời, khoảng 60.367 kết nối mới trong cửa sổ lấy mẫu, 0 lock waiter, 0 deadlock và 0 job/outbox thất bại. PHP-FPM dùng khoảng **66 MiB** ở đầu và cuối, đỉnh **79,85 MiB**. Đây là kiểm tra ngắn tại một mức tải. Soak và độ ổn định lâu dài thuộc lượt đo tiếp theo ở tải chấp nhận.
- **Sanctum và Admin.** 14 bước qua API thật gồm lấy CSRF, đăng nhập, đọc phiên, đọc Programs và các trang Admin, kiểm Customer bị từ chối ở Admin, đăng xuất và xác nhận cookie cũ trả 401. Lượt này là smoke riêng, ngoài cửa sổ đo RPS. Đã làm mới phiên của Customer thử trước khi chạy stress. [Bằng chứng auth smoke](/Users/ttcenter/TutorLink/outputs/performance-audit/phase-e/auth-smoke.json)
- **Stress · cấp 250 RPS trong 35 giây, giới hạn 1.024 request đang chờ ở máy phát.** Thành công **35,75 RPS**, p95 **5.005 ms**, 6.036 lượt đã phát bị lỗi và 1.284 lượt chưa phát. Có 71 POST trả 201 và 295 POST thiếu phản hồi hoàn chỉnh. DB có 367 Session sau khi FPM xử lý xong, gồm một bản ghi smoke. Theo từng khóa, cả 295 POST thiếu phản hồi đều đã commit. Replay cả 295 trả đúng ID và đối chiếu SQL khớp đủ 367 Session, audit và receipt. DB còn tăng từ 348 lên 367 dòng sau khi máy phát tải dừng chờ, nên bộ chạy hiện lấy snapshot sau khi FPM hết việc đang xử lý và hàng chờ. [Đối chiếu stress](/Users/ttcenter/TutorLink/outputs/performance-audit/phase-e/stress250-final/phase-e.json)
- **Stress · cấp 1.000 RPS trong 20 giây, giới hạn 512 request đang chờ ở máy phát.** Laravel xử lý **3.800/20.000** lượt, **167,04 RPS** thành công tính cả drain, p95/p99 **3.245/3.324 ms**. Bỏ **16.200/20.000** lượt, toàn bộ lượt đã phát trả thành công. FPM có tối đa 509 lượt chờ tại một mẫu. Đối chiếu đúng từng 177 Session gồm smoke. Hai lượt stress dùng giới hạn đồng thời khác nhau để quan sát quá tải, nên số throughput của chúng không tạo thành phép so sánh cấu hình trực tiếp. [Đối chiếu lượt cấp 1.000 RPS](/Users/ttcenter/TutorLink/outputs/performance-audit/phase-e/target1000/phase-e.json)

**Trạng thái Phase E.** Bộ kiểm tra và các lượt bổ sung tại chỗ đã hoàn tất. Bằng chứng hiện xác nhận tải hỗn hợp 100 RPS trong 10 phút trên VM hiện tại. Kết quả cấp 1.000 RPS bị từ chối theo thời lượng, throughput, tỷ lệ lỗi và latency. Nghiệm thu vẫn cần bài 1.000 RPS thành công trong 60 phút với máy phát tải riêng, cùng bài soak trên môi trường đại diện.

[Tổng hợp Phase E](/Users/ttcenter/TutorLink/outputs/performance-audit/phase-e/summary.json) · [Bộ kiểm tra nghiệm thu](/Users/ttcenter/TutorLink/docker/performance/phase-e.mjs)

Evidence nằm ở `outputs/performance-audit/inbox-polling/`.
