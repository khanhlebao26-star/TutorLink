# Báo cáo prerender và coverage frontend - 2026-11-27

## Cập nhật sau review: prerender khung tutor và kiểm tra hiển thị trực tiếp

Trang chủ, `/about`, `/become-a-tutor` và khung `/tutors/[slug]` đều prerender/ISR 300 giây. Tutor dùng `generateStaticParams` lấy tối đa sáu slug khác nhau từ tối đa 50 listing; slug khác render on demand. Nếu API không khả dụng khi build, danh sách tham số là rỗng. Tên, bio, dịch vụ và lịch khả dụng không nằm trong HTML cache; trình duyệt lấy lại chúng từ Laravel.

Trước mỗi lượt truy cập tutor, kể cả lượt dùng shell cache, proxy gọi API public bằng request không kèm thông tin đăng nhập, `cache: no-store` và timeout 5 giây. API trả 403/404 thì trang trả HTTP 404. Header `private, no-store` ngăn cache phía sau bỏ qua bước kiểm tra này. Khi mạng lỗi hoặc API tạm lỗi, shell không có dữ liệu hồ sơ vẫn an toàn và client cho phép thử lại. Luồng chuyển hướng đăng nhập cũ được giữ nguyên.

Đây là chiến lược kết hợp cho mục 6(b): prerender trang tĩnh và khung tutor; lấy mới dữ liệu có thể bị thu hồi. Khác với bản ISR hồ sơ ban đầu, không có snapshot hồ sơ tồn tại trong HTML sau moderation. Đổi lại, nội dung riêng của tutor cần JavaScript; chưa đo hiệu quả SEO/Core Web Vitals.

Frontend đạt **219/219 tests**, statements **80,59%** (3.409/4.230), lines **85,71%** (2.941/3.431); lint, typecheck và build đạt. Build production ghi nhận `/tutors/review-tutor` là SSG với ISR 5 phút. Chrome xác nhận HTML prerender không chứa tên hồ sơ; API rút hồ sơ thì lượt tải tiếp theo trả 404; client cũng loại hồ sơ khi API từ chối. Không có lỗi JavaScript. API trong lượt Chrome là giả lập.

Backend đầy đủ đạt **861/861 tests**, 7.655 assertions và coverage statements **90,41%**. Nhóm tin nhắn riêng đạt 62/62. Lượt đầu dùng bind mount từ macOS gặp timeout khởi tạo tiến trình ở Invitation concurrency; chạy riêng đạt 12/12 và lượt đầy đủ dùng Docker volume đạt toàn bộ, không nới timeout. Code backend không đổi sau lượt kiểm tra đầy đủ này.

