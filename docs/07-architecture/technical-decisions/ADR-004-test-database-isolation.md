# ADR004 Test database isolation

Status: safety requirement; review P1 chưa được sửa trong lượt docs. Context: suite cũ từng làm sạch DB dev; TestCase hiện ép env SQLite nhưng cache config vẫn có thể trỏ PostgreSQL.

Decision: fast tests dùng SQLite :memory: với effective-config guard trước destructive setup, test cache path riêng; PG constraints/races dùng base harness và DB disposable allow-list riêng. Không chạy migrate:fresh/RefreshDatabase dev/shared.

Consequences: assertion trong DatabaseSchemaTest sau RefreshDatabase quá muộn để bảo vệ. Cần test cached wrong-target fail-fast không chạm dev DB; chỉ chứng minh trên synthetic/disposable fixture. Không test guard bằng cách trỏ database quan trọng rồi chạy destructive suite. Providers bootstrap cũng phải không truy cập target sai trước guard.
