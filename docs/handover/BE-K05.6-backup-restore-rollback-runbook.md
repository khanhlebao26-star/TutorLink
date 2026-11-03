# BE-K05.6 — Runbook backup, restore và rollback

Owner: Khanh  
Reviewer: Hoàng  
Phiên bản runbook: 1.0  
Phạm vi hiện tại: Docker local, project `tutorlink-k05-prod`, PostgreSQL 16.

## 1. Phạm vi và chính sách

Một bộ backup gồm:

| Thành phần | Nội dung                                                  |
| ---------- | --------------------------------------------------------- |
| Database   | Schema, dữ liệu, migrations, outbox và database queue     |
| Storage    | Toàn bộ `storage/app`, gồm file private và public         |
| Image      | Image backend và proxy đúng phiên bản đang chạy           |
| Cấu hình   | Compose, metadata phiên bản, tham chiếu phiên bản secrets |
| Caddy      | Dữ liệu TLS và cấu hình được lưu trong volume             |

`APP_KEY` và secrets tương ứng phải được lưu trong nơi quản lý secrets riêng. Bản restore cần dùng khóa phù hợp với dữ liệu đã mã hóa.

Chính sách ban đầu để reviewer duyệt:

| Hạng mục            | Đề xuất                                                     |
| ------------------- | ----------------------------------------------------------- |
| Lịch backup         | Mỗi ngày và trước mỗi lần deploy có thay đổi dữ liệu/schema |
| Daily backup        | Giữ 7 bản                                                   |
| Weekly backup       | Giữ 4 bản                                                   |
| Backup trước deploy | Giữ ít nhất 14 ngày, cùng image tương ứng                   |
| Image rollback      | Giữ ít nhất hai phiên bản đã kiểm tra tốt                   |
| Mục tiêu RPO        | Không quá 24 giờ khi lịch backup hằng ngày đã hoạt động     |
| Mục tiêu RTO        | 60 phút; cần đo lại bằng diễn tập                           |
| Off-host            | Bản sao mã hóa, nằm ngoài máy chạy ứng dụng                 |

Hiện tại chưa cấu hình lịch tự động và nơi lưu off-host. Hai phần này chờ nhóm chọn hosting và công cụ lưu trữ.

Thư mục backup local dùng trong hướng dẫn:

```text
D:\Dev\Backups\TutorLink\<thời-điểm-backup>
```

Thư mục này chứa dữ liệu riêng tư và có thể chứa TLS private keys. Giới hạn quyền truy cập và lưu bản sao mã hóa ngoài máy. Issue/PR chỉ đính kèm kết quả kiểm tra đã loại bỏ dữ liệu nhạy cảm.

## 2. Chuẩn bị phiên làm việc

Chạy tại thư mục gốc dự án trong CMD:

```cmd
set "K05_PROD=docker compose --env-file .env.production -p tutorlink-k05-prod -f compose.production.yml"
```

Đặt thời điểm backup mới. Giá trị dưới đây là ví dụ, cần thay bằng thời điểm thực tế theo Asia/Saigon:

```cmd
set "K05_RUN=20261103-103000"
set "K05_BACKUP_DIR=D:\Dev\Backups\TutorLink\%K05_RUN%"
```

Kiểm tra thư mục chưa tồn tại:

```cmd
if exist "%K05_BACKUP_DIR%" echo STOP: Hay chon K05_RUN moi, khong ghi de backup cu.
```

Chỉ tiếp tục với một thư mục mới:

```cmd
mkdir "%K05_BACKUP_DIR%"
```

Điều kiện thực hiện:

- Chỉ có một người thao tác backup/deploy tại một thời điểm.
- Không thay đổi Compose hoặc `.env.production` trong lúc backup.
- Xác nhận `RELEASE_TAG` đang trỏ đúng phiên bản chạy.
- Các lệnh DB dưới đây dùng database/user `tutorlink_prod`. Nếu đã đổi, thay bằng giá trị thực tế.
- Kiểm tra từng lệnh thành công trước khi chạy bước kế tiếp.

## 3. Ghi phiên bản đang chạy