Bằng chứng: [summary.json](evidence/review-fixes-2026-11-27/summary.json), [coverage frontend](evidence/review-fixes-2026-11-27/frontend-coverage.txt), [production build](evidence/review-fixes-2026-11-27/build.txt), [log backend đầy đủ](evidence/review-fixes-2026-11-27/backend-full.txt), [coverage backend](evidence/review-fixes-2026-11-27/backend-coverage.txt), [ảnh 404](evidence/review-fixes-2026-11-27/hidden-profile-after.png). Theo dõi commit, PR, CI và merge tại [issue #94](https://github.com/khanhlebao26-star/TutorLink/issues/94). Video demo do nhóm tự làm sau.

## Bản ghi triển khai ban đầu

## Mục tiêu rubric 6(b)

Tiêu chí 6(b) yêu cầu chiến lược prerender cho trang tĩnh và trang tutor, dùng `generateStaticParams`, `revalidate`, và ghi trong README. Báo cáo này tập hợp bằng chứng build, kiểm thử coverage và benchmark local cho thay đổi đó.

## Phạm vi triển khai

- Trang chủ, `/about`, `/become-a-tutor`: `dynamic = "force-static"`, `revalidate = 300`.
- `/tutors/[slug]`: `generateStaticParams` lấy tối đa sáu slug khác nhau từ tối đa 50 listing public mới nhất; `dynamicParams = true` cho các slug khác render on-demand; `revalidate = 300`.
- Profile ban đầu được đọc từ endpoint tutor public ở server rồi truyền xuống client để HTML đầu tiên có nội dung profile. Reviews và những trang listing tiếp theo vẫn tải qua client như trước.
- Không đưa thông tin theo phiên vào HTML ISR. Request profile public không nhận cookie trình duyệt; kiểm tra production cho thấy response HTML giống hệt khi request có hoặc không có session cookie.
- API lỗi khi liệt kê slug làm `generateStaticParams` trả `[]`; lỗi khi tải một profile để lại shell client để trang tải lại. Cả hai trường hợp không làm hỏng production build.
- Không đổi API contract, dữ liệu nghiệp vụ, cơ chế xác thực hay phân quyền.

## Build và kiểm thử

Production build với API khả dụng thành công. Next.js liệt kê `/`, `/about`, `/become-a-tutor` là ISR 5 phút và tạo 5 tutor paths bằng SSG từ dữ liệu public hiện có.

Đã chạy thêm build với API không khả dụng bằng `NEXT_PUBLIC_API_URL=http://127.0.0.1:1 npm run build`. Build vẫn thành công, không prebuild slug tutor nào và giữ route tutor cho on-demand rendering. Sau đó build bình thường được chạy lại thành công.

Frontend regression/coverage chạy bằng `npm run test:coverage -- --reporter=dot`:

- 40 test files, 209 tests pass.
- Statements: **80.52%** (3,390 / 4,210).
- Lines: **85.66%** (2,922 / 3,411).
- Vitest gate: statements **80.01%**, lines **80.01%**.
- `npm run lint`, `npm run typecheck`, và production build đều pass.

Coverage được tạo trên toàn bộ `frontend/src/**/*.{ts,tsx}` theo cấu hình Vitest, không chỉ các file mới thêm.

### Kiểm tra lại sau refactor Tailwind ngày 2026-11-27

Sau đợt thay đổi giao diện, coverage được chạy lại: **210 tests / 40 files** pass, statements **80.57%** (3,405/4,226), lines **85.70%** (2,937/3,427). Hai chỉ số tiếp tục vượt gate 80.01%. Bộ ảnh Before/After và chi tiết regression hiện tại nằm trong [handover evidence tiêu chí 3(a)](evidence/3a-tailwind-migration/README.md).

## Benchmark production trước và sau

Đo trên Next.js production server local ở loopback, Node.js 26.7.0, 12 request tuần tự cho mỗi route. Mỗi mẫu tính thời gian từ lúc gửi request đến khi đọc đủ HTML response. Không phải phép đo browser/Core Web Vitals hay số liệu production. Baseline dùng production build ngay trước prerender; kết quả dùng build ISR.

| Route | Baseline p50 / p95 | ISR p50 / p95 | Baseline HTML | ISR HTML |
| --- | ---: | ---: | ---: | ---: |
| `/` | 6.9 / 106.6 ms | 1.8 / 54.3 ms | 41,974 B | 41,983 B |
| `/about` | 6.0 / 12.7 ms | 1.4 / 4.0 ms | 54,809 B | 54,827 B |
| `/become-a-tutor` | 4.5 / 8.4 ms | 1.1 / 3.3 ms | 47,051 B | 47,069 B |
| `/tutors/vo-minh-quan-24` | 4.3 / 9.2 ms | 1.0 / 5.0 ms | 19,311 B | 31,248 B |

Tutor HTML tăng vì response ban đầu hiện có profile public thay cho loading shell client. Lệnh benchmark có thể chạy lại khi production server đang hoạt động:

```sh
python3 frontend/scripts/benchmark-prerender.py \
  --base-url http://localhost:3100 \
  --tutor-slug vo-minh-quan-24 \
  --samples 12
```

P95 với 12 mẫu nhạy với một request chậm, nên dùng để đối chiếu cùng phép đo local, không suy diễn thành cam kết độ trễ. Số đo p50 của cả bốn route giảm trong lần benchmark này.

## Giới hạn độ mới

`revalidate = 300` là chu kỳ ISR, không phải giới hạn cứng cho độ cũ. Request đầu sau chu kỳ có thể nhận bản cũ trong lúc Next.js tái tạo ở nền. Cache API public phía backend có thể cộng thêm tối đa 60 giây trước khi trang tái tạo thấy thay đổi. Nếu tái tạo thất bại, Next.js giữ bản thành công gần nhất cho đến lần tái tạo sau.

## Tài liệu liên quan

- [Chiến lược prerender và coverage trong README dự án](../../README.md#614-public-page-prerendering-and-fresh-tutor-profiles)
- [Frontend README](../../frontend/README.md)
- [Benchmark runner](../../frontend/scripts/benchmark-prerender.py)
- [Vitest config](../../frontend/vitest.config.mts)
