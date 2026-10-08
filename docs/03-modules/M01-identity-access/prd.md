# PRD Identity and Access

FR-01, BR-01/04. User đăng ký Customer/Tutor với full_name/email/password/date_of_birth, xác minh signed link, login/logout, quên/reset password, đọc/cập nhật /me. Terms/version và verified eligibility phải đối chiếu validation/contract trước sửa, không giả định field đã enforced.

Hiện có routes Auth và FE auth pages; Mailpit nhận email local. Không có bằng chứng mọi ca expiry/CSRF/browser đã hoàn tất chỉ từ suite trước.

AC: tuổi dưới 18/role Admin/email trùng bị chặn; reset phản hồi trung tính và invalid/expired token không đổi password; signed verify hợp lệ/giả/hết hạn; suspended account không login/private; mutation thiếu CSRF bị chặn qua middleware thực; 429 và retry UI hoạt động.

Không scope: multi-role, OAuth, Supabase Auth, đổi password_hash convention. Thay đổi phải cập nhật OpenAPI/types và AC-G1-01/05; không công bố Auth Accepted nếu thiếu browser evidence.
