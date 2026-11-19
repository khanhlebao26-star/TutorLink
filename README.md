# TutorLink

**Nền tảng kết nối người dạy và học viên, quản lý chương trình học và quá trình học tập.**

Đồ án môn **Laravel** · Giảng viên **Nguyen Huu Quyen** · Lớp **23CSE**

<!-- Bổ sung tên nhóm, họ tên và MSSV thành viên trước khi nộp. -->

TutorLink sử dụng Laravel cho API và nghiệp vụ, Next.js cho ứng dụng web, PostgreSQL cho dữ liệu. Dự án triển khai theo ba vai trò: Học viên, Gia sư và Quản trị viên.

## Mục lục

- [1. Giới thiệu và phạm vi](#1-giới-thiệu-và-phạm-vi)
- [2. Chức năng chính](#2-chức-năng-chính)
- [3. Công nghệ và kiến trúc](#3-công-nghệ-và-kiến-trúc)
- [4. Cài đặt và chạy local](#4-cài-đặt-và-chạy-local)
- [5. Kiểm thử và tài liệu API](#5-kiểm-thử-và-tài-liệu-api)
- [6. Performance Optimization](#6-performance-optimization)
- [7. Kết quả đo và giới hạn](#7-kết-quả-đo-và-giới-hạn)
- [8. Hướng tối ưu tiếp theo](#8-hướng-tối-ưu-tiếp-theo)
- [9. Tài liệu và nguồn tham khảo](#9-tài-liệu-và-nguồn-tham-khảo)

## 1. Giới thiệu và phạm vi

TutorLink hỗ trợ tìm kiếm người dạy, trao đổi nhu cầu, thiết lập chương trình học và theo dõi việc học trong một không gian chung. Người dạy có thể cung cấp dịch vụ trong các lĩnh vực học tập, ngôn ngữ, thể chất và kỹ năng.

Luồng nghiệp vụ chính: **Hồ sơ gia sư → xét duyệt → đăng dịch vụ → trao đổi → tạo chương trình → gửi/chấp nhận lời mời → buổi học và bài tập → tiến độ và đánh giá.**

Lịch rảnh trên hồ sơ là thông tin tham khảo. Chương trình được kích hoạt qua lời mời; hệ thống chưa cung cấp đặt lịch tự động hay cổng thanh toán trực tuyến. Học phí hiện là nghiệp vụ ghi nhận khoản phí và trạng thái thanh toán.

## 2. Chức năng chính

### Học viên

- Đăng ký, xác minh email, đăng nhập, đặt lại mật khẩu và quản lý hồ sơ học tập.
- Tìm dịch vụ theo từ khóa, chuyên môn, hình thức học, thành phố và mức phí; lưu gia sư yêu thích.
- Nhắn tin, gửi tệp, phản hồi lời mời và tham gia chương trình học.
- Xem buổi học, thực hiện bài tập/quiz, theo dõi tiến độ, thông báo lớp và gửi đánh giá khi đủ điều kiện.

### Gia sư

- Hoàn thiện hồ sơ, gửi tài liệu xác minh và theo dõi xét duyệt.
- Quản lý dịch vụ, lịch rảnh, chương trình học và lời mời.
- Quản lý buổi học, bài tập, bài nộp, phản hồi, quiz và tiến độ học viên.
- Ghi nhận học phí, đăng thông báo và trao đổi với học viên.

### Quản trị viên

- Xem dashboard theo quyền được cấp; phân công và xét duyệt hồ sơ/tài liệu.
- Xử lý báo cáo, kiểm duyệt đánh giá và tra cứu audit theo phạm vi quyền.
- Theo dõi outbox, công việc nền và công cụ giám sát khi được cấu hình.

Phạm vi tích hợp API, nội dung tĩnh và dữ liệu minh họa được ghi riêng trong [README frontend](frontend/README.md#trạng-thái-tích-hợp). Sự tồn tại của route không tự chứng nhận mọi yêu cầu nghiệp vụ đã nghiệm thu.

## 3. Công nghệ và kiến trúc

- **Backend:** PHP 8.3, Laravel 13; Sanctum, Fortify, Reverb; Filament 5 cho khu vực quản trị Laravel.
- **Frontend:** Next.js 16, React 19, TypeScript, Tailwind CSS.
- **Dữ liệu:** PostgreSQL 16; cấu hình production mẫu dùng database cho cache, session và queue.
- **Triển khai backend:** Docker Compose, Caddy, PHP-FPM, OPcache; worker và scheduler chạy thành các tiến trình riêng.
- **Kiểm thử và hợp đồng API:** Pest/PHPUnit, PostgreSQL test riêng, Vitest, Playwright, Scramble/OpenAPI, GitHub Actions.
- **Quan sát:** Telescope, Pulse và bộ thử nghiệm Prometheus trong môi trường benchmark.

Trong local, Next.js gọi API Laravel tại `/api/v1`. Laravel chịu trách nhiệm xác thực, phân quyền, kiểm tra dữ liệu và giao dịch nghiệp vụ. Trong cấu hình production backend, Caddy chuyển HTTP tới PHP-FPM và WebSocket tới Reverb. Frontend cần được triển khai riêng; `compose.production.yml` hiện chưa có service Next.js.

```text
TutorLink/
├── backend/                 Laravel API, actions, policies, migrations, tests
├── frontend/                Next.js, API client, components, tests
├── docs/                    Product, domain, API và các báo cáo bàn giao
├── docker/performance/      Bộ đo tải, đối chiếu dữ liệu và giám sát thử
├── docker-compose.yml       Môi trường phát triển local
├── compose.workers.yml      Outbox worker, Slack worker và scheduler local
├── compose.testing.yml      PostgreSQL và backend test cô lập
└── compose.production.yml   Bộ triển khai backend production
```

## 4. Cài đặt và chạy local

Yêu cầu Docker có Docker Compose và Git. Chạy các lệnh từ thư mục gốc repository.

```sh
git clone https://github.com/khanhlebao26-star/TutorLink.git
cd TutorLink
cp backend/.env.docker.example backend/.env.docker
docker compose build backend
docker compose run --rm --no-deps backend php artisan key:generate --show
```

Lệnh cuối in khóa Laravel. Điền khóa vào `APP_KEY` trong `backend/.env.docker`; đặt `LOCAL_ADMIN_EMAIL` và `LOCAL_ADMIN_PASSWORD` cho tài khoản quản trị local. Nếu đã có tệp cấu hình, giữ tệp hiện có. Cấu hình Compose local cung cấp thông tin kết nối PostgreSQL cho container.

```sh
docker compose up -d --build
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose -f docker-compose.yml -f compose.workers.yml up -d
```

`DatabaseSeeder` nạp catalog và tài khoản admin local đã cấu hình. Nó không tự tạo toàn bộ dữ liệu demo chương trình học. `FrontendDemoSeeder` dành cho database test được bảo vệ, không dùng để seed database local thông thường.

- Ứng dụng web: [localhost:3000](http://localhost:3000).
- API: [localhost:8000/api/v1](http://localhost:8000/api/v1).
- Mailpit xem thư local: [localhost:8025](http://localhost:8025).
- pgAdmin local: [localhost:5050](http://localhost:5050).

Xem chi tiết tại [README backend](backend/README.md) và [README frontend](frontend/README.md). Panel Filament cần cấu hình `ADMIN_PANEL_ENABLED` và quyền phù hợp. Cấu hình production, HTTPS và secrets phải dùng môi trường riêng.

## 5. Kiểm thử và tài liệu API

Backend sử dụng PostgreSQL test riêng, có kiểm tra danh tính database trước khi tạo lại schema và nạp fixture:

```sh
docker compose -p tutorlink-tests -f compose.testing.yml run --build --rm backend-test php scripts/test-suite.php
docker compose -p tutorlink-tests -f compose.testing.yml down
```

Kiểm tra frontend với Node.js 22, chạy trong thư mục `frontend/`:

```sh
npm ci
npm run lint
npm run typecheck
npm test
npm run build
```

[OpenAPI đã xuất](docs/api/openapi.json) hiện có **139 thao tác** tại revision được rà soát ngày 19/11/2026. Xem [quy trình tạo và kiểm tra hợp đồng](docs/api/README.md). [API Route Catalog](<docs/Architecture & Technical Design/API Route Catalog.md>) phân biệt route đã đăng ký và route dự kiến.

Kết quả kiểm thử phải gắn với revision và môi trường thực hiện. Các báo cáo bàn giao là bằng chứng lịch sử; README không gắn nhãn toàn bộ test hiện tại đã pass khi chưa chạy lại.

## 6. Performance Optimization

Phần này ghi **cơ chế đã có trong mã TutorLink**. Các đề xuất chưa triển khai nằm riêng ở mục 8. [Bản ghi tối ưu server](docs/handover/TutorLink-server-optimization-2026-11-19.md) giải thích từng cơ chế, vị trí mã và giới hạn kiểm chứng.

### 6.1. Giảm truy vấn quan hệ lặp

Marketplace tải trước hồ sơ gia sư, người dùng và cây chuyên môn bằng eager loading, đồng thời chỉ lấy các cột cần cho quan hệ. API giới hạn tối đa 50 dịch vụ mỗi trang. Cách này tránh truy vấn từng quan hệ riêng cho từng item khi tạo response.

**Mã:** [ListingController](backend/app/Http/Controllers/Api/V1/ListingController.php), [CatalogController](backend/app/Http/Controllers/Api/V1/CatalogController.php).

### 6.2. Cache response marketplace công khai

Middleware cache JSON thành công cho các route public được cho phép, với TTL 60 giây. Request có Cookie/Authorization hoặc dữ liệu cá nhân đi theo nhánh `private, no-store`.

Cache key kết hợp đường dẫn, bộ lọc, generation và mốc tài liệu xác minh hết hạn. Trigger PostgreSQL đổi generation cùng giao dịch ghi để các thay đổi liên quan làm cache cũ mất hiệu lực. Generation và mốc hết hạn được đọc trong cùng một câu SQL.

**Giới hạn:** cache HIT vẫn phải đọc PostgreSQL để kiểm version; đây chưa phải đường đọc hoàn toàn không truy vấn DB. Redis đã được thử cho payload cache trong benchmark, chưa là service mặc định của bộ production.

**Mã:** [MarketplaceCacheControl](backend/app/Http/Middleware/MarketplaceCacheControl.php), [PublicCatalogCache](backend/app/Support/PublicCatalogCache.php), [migration invalidation](backend/database/migrations/2026_11_03_135735_create_marketplace_cache_versions.php).

### 6.3. Chỉ mục tìm kiếm và truy vấn trang sâu

Migration tạo GIN index với `pg_trgm` trên tiêu đề/mô tả của dịch vụ active, VND để hỗ trợ tìm chuỗi con. Index được tạo concurrent. Khi offset từ 20.000, controller dùng tập ID phù hợp trong CTE để chia sẻ phần lọc giữa lấy trang và đếm tổng, sau đó tải quan hệ.

**Giới hạn:** vẫn giữ exact total và OFFSET; chi phí chưa biến thành hằng số. Lợi ích phụ thuộc dữ liệu, từ khóa, độ chọn lọc và execution plan.

**Mã:** [migration GIN](backend/database/migrations/2026_11_14_154208_add_active_listing_text_search_index.php), [ListingController](backend/app/Http/Controllers/Api/V1/ListingController.php).

### 6.4. Tổng hợp số liệu tại database

Dashboard admin dùng `GROUP BY` và `COUNT` để tổng hợp số lượng theo trạng thái, có lọc phạm vi và quyền trước khi trả dữ liệu. Cách này không cần tải tất cả bản ghi về PHP để tự đếm. Dashboard vẫn có nhiều truy vấn theo nhóm quyền và hai truy vấn đếm outbox riêng.

**Mã:** [ReadAdminDashboard](backend/app/Actions/Admin/ReadAdminDashboard.php).

### 6.5. Xử lý thông báo và tích hợp ở tiến trình nền

Outbox lưu sự kiện cùng giao dịch nghiệp vụ; worker xử lý số lượng giới hạn, dùng `FOR UPDATE SKIP LOCKED`, retry và cơ chế chống tạo thông báo trùng. Broadcast và Slack có worker queue riêng, scheduler chạy riêng. Những công việc này được tách khỏi luồng xử lý HTTP chính.

**Giới hạn:** email xác minh và quên mật khẩu còn gửi theo luồng đồng bộ; không mô tả toàn bộ email đã được queue.

**Mã:** [ProcessNotificationOutbox](backend/app/Console/Commands/ProcessNotificationOutbox.php), [WorkNotificationOutbox](backend/app/Console/Commands/WorkNotificationOutbox.php), [production Compose](compose.production.yml).

### 6.6. Tối ưu runtime production

Image backend production cài dependency `--no-dev`, tạo autoloader tối ưu và bật OPcache. Khi khởi động, entrypoint cache config, route và view. Caddy nén zstd/gzip; worker chạy riêng với chính sách restart. Đây là cơ chế trong cấu hình triển khai, không phải bằng chứng về công suất production.

**Mã:** [Dockerfile.production](backend/Dockerfile.production), [entrypoint](backend/docker/production/entrypoint.sh), [php.ini](backend/docker/production/php.ini), [Caddyfile](backend/docker/production/Caddyfile).

### 6.7. Giảm số lần gọi API lặp

API client marketplace gộp các request giống nhau đang chờ. Polling dừng khi tab ẩn/offline, không chạy chồng lượt và có backoff. Badge tin nhắn dùng endpoint unread-count riêng, chu kỳ 15 giây; chat dùng chu kỳ mặc định 10 giây. Đây là tối ưu lượng request gửi tới server, không phải thay đổi thiết kế giao diện.

**Mã:** [marketplace client](frontend/src/lib/data/marketplace.ts), [use-polling](frontend/src/lib/hooks/use-polling.ts), [use-unread-messages](frontend/src/lib/hooks/use-unread-messages.ts).

### 6.8. Gộp dữ liệu thẻ trang chủ trong một response

`GET /listings?include=tutor_card` bổ sung headline, kinh nghiệm và thống kê rating công khai. Hai khu vực dịch vụ/gia sư nổi bật dùng chung request ứng viên, không gọi thêm profile hoặc danh sách review cho từng thẻ. Rating được aggregate theo nhóm, dùng cùng điều kiện hiển thị với API reviews. Request mặc định không nhận dữ liệu mở rộng. Hiện vẫn chọn nội dung từ tối đa 50 ứng viên; chưa tuyên bố giảm payload hoặc latency theo phần trăm.

**Kiểm chứng:** component test render cả hai khu vực với **1 request dữ liệu**, không gọi profile/reviews; backend test kiểm aggregate theo nhóm và tutor bị đình chỉ không xuất hiện.

### 6.9. Workspace tải theo nhu cầu và giữ nội dung khi làm mới

Tasks lọc/sắp xếp tại backend và chỉ tải **5 bài/trang**. Bộ đếm trạng thái vẫn phản ánh toàn Program; file đính kèm của trang được lấy bằng một query nhóm. Progress lấy `task_counts` và `completed_session_count` từ Overview thay vì tải toàn bộ lịch sử Tasks/Sessions.

Khi làm mới cùng nguồn dữ liệu sau thao tác, FE giữ nội dung và hiển thị trạng thái cập nhật. Đổi nguồn/phạm vi, logout hoặc lỗi quyền sẽ không giữ nội dung cũ. Workspace truyền AbortSignal xuống các lượt đọc để hủy khi không còn cần; API client đặt timeout mặc định 15 giây cho GET/HEAD, không tự gửi lại lệnh ghi.

**Kiểm chứng:** test Tasks với tổng 1.000 bài xác nhận chỉ một lượt gọi trang 5 item; test Progress xác nhận không gọi lịch sử Tasks/Sessions; hook test kiểm giữ nội dung khi refresh, lỗi quyền và loại bỏ kết quả scope cũ.

## 7. Kết quả đo và giới hạn

**Kiểm tra triển khai 19/11/2026 (working tree local):** các nhóm backend đạt **852 test / 7.568 assertions** trên PostgreSQL test cô lập; frontend đạt **85/85 test**, typecheck, lint các file thay đổi và production build. OpenAPI được tạo lại từ code, giữ 139 operations. Các test xác nhận giảm fan-out, phân trang, aggregate và vòng đời tải dữ liệu; chưa phải phép đo latency/RPS mới hay chứng nhận triển khai production.

Báo cáo [hiệu năng 14–15/11/2026](docs/handover/TutorLink-performance-audit-2026-11-14.md) lưu các thử nghiệm local với dữ liệu tổng hợp. Lượt mixed **100 RPS trong 600 giây** nhận thành công 60.000 request, đạt **99,99 RPS**, p95 **55 ms**, p99 **199 ms**, không lỗi và không lượt bị bỏ. Đối chiếu 3.001 Session, gồm fixture smoke, nhất quán với biên nhận và audit.

Máy phát tải và ứng dụng dùng chung tài nguyên Docker. Các thử nghiệm tải cao hơn chưa đạt mục tiêu; **chưa nghiệm thu 1.000 request thành công/giây trong 60 phút hoặc production capacity**. Kết quả 100 RPS cũng chưa chứng minh toàn bộ hành trình người dùng, mọi API hoặc tải đồng thời của ứng dụng Next.js.

Bộ đo và điều kiện nghiệm thu: [docker/performance](docker/performance/README.md).

## 8. Hướng tối ưu tiếp theo

Các mục dưới đây là **đề xuất**, chưa được tính là thành quả đã triển khai:

1. Queue email xác minh/đặt lại mật khẩu với worker nghe đúng queue, retry và kiểm tra hạn token.
2. Tách chính sách cache catalog ít thay đổi khỏi cache dữ liệu marketplace biến động; bổ sung chống nhiều request cùng dựng lại một key hết hạn.
3. Đo chi phí generation lookup và phạm vi invalidation; chỉ thay đổi sau khi bảo đảm ẩn dịch vụ, đình chỉ và hết hạn tài liệu vẫn có hiệu lực đúng lúc.
4. Gộp hai truy vấn đếm outbox nếu profiling cho thấy đáng kể; chỉ cache số liệu dashboard sau khi xác định độ trễ dữ liệu và phạm vi quyền chấp nhận được.
5. Đo plan tìm kiếm, tài nguyên và hàng đợi PHP-FPM trên staging với máy phát tải riêng; điều chỉnh theo số liệu.
6. Tích hợp Reverb ở frontend cho chat/badge, có reconnect/resync và polling dự phòng. Workspace Tasks/Progress đã chuyển sang phân trang/tổng hợp tại mục 6.9.

Không chuyển session sang Redis chỉ bằng đổi biến môi trường: cơ chế realtime hiện yêu cầu database session. Không bỏ khóa, version, audit hoặc idempotency để đổi lấy tốc độ.

## 9. Tài liệu và nguồn tham khảo

- [Mục lục tài liệu TutorLink](docs/README.md).
- [Bản ghi tối ưu server và đối chiếu tham khảo](docs/handover/TutorLink-server-optimization-2026-11-19.md).
- [Báo cáo kiểm thử và đo hiệu năng đã lưu](docs/handover/TutorLink-performance-audit-2026-11-14.md).
- [Daily Vocabulary](https://github.com/AkioCkist/daily_vocabulary/tree/3c0ea06ccaebda687c662fa14245afcb0829a5c6): tham khảo cách bố trí README và cách trình bày vấn đề, giải pháp, bằng chứng tối ưu. Công nghệ, cơ chế và kết quả của TutorLink được đối chiếu từ chính mã nguồn dự án; không sử dụng số liệu của repo tham khảo làm kết quả TutorLink.
