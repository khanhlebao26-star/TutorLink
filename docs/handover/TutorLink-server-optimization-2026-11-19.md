# TutorLink — Bản ghi tối ưu server

Ngày rà soát: **19/11/2026**. Source TutorLink: nhánh `feature/sv-optimal`, commit `c58ade1`. Repo học hỏi: `AkioCkist/daily_vocabulary`, commit `3c0ea06ccaebda687c662fa14245afcb0829a5c6`.

Phạm vi rà soát ban đầu (trước khi triển khai ở mục 7): đọc mã và cấu hình, kiểm tra bằng chứng hiệu năng đã lưu, ghi lại tối ưu TutorLink thực sự có và đề xuất tiếp theo. Không thiết kế giao diện, không sửa logic server hay thay cấu hình đang chạy. README được soạn theo thông tin môn Laravel, giảng viên Nguyen Huu Quyen, lớp 23CSE; người dùng bổ sung tên nhóm/thành viên.

## 1. Cách phân loại bằng chứng

- **Đã xác nhận trong source:** cơ chế có trong revision nêu trên. Không đồng nghĩa đã triển khai lên server hay đã chứng minh mức cải thiện.
- **Đã đo trước đây:** kết quả đọc lại từ báo cáo/artefact ngày 14–15/11/2026, không phải lượt đo mới ngày 19/11.
- **Đề xuất:** chưa thực hiện trong lần rà soát này; lợi ích cần xác minh bằng phép đo cùng điều kiện.
- **Not tested trong lượt rà soát ban đầu:** backend runtime, fresh regression, HTTP smoke và tải thực tế. Docker daemon local không kết nối được khi kiểm tra; không khởi động lại dịch vụ hoặc phát tải.

## 2. Học phương pháp từ repo tham khảo

Các nhóm kỹ thuật đáng học: gộp phép đếm thành SQL aggregate; cache theo loại dữ liệu và invalidation; thêm index theo truy vấn; tải trước quan hệ; gộp cập nhật; đưa email sang job nền. README trình bày vấn đề → giải pháp → vị trí mã → tác động → kiểm chứng. Khi dùng bố cục này cho TutorLink, số liệu phải đến từ TutorLink.

Cần kiểm tra code thay vì xem mọi nhãn “optimized” là kết luận. Ví dụ trong repo tham khảo:

