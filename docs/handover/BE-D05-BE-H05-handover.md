# BE-D05 + BE-H05 — Bàn giao backend Phase 5

Owner triển khai: Hoàng (nhận thêm phần BE-D05 của Dũng). Reviewer theo issue: Khanh cho D05, Dũng cho H05 khi có thể review.

Base: `01c073b` (`issue be-k05`), branch hiện tại `codex/2w-be-h05`. Lần pull `be-k05` fast-forward từ `11a03d5`; working tree sạch trước triển khai. Không sửa lịch sử Git hoặc code frontend. Các thay đổi dưới đây chưa commit/push, chưa có PR/remote CI/production evidence.

## BE-D05 — Phạm vi đã triển khai

- Một scope public dùng chung cho Tutor: account Tutor active, không soft-deleted/suspended, email verified; Profile approved/không suspended; identity approved còn hạn, File đúng owner/purpose/private/complete; mọi qualification claim có degree/certificate approved còn hạn tương ứng. Listing thêm active/VND, catalog chain active và không có moderation hold. List/detail/Profile/Listing nhúng/Review cùng gate.
- `GET /api/v1/tutors/{slug}` nhận `listings_page >= 1`, tối đa 10 card/trang, ID giảm dần; thêm `listings_meta` gồm page/per_page/has_more/next_page. Card allow-list gồm slug/title/delivery_mode/city/district/price_from_minor/currency/price_unit/specialization_name. Không dùng DTO Listing đầy đủ cho card nhúng. Slug không đổi khi sửa tên.
- Admin Profile list: mặc định 20, tối đa 50, page hợp lệ; sort allow-list `submitted_desc` (mặc định), `id_desc`. Giữ scope assigned/unassigned/all. Batch latest revision, eager load quan hệ và chọn cột; số query không tăng theo số dòng trong trang. Public Listing có thứ tự ID phụ khi giá bằng nhau.
- Cache **server-side** cho GET catalog/categories, catalog/specializations, listings, listing detail, tutor detail/reviews; chỉ anonymous request không Cookie/Authorization. Cache chỉ body JSON public 200, không cache lỗi hoặc metadata private; request authenticated/cookie/token và mọi API khác dùng `private, no-store`.
- Key `marketplace:v1:<sha256(generation, latest_expired_document, path, sorted_query)>`, TTL 60 giây, header quan sát `X-Marketplace-Cache: HIT|MISS|BYPASS`. Response public dùng `public, no-cache, must-revalidate` và `Vary: Cookie, Authorization` để proxy/browser phải kiểm lại server, không giữ Tutor đã mất eligibility trong 60 giây.
- Migration PostgreSQL thêm singleton `marketplace_cache_versions`, UUID generation và statement triggers trên User/Profile/Listing/catalog/documents/files/specialization pivot/availability/reviews/programs/members/holds. Trigger thay generation **trong transaction nguồn** nên chỉ connection sau commit thấy namespace mới; rollback không gây dùng lại namespace của payload chưa commit. Các query-builder write cũng được bao phủ, không phụ thuộc Eloquent observer. Key có mốc expiry đã tới hạn để giấy tờ hết hạn tự đổi namespace, không cần scheduler. Partial index expiry phục vụ lookup này.
- Cache lỗi hoặc generation chưa có thì đọc DB; không fallback về payload cache cũ. Eligibility vẫn thực thi tại DB khi miss/fallback.

## BE-H05 — Phạm vi đã triển khai