```cmd
%K05_PROD% ps
```

Ghi tag và image ID thực tế của hai container:

```cmd
docker inspect --format "{{.Name}} {{.Config.Image}} {{.Image}}" tutorlink-k05-prod-app-1 tutorlink-k05-prod-proxy-1 > "%K05_BACKUP_DIR%\running-images.txt"
```

Ghi thông tin checkout để tham khảo:

```cmd
git rev-parse HEAD > "%K05_BACKUP_DIR%\checkout-commit.txt"
git status --short > "%K05_BACKUP_DIR%\checkout-status.txt"
```

Nếu working tree chưa sạch, Git HEAD không chứng minh toàn bộ source trong image. Image ID thực tế là thông tin quyết định phiên bản đang chạy.

Lưu cấu hình Compose:

```cmd
copy compose.production.yml "%K05_BACKUP_DIR%\compose.production.yml"
```

Ghi thêm vào hồ sơ backup:

- Thời điểm và múi giờ.
- Người thực hiện.
- Lý do backup.
- Phiên bản secrets cần dùng để restore.
- Phiên bản PostgreSQL.
- Kết quả health trước backup.

Không ghi giá trị secret vào hồ sơ chia sẻ.

## 4. Lưu image phục vụ rollback

Tạo tag riêng từ image ID của container đang chạy:

```cmd
for /f %I in ('docker inspect --format "{{.Image}}" tutorlink-k05-prod-app-1') do docker image tag %I tutorlink-backend:backup-%K05_RUN%
```

```cmd
for /f %I in ('docker inspect --format "{{.Image}}" tutorlink-k05-prod-proxy-1') do docker image tag %I tutorlink-proxy:backup-%K05_RUN%
```

Các lệnh `for` trên dành cho CMD tương tác. Nếu đưa vào file `.cmd`, đổi `%I` thành `%%I`.

Lưu image ra archive:

```cmd
docker image save --output "%K05_BACKUP_DIR%\images.tar" tutorlink-backend:backup-%K05_RUN% tutorlink-proxy:backup-%K05_RUN%
```

Lưu image ID của các tag backup:

```cmd
docker image inspect --format "{{.Id}}" tutorlink-backend:backup-%K05_RUN% tutorlink-proxy:backup-%K05_RUN% > "%K05_BACKUP_DIR%\backup-image-ids.txt"
```