- [ReviewService](https://github.com/AkioCkist/daily_vocabulary/blob/3c0ea06ccaebda687c662fa14245afcb0829a5c6/app/Services/ReviewService.php) có aggregate cho các thống kê cùng bảng; [DashboardService](https://github.com/AkioCkist/daily_vocabulary/blob/3c0ea06ccaebda687c662fa14245afcb0829a5c6/app/Services/DashboardService.php) vẫn có nhiều phép đếm khi cache miss. Cache và tối ưu truy vấn gốc là hai việc khác nhau.
- [EmailDigestService](https://github.com/AkioCkist/daily_vocabulary/blob/3c0ea06ccaebda687c662fa14245afcb0829a5c6/app/Services/EmailDigestService.php) dispatch vào `default`; lệnh deployment trong [README](https://github.com/AkioCkist/daily_vocabulary/blob/3c0ea06ccaebda687c662fa14245afcb0829a5c6/README.md) nghe `emails`. Cần đồng bộ producer và worker. `delay(5s)` tự nó không gộp nhiều job thành batch.
- Nhiều phép `COUNT` cố định chưa phải lỗi N+1; ít query hơn cũng không tự đồng nghĩa ít kết nối DB hơn.
- [FlashcardController](https://github.com/AkioCkist/daily_vocabulary/blob/3c0ea06ccaebda687c662fa14245afcb0829a5c6/app/Http/Controllers/FlashcardController.php) có nhánh dùng `updateOrCreate`; không nên gọi đây là một câu bulk upsert cho nhiều bản ghi. Chọn một offset rồi lấy một đoạn liên tiếp cũng không tương đương lấy mẫu ngẫu nhiên đều nhiều từ.

Không chạy bộ test hoặc tái hiện các con số hiệu năng của repo tham khảo.

## 3. Tối ưu TutorLink đã có trong mã

### 3.1. Tải trước quan hệ, chọn cột và giới hạn kết quả

[ListingController](../../backend/app/Http/Controllers/Api/V1/ListingController.php) dùng `with()` cho tutor profile, user, specialization, subcategory và category; giới hạn cột trong các quan hệ. `per_page` tối đa 50. [CatalogController](../../backend/app/Http/Controllers/Api/V1/CatalogController.php) tải cây catalog với quan hệ lồng nhau.

**Tác dụng:** tránh mỗi item lại truy vấn các quan hệ riêng; kiểm soát kích thước response theo trang. Đây là cơ chế hiện có, không ghi một phần trăm giảm tải mới khi chưa đo.

### 3.2. Cache response public có điều kiện

[MarketplaceCacheControl](../../backend/app/Http/Middleware/MarketplaceCacheControl.php) chỉ nhận GET thuộc allow-list, không có Cookie/Authorization và không có user. Chỉ JSON 200 được cache; các nhánh cá nhân hoặc lỗi đi `private, no-store`. TTL là 60 giây; header `X-Marketplace-Cache` cho biết HIT/MISS/BYPASS.

[PublicCatalogCache](../../backend/app/Support/PublicCatalogCache.php) tạo key từ generation, mốc tài liệu approved đã hết hạn, cấu hình yêu cầu tài liệu, path và query đã sắp xếp khóa. Generation cùng expiry được đọc bằng một SQL snapshot. Nếu cache lỗi, middleware quay về xử lý controller.

[Migration invalidation](../../backend/database/migrations/2026_11_03_135735_create_marketplace_cache_versions.php) đặt trigger ở 14 bảng liên quan; thay đổi làm generation mới được ghi trong cùng giao dịch. Có index partial cho truy vấn expiry.

**Tác dụng:** tránh tính lại toàn bộ response public khi key còn hiệu lực, đồng thời bảo toàn điều kiện hiển thị. **Giới hạn:** HIT vẫn có truy vấn generation/expiry; default production cache payload cũng nằm trên PostgreSQL. Chưa có khóa chống nhiều request cùng dựng lại cache và chưa có chính sách admission cho key bộ lọc.

### 3.3. Search index và exact-total pagination

[Migration GIN/pg_trgm](../../backend/database/migrations/2026_11_14_154208_add_active_listing_text_search_index.php) tạo index concurrent trên title/description, giới hạn dịch vụ active và VND. Nó phù hợp với ý định hỗ trợ tìm `%term%`, không phải bằng chứng mọi truy vấn đều dùng index.

[ListingController::deepPage](../../backend/app/Http/Controllers/Api/V1/ListingController.php) dùng CTE `matching AS MATERIALIZED` khi offset ≥20.000, lọc tập ID/cột sort rồi lấy trang và exact total từ tập đó. Cách này giảm lặp phần lọc trong trường hợp trang sâu, vẫn giữ hợp đồng total và thứ tự.

**Giới hạn:** CTE có thể materialize nhiều ID; COUNT/OFFSET vẫn có chi phí. Phải so sánh plan cho từ khóa phổ biến/hiếm, các cách sort và trang sâu trên dữ liệu đại diện trước khi thêm hoặc bỏ index.

### 3.4. Dashboard aggregate tại PostgreSQL

[ReadAdminDashboard](../../backend/app/Actions/Admin/ReadAdminDashboard.php) dùng `GROUP BY status/visibility` và `COUNT`, có scope assignment và quyền theo actor. Không tải toàn bộ hồ sơ/báo cáo/review về PHP để đếm.

**Giới hạn:** mỗi scope/tài nguyên vẫn có query riêng; pending và failed outbox được đếm bằng hai query. Không thể mô tả dashboard hiện chỉ có một SQL hay đã cache toàn bộ.

### 3.5. Outbox và worker nền

[NotificationOutboxWriter](../../backend/app/Support/NotificationOutboxWriter.php) và nghiệp vụ ghi outbox bảo toàn sự kiện trong giao dịch. [ProcessNotificationOutbox](../../backend/app/Console/Commands/ProcessNotificationOutbox.php) dùng `FOR UPDATE SKIP LOCKED`, xử lý từng event trong transaction, giới hạn batch và retry. [WorkNotificationOutbox](../../backend/app/Console/Commands/WorkNotificationOutbox.php) mặc định xử lý tối đa 100 event mỗi vòng, nghỉ 2 giây.

[Compose production](../../compose.production.yml) chạy outbox, Slack, broadcast và scheduler riêng. Slack/broadcast là database queue. Outbox là worker đọc bảng nghiệp vụ riêng, không phải queue Redis để có thể đổi chỉ bằng một biến môi trường.

**Tác dụng:** tách xử lý thông báo/tích hợp khỏi HTTP và hỗ trợ retry. **Giới hạn:** chưa chứng minh công suất drain của worker ở staging; 100 event/vòng không đồng nghĩa 50 event/giây vì còn thời gian xử lý.

### 3.6. Runtime production

[Dockerfile.production](../../backend/Dockerfile.production) cài dependency không gồm dev và tạo autoloader classmap authoritative. [php.ini](../../backend/docker/production/php.ini) bật OPcache, tắt kiểm timestamp. [Entrypoint](../../backend/docker/production/entrypoint.sh) chạy config/route/view cache. [Caddyfile](../../backend/docker/production/Caddyfile) nén zstd/gzip và chuyển riêng WebSocket tới Reverb.

**Tác dụng:** giảm chi phí nạp/biên dịch và khởi tạo cấu hình, giảm dung lượng truyền các response nén được. Đây là cấu hình đã có, chưa xác nhận đang được server thực tế sử dụng. Với OPcache không kiểm timestamp, phát hành code cần quy trình thay image/restart đúng phiên bản.

### 3.7. Giảm request tới API mà không đổi bố cục giao diện

[Marketplace client](../../frontend/src/lib/data/marketplace.ts) gộp request public trùng đang chờ. [usePolling](../../frontend/src/lib/hooks/use-polling.ts) bảo đảm không chồng lượt, tạm dừng/abort khi tab ẩn hoặc offline, backoff khi lỗi và xử lý Retry-After. [Badge unread](../../frontend/src/lib/hooks/use-unread-messages.ts) gọi endpoint count riêng với chu kỳ 15 giây; chat mặc định 10 giây.

**Giới hạn:** client chat vẫn polling; có Reverb backend không đồng nghĩa frontend đã nhận tin bằng WebSocket. [readAllWorkspaceRows](../../frontend/src/lib/data/workspace.ts) vẫn tải mọi trang còn lại bằng `Promise.all`; Tasks và Progress có sử dụng trong [workspace tab](../../frontend/src/components/programs/workspace-tab-page.tsx).

## 4. Phép thử đã có và cách báo cáo trung thực

Nguồn: [báo cáo hiệu năng 14–15/11](TutorLink-performance-audit-2026-11-14.md), [harness và quy trình đối chiếu](../../docker/performance/README.md). Trong máy làm việc còn có `outputs/performance-audit/phase-e/summary.json`; thư mục outputs không được đưa vào Git, nên README dẫn báo cáo đã theo dõi trong repo.

- **100 RPS / 600 giây:** 60.000 thành công; 99,99 successful RPS; p95 55 ms, p99 199 ms; 0 lỗi, 0 lượt bỏ. Đối chiếu 3.001 Session gồm smoke nhất quán.
- **250 RPS / 35 giây:** khoảng 35,75 successful RPS tính cả drain, p95 5.005 ms; 6.036 lỗi và 1.284 lượt chưa phát. Có 295 giao dịch commit nhưng client thiếu phản hồi, đã replay và đối chiếu bằng cùng idempotency key. Kết quả này không đạt yêu cầu tải.
- **1.000 RPS cấp / 20 giây:** 3.800 thành công, khoảng 167,04 successful RPS tính cả drain; p95 3.245 ms; 16.200 lượt bị bỏ. Không thể lấy “0 HTTP errors đã phát” để bỏ qua 81% lượt chưa phát.

Những lượt này dùng dữ liệu tổng hợp và máy phát tải cùng VM với ứng dụng, không phải benchmark production. Chưa đạt mục tiêu 1.000 successful RPS liên tục 60 phút hoặc soak dài hạn. Không so trực tiếp các lượt khác thời lượng, workload hoặc giới hạn generator để suy ra đường cong capacity.

### Redis đã được thử, không phải đề xuất hoàn toàn mới

Báo cáo ngày 15/11 có thử payload cache PostgreSQL/Redis với cùng generation lookup. Ở 75 RPS, workload bộ lọc đa dạng quan sát Redis p95 115–120 ms, hai lượt PostgreSQL 164–303 ms; đây là lợi ích trong workload đó, không bảo đảm tổng quát. Ở 160 RPS bộ lọc lặp, cả hai store đều chưa đạt ngưỡng p95 300 ms. PostgreSQL được giữ làm mặc định; PhpRedis/Redis trong harness không đồng nghĩa đã tích hợp vào production image/Compose.

## 5. Các việc nên tối ưu tiếp

### Ưu tiên 1 — Email xác minh và đặt lại mật khẩu

**Hiện trạng:** [AuthController](../../backend/app/Http/Controllers/Api/V1/AuthController.php) gọi trực tiếp `sendEmailVerificationNotification()` và `Password::sendResetLink()`. [User](../../backend/app/Models/User.php) dùng notification mặc định; cấu hình mail của [AppServiceProvider](../../backend/app/Providers/AppServiceProvider.php) tùy biến nội dung/URL, chưa chuyển các notification này sang queued notification.

**Đề xuất:** notification/job nền cho mail; chỉ rõ tên queue, worker tương ứng và cách xử lý sau commit. Giữ phản hồi chống lộ tài khoản, rate limit và tính đúng của link/token. Kiểm token còn hạn sau thời gian chờ queue, reset bị thay thế, retry và lỗi SMTP. Cấu hình production hiện chưa có worker nghe queue email/default riêng.

**Đo:** p95 HTTP lúc SMTP chậm và thời gian từ yêu cầu tới nhận thư, kiểm gửi sau commit và không lộ tài khoản. [Laravel queues](https://laravel.com/framework/docs/13.x/queues).

### Ưu tiên 2 — Cache đúng nhóm dữ liệu và chống dựng lại đồng thời

**Hiện trạng:** catalog ít đổi và marketplace biến động dùng cùng generation chung/TTL 60 giây. Thay đổi ở users/programs cũng có thể làm mất cache catalog dù catalog không đổi. Nhiều request cùng MISS có thể cùng chạy truy vấn.

**Đề xuất:** tách dependency/version cho catalog và marketplace; catalog có thể có TTL dài hơn khi invalidation đã đúng. Thử cơ chế chỉ một request dựng một key, thời gian khóa có giới hạn và fallback phù hợp. Giới hạn key theo bộ lọc hợp lệ. Thử Redis payload theo A/B, giữ kiểm generation cần thiết cho tính đúng.

**Đo:** SQL/request trên HIT/MISS, hit ratio, số lần rebuild cùng key, p95 khi hết hạn đồng loạt; kiểm ẩn listing, suspension, tài liệu hết hạn và rollback. Không dùng cache cũ tiếp tục phục vụ nội dung đã mất quyền hiển thị. [Laravel cache/locks](https://laravel.com/framework/docs/13.x/cache#atomic-locks).

### Ưu tiên 3 — Đo invalidation chung và query aggregate

**Hiện trạng:** trigger từ 14 bảng cập nhật cùng row generation. Đây là điểm có khả năng tranh khóa, chưa có bằng chứng nó là bottleneck chi phối hiện tại. Dashboard còn hai phép đếm outbox riêng.

**Đề xuất:** đo lock wait dưới tải ghi và số lần invalidation không cần thiết; thu hẹp trigger theo phụ thuộc/cột/trạng thái sau kiểm tính đúng. Có thể gộp outbox pending/failed vào conditional aggregate cùng phạm vi nếu có lợi. Cache thống kê ngắn hạn chỉ khi xác định freshness và scope quyền; authorization vẫn kiểm ở mỗi request.

**Đo:** lock wait, write p95, số truy vấn và execution time. Giữ transaction, audit, quyền, version và idempotency.

### Ưu tiên 4 — Search plan và PHP-FPM

**Hiện trạng:** đã có pg_trgm/deep-page query. Production chưa có pool sizing riêng trong repo; các thử nghiệm trước thấy hàng chờ FPM khi quá tải. Chưa có số đo live ngày 19/11.

**Đề xuất:** đo `EXPLAIN (ANALYZE, BUFFERS)` trên staging cho COUNT và data; đo FPM active/idle, listen queue, CPU/RSS và kết nối DB. Thay một biến mỗi lượt, chọn `pm.max_children` theo ngân sách RAM/CPU/DB. So sánh telemetry bật/tắt theo cùng sampling. Chỉ cân nhắc Octane hoặc thêm replica khi đo xác định được lợi ích.

**Đo:** successful RPS, p50/p95/p99, lỗi và dropped; CPU, RAM, IO, FPM queue, DB waits. Không tăng worker số lượng lớn chỉ vì muốn tăng RPS. [PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php), [PostgreSQL EXPLAIN](https://www.postgresql.org/docs/16/using-explain.html).

### Ưu tiên 5 — Giảm tải phát sinh từ client

**Đề xuất:** phân trang/lọc Tasks tại server và summary có giới hạn cho Progress thay việc tải hết Tasks/Sessions. Tận dụng Reverb để báo thay đổi chat/badge, có reconnect/resync và polling dự phòng. Đây là tối ưu số request và lượng dữ liệu, không phải thiết kế lại giao diện.

**Đo:** request mỗi lần mở workspace, response bytes và độ trễ cập nhật chat, thử tab ẩn/offline/reconnect; kiểm quyền theo phiên, logout và suspend.

### Ràng buộc khi học Redis từ repo tham khảo

[RealtimeSessionChannels](../../backend/app/Support/RealtimeSessionChannels.php) yêu cầu session driver `database` trên kết nối DB ứng dụng và truy vấn bảng sessions để kiểm phiên sống. Không thể đổi `SESSION_DRIVER=redis` như một mẹo cấu hình độc lập. Nếu đổi session store, cần thiết kế lại kiểm sống/thu hồi phiên và kiểm thử liên quan. Database queue và outbox cũng cần đánh giá riêng về tính bền vững; cache eviction không được làm mất công việc.

## 6. Cách ghi phần Optimization trong README nộp đồ án

Với mỗi cơ chế của TutorLink, ghi theo thứ tự:

1. Vấn đề cụ thể trong luồng của dự án.
2. Giải pháp đã có và liên kết mã/migration/cấu hình.
3. Điều kiện áp dụng và giới hạn còn lại.
4. Kết quả đo kèm ngày, revision, dataset, workload và môi trường nếu có.
5. Kiểm tra tính đúng, quyền và dữ liệu; phân biệt đã đo với chưa đo.

README gốc đã ghi các cơ chế hiện có tại mục Performance Optimization; kết quả đo và hướng tiếp theo tách riêng. Không dùng phần trăm cải thiện hoặc số test của repo Daily Vocabulary cho TutorLink. Không gắn “production ready” chỉ từ test pass hay một lượt load local.


## 7. Triển khai đợt đầu ngày 19/11/2026

Sau rà soát, người dùng cho phép triển khai và cập nhật README. Các thay đổi sau được thực hiện trên working tree local, chưa commit/push/deploy.

### Kết quả đã triển khai

- Homepage dùng `GET /listings?include=tutor_card` để lấy headline, kinh nghiệm, rating count/average ngay trong response ứng viên. Hai section dùng chung một request đang chờ, bỏ các lượt gọi profile/review để dựng từng thẻ. Vẫn chọn nội dung từ tối đa 50 ứng viên; chưa giảm số ứng viên xuống 3 và chưa đo payload/latency mới. Điều kiện review công khai được dùng chung giữa API reviews và aggregate cho thẻ.
- Tasks lọc `all/grading/not-submitted/completed`, sắp xếp `due/newest`, phân trang server 5 item theo FE. Counts độc lập bộ lọc/trang, chỉ thuộc Program được phép truy cập. Không truyền filter/sort vẫn giữ hành vi ID tăng và bao gồm cancelled như cũ. Query lấy file được gộp cho cả trang thay N query từng task.
- Progress dùng Overview `task_counts` và `completed_session_count` trong snapshot chỉ đọc, thay việc tải toàn bộ lịch sử Tasks/Sessions. Nhận xét vẫn phân trang riêng.
- Hook tải dữ liệu giữ nội dung khi refresh cùng loader, hiển thị trạng thái cập nhật; bỏ dữ liệu cũ khi đổi loader/scope, logout hoặc lỗi quyền. Workspace truyền AbortSignal tới Axios. GET/HEAD mặc định timeout 15 giây, caller có thể đặt riêng; không thêm retry tự động cho ghi. Không áp dụng optimistic completion cho nghiệp vụ ghi: vẫn chờ server xác nhận.
- Sửa thứ tự tạo thư mục cache trong Dockerfile.testing trước package discovery, vì image test không build được khi các thư mục runtime chưa tồn tại. Database và dữ liệu local của người dùng không dùng để chạy regression.

### Kiểm chứng

- **Pass — backend PostgreSQL:** preflight 7/27 assertions; regression 767/5.570; D04 77/1.078; race D04 1/893. Tổng **852 test / 7.568 assertions**. Runner ban đầu dừng ở 2 assertion kiểm chính xác shape Overview cũ; đã cập nhật kỳ vọng cho trường count mới, xác nhận fixture Active có 1 completed Session và Cancelled có 0; chạy lại toàn nhóm D04 và race đạt. Nhóm regression và preflight đã đạt trước đó, không chạy lại các nhóm không bị thay đổi hành vi.
- **Pass — backend mục tiêu:** Marketplace/Task tests kiểm option compact, tutor suspend, một query aggregate rating, filter/sort/task counts, một query file_links/trang và outsider bị 404. Chạy lại sau hoàn thiện resource schema.
- **Pass — frontend:** 85 test/22 file; gồm render cả hai section với 1 request, Tasks tổng 1.000 vẫn chỉ gọi trang đang xem, Progress không tải lịch sử, refresh/scope/logout/permission failure, timeout mặc định/override và không thêm timeout cho mutation.
- **Pass — tooling:** typecheck; ESLint các file FE thay đổi; production build Next.js; Pint cho PHP thay đổi. OpenAPI export từ source và parity 139 operations; không thêm route. Redocly hợp lệ, 0 lỗi; 71 warning hiện có (đối chiếu bản HEAD trước sửa cũng 71 warning).
- **Chưa đo:** latency/RPS mới, payload before/after trên trình duyệt thật và full browser E2E trên staging. Không chuyển các số liệu tải ngày 15/11 thành kết quả của patch này.

Lệnh backend dùng Compose project `tutorlink-ux-tests`, `compose.testing.yml`, với code app/tests được mount vào image test. D04 được chuẩn bị bằng `scripts/test-console.php d04:seed` theo runner trước khi kiểm fixture/race. Các log local: `/tmp/tutorlink-ux-backend-tests.log`, `/tmp/tutorlink-ux-backend-final.log`, `/tmp/tutorlink-ux-contract-final.log`. Log chứa dữ liệu tổng hợp trong test, không phải dữ liệu người dùng thực.

Các thay đổi API là bổ sung tùy chọn/trường. Khi phát hành, cập nhật backend trước hoặc cùng frontend để FE có `tutor_card`, `status_counts` và `completed_session_count`. Không cần migration mới cho patch này.

### Phần tiếp theo

Đợt này hoàn tất ba ưu tiên đầu từ phần đánh giá trải nghiệm: homepage, workspace và refresh sau thao tác; bổ sung timeout/hủy các lượt đọc workspace. Email queue, Reverb frontend, đo cache qua browser có cookie và sizing PHP-FPM vẫn là công việc tiếp theo. README chỉ mô tả các cơ chế đã có trong patch và giới hạn kiểm chứng.