- Composer cài Filament 5.11.1 và require trực tiếp Livewire ^4.0 (lock 4.4.8 sẵn từ K05), giữ Laravel 13.35.0/PHP 8.3. Docker dev/test/production thêm ICU/intl, CI thêm intl/zip; không ignore platform requirements.
- User triển khai `FilamentUser::canAccessPanel`: gate mặc định đóng (`config/admin.php`), chỉ panel `admin`, actor đọc mới từ DB, active/verified/không suspended/deleted, có `admin.dashboard.view`. Không đăng ký PanelProvider, không scaffold màn hình/login/resource Filament; `/admin` và `/admin/login` vẫn 404.
- Tái sử dụng action/policy H02 đã có: AssignTutorProfile, DecideVerificationDocument, DecideTutorProfile, SuspendTutorProfile. `TutorProfilePolicy` dùng chung `AdminAuthorization` và actor đã lock/read mới.
- Tách `AssignReport`, `DecideReport`, `ChangeAccountStatus` khỏi controller. Action nhận User/ID/input, tự validation, reload/lock actor và target, kiểm permission/assignment/version, transaction + audit; không tin quyền của User cũ trong bộ nhớ. Report resolve/dismiss và outbox commit/rollback cùng nhau. Account suspension thu hồi token/session, chặn tự đình chỉ/khôi phục, restore không tự đổi Listing/Profile.
- `ReportPolicy` định nghĩa assign, decide theo assignment hiện tại, và điều kiện assignee. `AdminAuthorization` dùng chung active + verified + role/permission + không deleted/suspended. API gọi chính các action này; Filament tương lai phải gọi cùng action, không ghi model trực tiếp.
- API mới **GET /api/v1/admin/dashboard**, browser session + `admin.dashboard.view`. Chỉ aggregate/metadata: Profile/Report assigned-to-me nếu có view; unassigned nếu có assign; Review visibility counts nếu moderate; pending/failed outbox counts nếu view outbox. Resource không có quyền bị bỏ qua. Trả permission allow-list, không trả identity, File, evidence, payload, raw audit JSONB. Không cache public.
- Bổ sung display settings cho các Pulse recorder đã tắt để dashboard K05 render khi kiểm thử Livewire không lazy; vẫn giữ recorder đó disabled, không bật thu thập dữ liệu mới.

## API/schema delta

- Một operation mới dashboard; các route cũ vẫn tồn tại. Public Profile card DTO/phân trang và Admin Profile pagination/sort đổi như trên. Các error API luôn no-store; dashboard dùng envelope code/message/request_id/errors, guest 401.
- Một migration mới PostgreSQL cache generation/triggers + partial expiry index. Không đổi schema nghiệp vụ/permissions của tài khoản đang có; cần provision `admin.dashboard.view` có chủ đích. Không tự cấp quyền Admin hiện hữu.
- OpenAPI phải xuất từ source theo `docs/api/README.md`; bằng chứng export/parity/lint được ghi cùng kết quả kiểm thử bên dưới.

## Kế hoạch chuyển Admin route sau Gate BE

- Tutor Profile index/detail/assignment, verification-document approve/reject, Profile approve/request-changes/reject/suspend/restore: giữ API hiện hành; Filament Resource gọi action/policy H02; giữ assignment, version và download permission riêng.
- Account suspend/restore: gọi ChangeAccountStatus; không `User::update` từ UI.
- Report list/detail/evidence/assignment/resolve/dismiss: giữ API và evidence DTO H04; assignment/decision dùng action mới. Request evidence luôn kiểm lại quyền/assignment/open, kể cả từ Livewire.
- Review list/detail/hide/restore và audit list/detail: giữ API H04; trước khi làm Filament action cần tiếp tục tái sử dụng policy/DTO/audit, không nối thẳng raw JSONB vào bảng.
- Notification outbox list/replay: giữ API D23 và permission riêng; không cung cấp payload/exception tùy ý qua dashboard.
- `/telescope` và `/pulse` tiếp tục là bề mặt monitoring K05 có permission riêng; không thay bằng quyền dashboard chung.
- Trong checkout không có web Admin UI cũ ngoài monitoring; các màn hình frontend và API chưa bị tắt. Chỉ chuyển khi feature parity, bảo mật và nghiệm thu UI đã có evidence, có bước rollback.

## Giới hạn và việc tiếp theo

