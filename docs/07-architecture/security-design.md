# Security design

Policy kiểm tuple actor role/account + owner/member + resource state + file purpose/visibility. Resource route binding không tự cấp quyền. Public allow-list DTO loại verification/private File metadata; Admin role không bypass permission.

Transaction recheck eligibility ở write boundary để tránh race suspend/accept. Lock order theo BR-09; Admin suspension phải phối hợp cùng thứ tự trước mở race-sensitive Program API. Audit same transaction; async effects after commit.

Sanctum CSRF/cookie/CORS đúng origin, production HTTPS/Secure/HttpOnly; không secrets NEXT_PUBLIC. Local `.env.docker` không đổi trong task docs. Signed verify URL không được sửa host/query làm hỏng signature; test expiry browser riêng.

Test harness phải fail trước RefreshDatabase khi effective connection không đúng, không chỉ assertion trong method sau trait setup. Dùng config cache path test riêng; kiểm providers không chạm dev DB trước guard. PostgreSQL suite base riêng với explicit disposable target allow-list, không tận dụng TestCase ép SQLite rồi gọi là integration PG.
