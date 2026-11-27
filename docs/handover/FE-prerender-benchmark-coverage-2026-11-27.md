# Báo cáo prerender và coverage frontend - 2026-11-27

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

- [Chiến lược prerender và coverage trong README dự án](../../README.md#614-public-page-prerendering-and-tutor-isr)
- [Frontend README](../../frontend/README.md)
- [Benchmark runner](../../frontend/scripts/benchmark-prerender.py)
- [Vitest config](../../frontend/vitest.config.mts)