Archive image có thể nạp lại bằng `docker image load`. Tham khảo: [Docker image save](https://docs.docker.com/reference/cli/docker/image/save/).

## 5. Backup database và storage nhất quán

### 5.1. Dừng các tiến trình ghi dữ liệu

Bước này tạo thời gian gián đoạn trên stack local đang kiểm tra.

```cmd
%K05_PROD% stop proxy app outbox-worker slack-worker broadcast-worker scheduler reverb
```

Giữ DB chạy:

```cmd
%K05_PROD% ps -a
```

Chỉ tiếp tục khi các tiến trình ứng dụng đã dừng. Không có thao tác ghi từ CLI hoặc tiến trình ngoài stack trong khoảng backup.

### 5.2. Ghi số lượng bản ghi để đối chiếu

```cmd
%K05_PROD% exec -T db psql -U tutorlink_prod -d tutorlink_prod -At -c "SELECT 'users', COUNT(*) FROM users UNION ALL SELECT 'files', COUNT(*) FROM files UNION ALL SELECT 'programs', COUNT(*) FROM programs UNION ALL SELECT 'notification_outbox', COUNT(*) FROM notification_outbox ORDER BY 1;" > "%K05_BACKUP_DIR%\db-counts.txt"
```

### 5.3. Backup PostgreSQL

Tạo custom-format dump bên trong container:

```cmd
%K05_PROD% exec -T db pg_dump -U tutorlink_prod -d tutorlink_prod --format=custom --file=/tmp/k05-%K05_RUN%.dump
```

Chép ra máy host:

```cmd
%K05_PROD% cp db:/tmp/k05-%K05_RUN%.dump "%K05_BACKUP_DIR%\database.dump"
```

Kiểm tra archive có thể đọc được:

```cmd
%K05_PROD% exec -T db pg_restore --list /tmp/k05-%K05_RUN%.dump > "%K05_BACKUP_DIR%\database-toc.txt"
```

`pg_dump` tạo bản backup nhất quán của database. Việc dừng các tiến trình ghi ở trên giúp database và file upload cùng thuộc một trạng thái ứng dụng. Tham khảo: [PostgreSQL 16 — pg_dump](https://www.postgresql.org/docs/16/app-pgdump.html).

### 5.4. Backup file upload

Helper container dùng chung storage, nhưng bỏ qua entrypoint ứng dụng:

```cmd
%K05_PROD% run --rm --no-deps --user root --entrypoint tar -v "%K05_BACKUP_DIR%:/backup" app -czf /backup/storage-app.tgz -C /var/www/html/storage app
```

Tạo danh sách SHA-256 của file để kiểm tra sau restore:

```cmd
%K05_PROD% run --rm --no-deps --entrypoint sh app -c "cd /var/www/html/storage/app && find . -type f -exec sha256sum {} \; | LC_ALL=C sort" > "%K05_BACKUP_DIR%\storage-sha256.txt"
```

### 5.5. Backup trạng thái Caddy

```cmd
%K05_PROD% run --rm --no-deps --entrypoint tar -v "%K05_BACKUP_DIR%:/backup" proxy -czf /backup/caddy-state.tgz -C / data config
```

Archive này có thể chứa private keys. Lưu cùng bộ backup được bảo vệ; không đưa vào Git hoặc evidence công khai.

### 5.6. Ghi checksum

```cmd
certutil -hashfile "%K05_BACKUP_DIR%\database.dump" SHA256 > "%K05_BACKUP_DIR%\database.dump.sha256.txt"
```

```cmd
certutil -hashfile "%K05_BACKUP_DIR%\storage-app.tgz" SHA256 > "%K05_BACKUP_DIR%\storage-app.tgz.sha256.txt"
```

```cmd
certutil -hashfile "%K05_BACKUP_DIR%\caddy-state.tgz" SHA256 > "%K05_BACKUP_DIR%\caddy-state.tgz.sha256.txt"
```

```cmd
certutil -hashfile "%K05_BACKUP_DIR%\images.tar" SHA256 > "%K05_BACKUP_DIR%\images.tar.sha256.txt"
```

Chỉ ghi nhận backup thành công khi dump, copy và tar đều thành công; archive có thể đọc được và các file cần thiết đã có trên host.

### 5.7. Khởi động lại stack nguồn

```cmd
%K05_PROD% start app outbox-worker slack-worker broadcast-worker scheduler reverb proxy
```

Chờ heartbeat phục hồi rồi kiểm tra:

```cmd
curl.exe --ssl-revoke-best-effort --cacert outputs\be-k05.5\caddy-root.crt -i https://localhost:8443/health/ready
```

Kỳ vọng HTTP 200.

Nếu backup thất bại, ghi nhận thất bại và giữ các backup cũ. Việc khởi động lại stack nguồn không được coi là bằng chứng backup thành công.

## 6. Diễn tập restore trên môi trường riêng

### 6.1. Chuẩn bị cấu hình cô lập

Tạo file `compose.restore.yml` ở thư mục gốc:

```yaml
networks:
  default:
    internal: true
```

Network nội bộ giúp môi trường diễn tập không gửi request ra Internet.

Tạo `.env.restore` từ cấu hình/secrets phù hợp với backup:

```cmd
copy .env.production .env.restore
```

Đối với diễn tập ngay sau backup, có thể dùng cấu hình hiện tại nếu secrets chưa thay đổi. Nếu khôi phục backup cũ, lấy secrets đúng phiên bản, đặc biệt là `APP_KEY`.

Sửa các biến sau trong `.env.restore`:

```dotenv
RELEASE_TAG=backup-20261103-103000

APP_DOMAIN=localhost
APP_URL=https://localhost:9443
HTTP_BIND=127.0.0.1
HTTP_PORT=9088
HTTPS_BIND=127.0.0.1
HTTPS_PORT=9443
TLS_CONFIG=tls internal

DB_DATABASE=tutorlink_restore
DB_USERNAME=tutorlink_restore

SESSION_COOKIE=tutorlink-restore-session

MAIL_MAILER=array
BROADCAST_CONNECTION=null
MONITORING_ALERTS_ENABLED=false
MONITORING_ALERTS_SUPPRESSED=true
SLACK_BOT_USER_OAUTH_TOKEN=
SLACK_BOT_USER_DEFAULT_CHANNEL=
```

`RELEASE_TAG` phải khớp tag backup đã tạo. Đặt mật khẩu DB cho môi trường restore. Giữ `APP_KEY` tương ứng với dữ liệu backup.

Tạo biến lệnh cho project restore riêng:

```cmd
set "K05_DR=docker compose --env-file .env.restore -p tutorlink-k05-restore-%K05_RUN% -f compose.production.yml -f compose.restore.yml"
```

Kiểm tra cấu hình:

```cmd
%K05_DR% config --quiet
```

Project mới có database và volume riêng. Runbook áp dụng khi các volume trong Compose vẫn là named volume theo project, không có `external: true` hoặc `name:` trỏ vào volume nguồn.

### 6.2. Kiểm tra backup và nạp image

Tính lại SHA-256 của các archive, đối chiếu với giá trị đã ghi lúc backup. Dừng nếu không khớp.

Nếu image backup chưa có trên máy:

```cmd
docker image load --input "%K05_BACKUP_DIR%\images.tar"
```

### 6.3. Khởi động riêng DB restore

```cmd
%K05_DR% up -d db
```

Chờ DB healthy rồi xác nhận đúng database và user:

```cmd
%K05_DR% exec -T db psql -U tutorlink_restore -d tutorlink_restore -c "SELECT current_database(), current_user;"
```

Kết quả phải là `tutorlink_restore`.

Kiểm tra database đích chưa có bảng ứng dụng:

```cmd
%K05_DR% exec -T db psql -U tutorlink_restore -d tutorlink_restore -At -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';"
```

Với database mới, kết quả phải bằng `0`. Nếu không đúng, kiểm tra lại project/volume trước khi tiếp tục.

### 6.4. Restore database

```cmd
%K05_DR% cp "%K05_BACKUP_DIR%\database.dump" db:/tmp/k05-restore.dump
```

```cmd
%K05_DR% exec -T db pg_restore -U tutorlink_restore -d tutorlink_restore --clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction /tmp/k05-restore.dump
```

Lệnh trên chỉ áp dụng cho DB restore riêng đã xác nhận ở bước trước. `--single-transaction` giúp restore được thực hiện trong một transaction và dừng khi lỗi. Tham khảo: [PostgreSQL 16 — pg_restore](https://www.postgresql.org/docs/16/app-pgrestore.html).

### 6.5. Restore storage

```cmd
%K05_DR% run --rm --no-deps --user root --entrypoint sh -v "%K05_BACKUP_DIR%:/backup:ro" app -c "tar -xzf /backup/storage-app.tgz -C /var/www/html/storage && chown -R www-data:www-data /var/www/html/storage/app"
```

Kiểm tra từng file bằng checksum đã lưu:

```cmd
%K05_DR% run --rm --no-deps --entrypoint sh -v "%K05_BACKUP_DIR%:/backup:ro" app -c "cd /var/www/html/storage/app && sha256sum -c /backup/storage-sha256.txt"
```

Kỳ vọng tất cả file được liệt kê đều `OK`.

Để có bằng chứng hữu ích, bộ dữ liệu diễn tập nên có ít nhất một file upload. Nếu storage rỗng, ghi rõ giới hạn này.

### 6.6. Đối chiếu dữ liệu trước khi chạy worker

```cmd
%K05_DR% exec -T db psql -U tutorlink_restore -d tutorlink_restore -At -c "SELECT 'users', COUNT(*) FROM users UNION ALL SELECT 'files', COUNT(*) FROM files UNION ALL SELECT 'programs', COUNT(*) FROM programs UNION ALL SELECT 'notification_outbox', COUNT(*) FROM notification_outbox ORDER BY 1;" > "%K05_BACKUP_DIR%\restore-counts.txt"
```

```cmd
fc "%K05_BACKUP_DIR%\db-counts.txt" "%K05_BACKUP_DIR%\restore-counts.txt"
```

Kỳ vọng hai file giống nhau.

Kiểm tra lịch sử migration:

```cmd
%K05_DR% run --rm --no-deps app php artisan migrate:status
```

Không chạy migration mới chỉ để làm phép thử restore. Image và schema phải tương ứng với checkpoint backup.

Xóa cache vận hành cũ **chỉ trên môi trường restore riêng** để heartbeat được ghi lại từ tiến trình mới:

```cmd
%K05_DR% run --rm --no-deps app php artisan cache:clear
```

### 6.7. Khởi động và kiểm tra health

```cmd
%K05_DR% up -d --no-build app proxy outbox-worker slack-worker broadcast-worker scheduler reverb
```

Trong diễn tập, để Caddy tạo CA riêng; không đưa TLS private keys của hệ thống nguồn vào môi trường này.

Lấy root certificate mới:

```cmd
%K05_DR% exec -T proxy cat /data/caddy/pki/authorities/local/root.crt > "%K05_BACKUP_DIR%\restore-caddy-root.crt"
```

Kiểm tra app:

```cmd
curl.exe --ssl-revoke-best-effort --cacert "%K05_BACKUP_DIR%\restore-caddy-root.crt" -H "Accept: application/json" -i https://localhost:9443/up
```

Kiểm tra DB và worker sau khi có heartbeat:

```cmd
curl.exe --ssl-revoke-best-effort --cacert "%K05_BACKUP_DIR%\restore-caddy-root.crt" -i https://localhost:9443/health/ready
```

Kỳ vọng cả hai trả HTTP 200.

Kiểm tra thêm:

- Đăng nhập bằng tài khoản test.
- Đọc một dữ liệu nghiệp vụ đã biết.
- Tải lại một file đã upload và đối chiếu nội dung/checksum.
- Không có request gửi ra Slack, SMTP hoặc tích hợp production.

Queued jobs và outbox được khôi phục cùng DB. Khi khôi phục hệ thống thật, phải đánh giá việc chạy lại chúng trước khi bật worker: restore DB không đảo ngược email, Slack hoặc tác động bên ngoài đã xảy ra.

Nếu cần khôi phục chính hệ thống gốc và giữ nguyên TLS identity, archive `caddy-state.tgz` phải được phục hồi vào các volume Caddy của đúng hệ thống đó, khi proxy đang dừng. Bước này không thuộc phép thử DR cô lập ở trên.

## 7. Rollback code

### 7.1. Điều kiện áp dụng

Rollback code chỉ áp dụng khi image cũ tương thích với:

- Schema hiện tại.
- Dữ liệu do phiên bản mới tạo.
- Payload job/outbox còn chờ.
- Định dạng file và cấu hình hiện tại.

| Tình huống                                              | Cách xử lý                                                              |
| ------------------------------------------------------- | ----------------------------------------------------------------------- |
| Lỗi code, schema vẫn tương thích                        | Chạy lại image tốt đã lưu, giữ dữ liệu hiện tại                         |
| Migration thêm trường/bảng nhưng code cũ vẫn hoạt động  | Có thể rollback code sau khi xác nhận tương thích                       |
| Migration xóa/đổi dữ liệu làm code cũ không tương thích | Ưu tiên fix-forward hoặc kế hoạch phục hồi dữ liệu được duyệt           |
| Phải trở về checkpoint dữ liệu trước deploy             | Khôi phục cả DB và storage cùng bộ backup; xác nhận phần dữ liệu sẽ mất |

Không tự động chạy `migrate:rollback` theo mỗi lần rollback code. Một `down()` migration có thể làm mất dữ liệu hoặc chưa phục hồi được biến đổi đã xảy ra.

### 7.2. Chọn phiên bản rollback

Ví dụ chọn image đã lưu trước lần deploy lỗi:

```cmd
set "K05_ROLLBACK_TAG=backup-20261103-103000"
```

Kiểm tra image tồn tại và đối chiếu image ID với hồ sơ backup:

```cmd
docker image inspect --format "{{.Id}}" tutorlink-backend:%K05_ROLLBACK_TAG% tutorlink-proxy:%K05_ROLLBACK_TAG%
```

Nếu cần, nạp archive image tương ứng bằng `docker image load`.

Ghi lại:

- Image ID đang chạy.
- Image ID mục tiêu.
- Lý do rollback.
- Kết luận tương thích schema/job payload.
- Người thực hiện và thời điểm.

### 7.3. Dừng tiến trình và đổi release

```cmd
%K05_PROD% stop proxy app outbox-worker slack-worker broadcast-worker scheduler reverb
```

Trong `.env.production`, sửa `RELEASE_TAG` thành giá trị đã chọn:

```dotenv
RELEASE_TAG=backup-20261103-103000
```

Giữ nguyên database, volume và `APP_KEY` của hệ thống hiện tại khi thực hiện rollback code tương thích.

Tạo lại các container bằng image đã lưu:

```cmd
%K05_PROD% up -d --no-build --force-recreate app proxy outbox-worker slack-worker broadcast-worker scheduler reverb
```

Entrypoint sẽ tạo cache cấu hình/routes/views; worker và scheduler khởi động bằng code trong image mục tiêu.

Không build lại source hiện tại dưới tag của phiên bản cũ.

### 7.4. Kiểm tra sau rollback

```cmd
docker inspect --format "{{.Name}} {{.Config.Image}} {{.Image}}" tutorlink-k05-prod-app-1 tutorlink-k05-prod-proxy-1
```

Image ID phải khớp mục tiêu rollback.

```cmd
curl.exe --ssl-revoke-best-effort --cacert outputs\be-k05.5\caddy-root.crt -H "Accept: application/json" -i https://localhost:8443/up
```

```cmd
curl.exe --ssl-revoke-best-effort --cacert outputs\be-k05.5\caddy-root.crt -i https://localhost:8443/health/ready
```

Sau khi heartbeat có lại:

- Hai endpoint trả HTTP 200.
- DB và các worker/scheduler đều `ok`.
- API nghiệp vụ chính hoạt động.
- File upload vẫn đọc được.
- Không xuất hiện lỗi mới liên tục trong log.
- Không tự động replay toàn bộ failed jobs.

Nếu không đạt, giữ hệ thống trong trạng thái bảo trì và chuyển sang kế hoạch fix-forward hoặc restore checkpoint đã được xác nhận.

## 8. Evidence và tiêu chí hoàn thành diễn tập

| Kiểm tra            | Evidence                                           |
| ------------------- | -------------------------------------------------- |
| Xác định phiên bản  | Tag, image ID nguồn và mục tiêu                    |
| Backup hợp lệ       | Archive đọc được, checksum đã ghi                  |
| Restore DB          | Lệnh restore thành công, số bản ghi đối chiếu khớp |
| Restore storage     | Các file trong manifest kiểm tra SHA-256 đều đạt   |
| Runtime sau restore | `/up` và `/health/ready` HTTP 200                  |
| Kiểm tra nghiệp vụ  | Một luồng đọc dữ liệu và tải file thành công       |
| Rollback            | Image ID sau chạy khớp image mục tiêu              |
| Thời gian phục hồi  | Thời điểm bắt đầu/kết thúc và thời gian thực tế    |

Nếu mới có một phiên bản image, ghi rõ chưa diễn tập rollback giữa hai phiên bản khác nhau. Việc đổi tag của cùng một image không chứng minh rollback phiên bản.

Known limitations hiện tại:

- Chưa có hosting production.
- Nơi lưu backup off-host và lịch tự động chưa chốt.
- Retention, RPO và RTO đang là đề xuất chờ reviewer duyệt.
- Health không thay thế kiểm tra dữ liệu và nghiệp vụ.
- Runbook này áp dụng cho topology Docker Compose hiện tại và PostgreSQL 16.