- Chưa triển khai public avatar download theo M10; Profile trả `avatar: null`, không phát URL/key bỏ qua policy. Không tuyên bố toàn bộ D06/Files hoặc frontend đã nghiệm thu.
- Generation toàn marketplace dùng một row, invalidation rộng (kể cả thay đổi không ảnh hưởng payload). Đổi lại có tính nhất quán giữa query-builder/action và cache. Có thể giảm hit rate và serialize các writer public-source; số đo đọc đơn lẻ không chứng minh throughput ghi/concurrency 50–100 user. Cần benchmark mixed workload trước production, cân nhắc generation theo nhóm nếu write contention đáng kể.
- Key phụ thuộc `now('UTC')` từ app; production phải đồng bộ clock/NTP. TTL cũ dọn namespace tự nhiên, không quét xóa key hàng loạt.
- Bộ đo 5.000 Listing/20 Tutor là dataset đại diện cho query marketplace, không đáp ứng đầy đủ PC-01 (5.000 User/1.000 Program/100.000 Message/10.000 Task) và không phải load test qua mạng/staging. Chưa chứng nhận SLO production.
- `admin.panel_enabled` cố ý false; bật flag không tự tạo panel. UI Filament/auth/login/assets và parity quyền sẽ triển khai sau Gate BE. Review moderation/evidence/file/outbox không được thay bằng ghi model từ Filament.
- Commit/push, PR và reviewer approval còn chờ. K05 có báo cáo local nhưng các acceptance host/deployment/DR trong bàn giao K05 vẫn chưa được chứng nhận; không suy ra đã hoàn tất production từ code vừa pull.

## Kiểm chứng và bằng chứng

Các kết quả là local Docker/PostgreSQL test, ngày 03/11/2026; không chạy migration vào database ứng dụng.

