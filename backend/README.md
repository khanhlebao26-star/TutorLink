# TutorLink backend

Laravel phục vụ API tại `/api/v1` cho frontend Next.js. Backend quản lý phiên đăng nhập Sanctum, hồ sơ gia sư và học viên, dịch vụ, chương trình học, hội thoại, tệp, thông báo và nghiệp vụ quản trị. PostgreSQL là database dùng trong cấu hình Docker của dự án.

## Chạy local bằng Docker Compose

Chạy lệnh từ thư mục gốc của repository. Trong lần thiết lập đầu tiên, tạo `backend/.env.docker` từ [.env.docker.example](.env.docker.example) nếu tệp chưa tồn tại. Điền `APP_KEY` bằng một khóa Laravel mới cho môi trường đó. Có thể tạo giá trị bằng PHP CLI:

```sh
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

```sh
docker compose up -d --build
docker compose exec backend php artisan migrate
```

Frontend ở `http://localhost:3000`, API ở `http://localhost:8000/api/v1`. Trang quản trị Next.js ở cổng 3000 gọi API JSON. Panel Filament của Laravel ở cổng 8000 yêu cầu bật `ADMIN_PANEL_ENABLED` và tài khoản có quyền. Xem [cấu hình local](../docker-compose.yml) và [bảng tích hợp theo màn hình](../frontend/README.md#trạng-thái-tích-hợp).

## Đối chiếu API

- [routes/api.php](routes/api.php) khai báo các route API phiên bản 1. [routes/web.php](routes/web.php) khai báo các route web, gồm xác thực kênh broadcast và giao diện duyệt hồ sơ bằng Blade.
- [Hướng dẫn OpenAPI](../docs/api/README.md) mô tả 139 thao tác hiện có và cách tạo lại bản xuất từ Laravel, gồm yêu thích, hồ sơ học tập và quiz.

## Kiểm thử

CI dùng PHP 8.3. Trên Windows, bộ test đã chạy với PHP CLI 8.5.12. Dùng `php -v` và `php --ini` để kiểm tra đúng phiên bản và tệp cấu hình. Khi chạy Pest trực tiếp, bật `extension=sockets` trong cấu hình PHP CLI để cả tiến trình test con nạp được extension.

Bộ test tích hợp sử dụng cơ sở dữ liệu PostgreSQL riêng vì có các nhóm kiểm tra migration, khóa và ghi đồng thời. Từ gốc repository, chạy bộ test bằng cấu hình Docker sau khi đã tạo `backend/.env.docker`:

```sh
docker compose -p tutorlink-tests -f compose.testing.yml run --build --rm backend-test php scripts/test-suite.php
docker compose -p tutorlink-tests -f compose.testing.yml down
```

Runner kiểm tra danh tính database test trước khi tạo lại bảng và nạp fixture. Xem [test-suite.php](scripts/test-suite.php), [compose.testing.yml](../compose.testing.yml) và [cấu hình CI](../.github/workflows/ci.yml).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
