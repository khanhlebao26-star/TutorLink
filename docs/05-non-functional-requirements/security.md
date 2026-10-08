# Security

Sanctum first-party cookie, CSRF mutation, HTTPS + HttpOnly/Secure production; không lưu token dài hạn vào localStorage. Verified/account status, role, owner/member, resource state được kiểm server. File private qua backend policy; public không verification document/object key.

Permission matrix phải có owner/member/outsider, unverified/suspended và Admin thiếu từng permission. Ghi 403/404 theo disclosure contract, không tùy màn hình. Rate limit login/reset/resend/message; logs không password/token/document content.

An toàn test là điều kiện tiên quyết: không RefreshDatabase/migrate:fresh trên dev/shared. Config cache có thể bỏ qua env forcing; cần fail-fast effective configuration trước destructive lifecycle. Không chạy suite để “xem có an toàn không” khi chưa chốt target.

Backup/evidence dùng dữ liệu giả; redact cookie/token/credential. Cấu hình production APP_DEBUG=false. Local ports expose không phải cấu hình Internet demo đã an toàn.
