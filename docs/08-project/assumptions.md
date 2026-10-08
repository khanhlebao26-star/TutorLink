# Assumptions

- Kế hoạch bốn tuần/gates và single-role/single-customer vẫn là baseline; calendar mới chưa được xác nhận.
- A=Khanh, B=Dũng, C=Hoàng theo proposal/backlog; nếu nhóm đổi C owner phải cập nhật task brief/ownership trước giao, không suy ra từ “cho tôi”.
- Local stack dùng Docker khi thiếu PHP/Composer local; task docs không cần cài tool/Boost hoặc sửa application.
- Currency MVP VND exponent0, UI tiếng Việt; không đa tiền tệ/localization runtime.
- Existing schema tái dùng, delta non-destructive; public Tutor slug/media và enum wire vẫn cần review trong slice.

Assumption khác requirement: nếu live repo/nhóm thay đổi một mục, ghi delta và xin quyết định có ảnh hưởng scope trước code. Không ghi assumption thành evidence Pass.
