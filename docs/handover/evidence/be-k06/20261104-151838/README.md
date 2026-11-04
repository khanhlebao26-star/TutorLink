# BE-K06 evidence: 20261104-151838

Baseline `fb54886617524e43727705f7f6ee86bed4135ef9`; working tree chưa commit được nhận diện bởi `source-manifest.json` và `test-changes.patch`.

## Các lệnh đã dùng

PowerShell tại repository root. Baseline build từ Dockerfile.testing hiện hành, image ID tại `baseline/image.txt`.

```powershell
docker compose -p tutorlink-k06-test -f compose.testing.yml build backend-test
$run = '20261104-151838'
$evidence = Join-Path $PWD "docs/handover/evidence/be-k06/$run"
$tests = Join-Path $PWD 'backend/tests'

# Baseline: test files trong image trước khi bổ sung.
docker compose -p tutorlink-k06-test -f compose.testing.yml run --rm --volume "${evidence}/baseline:/evidence" backend-test

# Focused sau bổ sung; mount test files hiện hành read-only.
docker compose -p tutorlink-k06-test -f compose.testing.yml run --rm --volume "${evidence}:/evidence" --volume "${tests}:/var/www/html/tests:ro" backend-test vendor/bin/pest tests/Feature/SessionApiTest.php tests/Feature/SessionTransitionApiTest.php --display-warnings --fail-on-warning --colors=never --log-junit /evidence/task3-session-junit.xml

# Full suite cuối: cùng image/source scope, test files mới.
docker compose -p tutorlink-k06-test -f compose.testing.yml run --rm --volume "${evidence}/final:/evidence" --volume "${tests}:/var/www/html/tests:ro" backend-test

# Live smoke: chỉ --slack sau khi xác nhận channel thử.
docker compose -p tutorlink-src -f docker-compose.yml -f compose.workers.yml exec -T backend php scripts/be-k06-smoke.php --slack
```

Test runner mặc định chạy `php scripts/test-suite.php --coverage` trên PostgreSQL test riêng, copy build/reports ra /evidence/coverage và giữ exit code. PowerShell lưu stdout/stderr và `$LASTEXITCODE` vào các file log/exit tương ứng.

## Kết quả

- `baseline/`: 822 tests pass; `final/`: 827 tests pass. Source denominator 8.888 không đổi.
- `test-coverage-summary.json`: toàn source và mapping/danh sách file từng module.
- `final/case-inventory.csv`: 827 case; `final/failed-tests.csv`: chỉ header vì không có test fail.
- `task3-session-*`: 44 tests/317 assertions.
- `smoke.json`: 25 request checks, 14 assertions, fixtures IDs, outbox và receipt Slack thật.
- `smoke-test-environment-guard.json`: expected refusal trong test environment; exit 1 đúng kỳ vọng.
- `slack-permalink.json`: permalink message thật.
- `smoke-initial-attempt.json`, `smoke-missing-filament.json`: dừng trước khi tạo fixture do runtime thiếu class.
- `smoke-json-object-order-attempt.json`: observer dừng do thứ tự object keys, không phải lỗi replay nghiệp vụ.
- `runtime-*`: lỗi dependency/intl ban đầu và kết quả rebuild/composer install cuối.
- `health-final.txt`, `migrations-final.txt`, `runtime-final.txt`: trạng thái cuối.
- `pint-*-final.*`: format pass. Các log formatter thử ban đầu được giữ để truy vết; dùng kết quả cuối làm kết luận.
- `evidence-scan.json`: quét mẫu credential thông dụng; `secret-literal-scan.json`: đối chiếu literal secret môi trường mà không lưu giá trị.

HTML/serialized coverage được chuyển sang `outputs/be-k06/20261104-151838/{baseline,final}/coverage/` (Git ignored); JUnit/Clover/text giữ ở đây.

Không commit/push/PR/bình luận GitHub trong đợt này. Báo cáo chính `docs/handover/BE-K06-test-report.md`; nội dung tự đăng `docs/handover/BE-K06-issue-update.md`.