- **PASS:** 822 test / 7.187 assertions theo bốn nhóm của harness K05: legacy D04 preflight 7/27, regression 737/5.190, fixture D04 77/1.077, D04 race 1/893. Không error/failure/skipped trong JUnit cuối từng nhóm. [Summary](evidence/be-d05-h05/summary.json) và JUnit đi kèm.
- **PASS:** coverage dòng gộp 93,41% (8.302/8.888), [chi tiết](evidence/be-d05-h05/coverage.txt). Các test mới kiểm gate/cache/expiry/rollback/two-connection commit boundary/N+1, direct action so với HTTP, mất quyền, audit failure rollback, dashboard và panel gate.
- **Minh bạch harness:** một invocation coverage ban đầu dừng ở barrier D04 do `pg_stat_activity` giữ statistics snapshot trong transaction; không phải evidence cả invocation đó xanh. Đã giữ [log thất bại](evidence/be-d05-h05/coverage-run-before-barrier-fix.txt), thêm `pg_stat_clear_snapshot()` mỗi vòng và chỉ tiếp tục khi cả parent blocker + `Lock` cùng hiện hữu. Vẫn timeout 10 giây và vẫn thất bại nếu worker không chờ khóa thật. Sau migrate:fresh + fixture trên test DB riêng, [race cuối](evidence/be-d05-h05/d04-race-final.txt) đạt đủ 893 assertions; coverage/JUnit nhóm này được thay bằng lượt cuối rồi merge cùng ba nhóm đã đạt, theo cùng reporter của `scripts/test-suite.php`. Không cộng test rerun vào tổng 822. [PostgreSQL 16 về statistics snapshot](https://www.postgresql.org/docs/16/monitoring-stats.html).
- **PASS:** format Pint dirty; `git diff --check`. [Pint](evidence/be-d05-h05/pint.txt).
- **PASS:** [OpenAPI export](evidence/be-d05-h05/openapi-export.txt), [route parity 123 operations](evidence/be-d05-h05/openapi-parity.txt), [Redocly 2.60.0 lint exit 0](evidence/be-d05-h05/openapi-lint.txt), còn 58 warnings (baseline K05 là 59). Endpoint Profile có annotation source đầy đủ cho object cards và pagination, không chỉnh JSON bằng tay.
- **PASS BUILD ONLY:** [image PHP-FPM production](evidence/be-d05-h05/production-image.txt) build với Composer platform check no-dev; [PHP 8.3.36, intl/zip/pdo_pgsql](evidence/be-d05-h05/production-runtime.json) đều có. [Build log](evidence/be-d05-h05/production-build.txt). Không khởi chạy/deploy production profile, không claim TLS/health/load/DR production.

## Số đo D05 trước/sau

Benchmark test dùng source K05 tại `01c073b` và vendor K05 độc lập, rồi source mới, cùng PHP 8.3/PostgreSQL 16.15 aarch64. Docker VM: 4 CPU, 4.110.307.328 byte RAM; không cấu hình pooler riêng, không chạy app worker trong phép đo. Dataset giả: 20 Tutor có identity hợp lệ, 5.000 Listing active (250/Tutor), 20 bộ catalog. Mỗi route 1 cold + 20 warm request trong Laravel test client, không bao gồm RTT/TLS. Query count là DB query được framework ghi trong request; không dùng dữ liệu riêng thật. JSON gốc có EXPLAIN ANALYZE/BUFFERS cho query Listing/Profile: [trước](evidence/be-d05-h05/benchmark-before.json), [sau](evidence/be-d05-h05/benchmark-after.json).

- Listing search: warm 7 → 2 query; median 15,077 → 1,626 ms; p95 16,210 → 2,661 ms. Cold 7 → 9 query vì thêm generation + expiry lookup; thời gian cold 76,694 → 69,417 ms trong lượt đo này.
- Tutor detail: warm 8 → 2 query; median 60,641 → 1,492 ms; p95 107,239 → 4,495 ms. Cold body khoảng 133.145 → 2.524 byte khi từ 250 Listing đầy đủ xuống 10 card; tên giả khác nhau giữa lượt đo có thể làm byte count dao động nhẹ.
- Catalog categories: warm 3 → 2 query; median 11,159 → 1,299 ms; p95 31,219 → 1,523 ms. Cold 3 → 5 query, 12,268 → 12,406 ms; cache chỉ cải thiện warm path, không tuyên bố mọi request đều nhanh hơn.
- Test N+1 so trang 1 với 20 dòng cho public Listing và assigned Admin Profile: số query bằng nhau. Hai connection độc lập chứng minh generation cache chỉ đổi với connection khác sau commit; rollback và expiry đã có test riêng.

Đây là microbenchmark đọc theo từng route, không phải PC-01 đầy đủ hoặc 50–100 user đồng thời. Chưa chứng nhận SLO p95 trên staging/production hoặc write throughput của generation singleton.

## Nội dung cập nhật issue đề xuất

**BE-D05:** Đã triển khai và kiểm thử local gate account/email/Profile/giấy tờ/Listing/catalog/hold; giới hạn query/pagination, card DTO, cache public theo transaction + expiry, fallback DB và no-store cho private response. Có benchmark trước/sau 5.000 Listing, EXPLAIN và test invalidation/N+1. Cần Khanh review, commit/PR/remote CI; còn public avatar route và load test PC-01/production ngoài phạm vi đã chứng minh. Chưa triển khai production.

**BE-H05:** Đã cài Filament/Livewire tương thích PHP/Laravel, gate panel đóng trước Gate BE; API và business action dùng chung policy/validation/lock/transaction/audit, có dashboard aggregate theo permission/assignment và kế hoạch chuyển route cũ. Bộ test grouped cuối đạt 822 test, coverage 93,41%, OpenAPI 123 operations; image PHP-FPM build đạt. Cần reviewer kiểm permission mapping, commit/PR/remote CI; chưa mở UI Filament hoặc tắt route hiện hành. Chưa đăng comment/đóng issue tự động.
