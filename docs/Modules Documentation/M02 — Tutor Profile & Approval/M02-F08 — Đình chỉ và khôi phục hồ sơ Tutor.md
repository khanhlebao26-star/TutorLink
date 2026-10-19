# M02-F08 — Đình chỉ và khôi phục hồ sơ Tutor

## Trạng thái: Draft  |  Actor: Admin có quyền đình chỉ hồ sơ; khóa account cần thêm quyền đình chỉ account  |  API hiện tại: POST suspend/restore

# Mục tiêu

Đình chỉ một Tutor Profile khi cần kiểm soát an toàn/tuân thủ và khôi phục đúng trạng thái hồ sơ trước đình chỉ khi được phép.

# Quy tắc sản phẩm

* Mọi TutorProfileStatus chưa suspended có thể chuyển sang suspended theo quyền quản trị; lưu trạng thái trước đình chỉ để khôi phục chính xác.  
* Khôi phục chuyển suspended về đúng trạng thái đã lưu; không tự duyệt, không tự đặt thành draft hoặc approved.  
* Nếu UserStatus đang active khi hồ sơ bị đình chỉ, phải khóa account trong cùng transaction. Cần cả admin.tutor\_profiles.suspend và admin.accounts.suspend.  
* Đình chỉ profile làm ẩn Listing, ngăn Tutor tạo/đăng Listing hoặc tạo Program mới, và khóa đăng nhập theo quyết định sản phẩm. Program đang hoạt động không tự hủy; lịch sử được giữ.  
* Khôi phục profile và khôi phục account là hai thao tác riêng. Khôi phục profile không mở khóa account bị đình chỉ độc lập và không tự kích hoạt lại Listing.  
* Mỗi hành động cần reason, actor, timestamp và audit; Admin không được tự cấp quyền từ role.

# Luồng

* Admin có admin.tutor\_profiles.suspend chọn đình chỉ, nhập reason và xác nhận.  
* Backend đọc khóa trạng thái hiện tại, lưu profile state trước đình chỉ, đặt profile suspended và nếu account active thì đổi sang suspended trong cùng transaction với đủ hai permission.  
* Admin khôi phục profile; hệ thống đưa profile về state đã lưu. Nếu account vẫn suspended, thao tác khôi phục profile không thay đổi account.  
* Khôi phục account được thực hiện riêng với admin.accounts.suspend sau khi quyết định phù hợp.

# Tiêu chí chấp nhận

* Profile suspend lưu lại trạng thái trước đó và không làm mất trạng thái quyết định/lịch sử.  
* Nếu account active, profile \+ account suspension thành công cùng transaction hoặc không ghi thay đổi một phần.  
* Thiếu một trong hai quyền cần thiết thì không khóa account hoặc profile một cách không nhất quán.  
* Restore trả về đúng trạng thái trước đình chỉ; không tự approved, không tự mở account và không tự publish Listing.  
* Program active/paused và dữ liệu lịch sử được giữ; Tutor không thể tạo hoạt động mới cần profile approved trong khi suspended.  
* Lý do, actor/time và before/after được ghi audit; stale hoặc lặp action bị từ chối mà không ghi đè trạng thái mới hơn.

# Chênh lệch triển khai cần đồng bộ

* API hiện có action profile suspend/restore dùng suspended\_at và action account riêng dùng status active/inactive. Profile action hiện chưa đổi account status trong cùng transaction; account status target là active/suspended, không phải inactive/cancelled.  
* Cần bảo đảm permission kiểm tra cho thao tác kết hợp, xử lý account đã suspended trước đó, và lưu trạng thái profile gốc theo State Lifecycles.

# Nguồn và phụ thuộc

Business Rules; State Lifecycles; Roles & Actors; Enum Catalog; Project Overview. Phụ thuộc admin.tutor\_profiles.suspend, admin.accounts.suspend, audit và M02-F04.
