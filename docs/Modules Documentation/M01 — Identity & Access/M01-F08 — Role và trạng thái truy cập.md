# M01-F08 — Role và trạng thái truy cập tài khoản

Trạng thái: Draft    
Actor: Customer, Tutor, Admin và các API cần xác thực    
Phạm vi: chính sách xuyên các luồng M01; không thay thế quyền nghiệp vụ M02–M10

# Mục tiêu

Giữ role tài khoản cố định và bảo đảm trạng thái tài khoản được thực thi tại backend trên login lẫn private API.

# Quy tắc

* Một tài khoản có đúng một role: customer, tutor hoặc admin. Chỉ Customer/Tutor tự đăng ký; Admin do nội bộ tạo.  
* Guest không phải role. Request không có session chỉ được truy cập phần công khai được module tương ứng cho phép.  
* UserStatus active là trạng thái cho phép các thao tác yêu cầu tài khoản hoạt động. suspended không được đăng nhập hoặc thực hiện các thao tác đó. deactivated thuộc quy trình vô hiệu hóa riêng, không dùng thay suspended; chưa mở quy trình deactivation mới trong MVP.  
* Đình chỉ tài khoản khác với đình chỉ Tutor Profile. Profile suspension chặn hành động Tutor theo Business Rules nhưng không tự hủy Program đang chạy hoặc xóa lịch sử.  
* Role/status do server kiểm soát. UI ẩn thao tác không thay thế kiểm tra backend.  
* Admin chỉ có quyền được cấp; không được suy diễn mọi quyền chỉ từ role admin.

# Tiêu chí chấp nhận

* Payload đăng ký không thể tạo Admin; payload /me không thể đổi role/status.  
* UserStatus suspended bị từ chối đăng nhập và các thao tác yêu cầu tài khoản hoạt động; từng private API kiểm tra điều kiện UserStatus theo quy tắc nghiệp vụ của module sở hữu API.  
* Account đã khôi phục hoạt động lại theo đúng permission/decision được cấp; khôi phục Tutor Profile không tự khôi phục account bị suspend.  
* Request không đăng nhập chỉ đọc dữ liệu public; không truy cập private data qua ID đoán được.  
* Các endpoint tiếp tục kiểm ownership/member/resource state theo module sở hữu tài nguyên.

# Ranh giới và ghi chú triển khai

M02 sở hữu quyết định duyệt/đình chỉ Tutor Profile và Admin permissions. M03 sở hữu điều kiện công khai Listing; M04–M10 sở hữu member/owner/resource policies của dữ liệu tương ứng. M01 chỉ cung cấp identity, role và trạng thái tài khoản nền.

Mô hình sản phẩm dùng UserStatus active/suspended/deactivated. deactivated dành cho quy trình riêng, chưa mở trong MVP. Một số runtime guard còn dùng inactive/cancelled; đây là chênh lệch triển khai cần đồng bộ với API schema, middleware, Admin controller, response code và dữ liệu hiện có; không coi các giá trị cũ là enum sản phẩm.
