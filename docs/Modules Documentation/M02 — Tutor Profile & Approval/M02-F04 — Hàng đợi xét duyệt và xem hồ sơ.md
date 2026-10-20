# M02-F04 — Hàng đợi xét duyệt và xem hồ sơ

## Trạng thái: Draft  |  Actor: Admin được phân công  |  API hiện tại: GET /api/v1/admin/tutor-profiles\[/{id}\]

# Mục tiêu

Cung cấp cho Admin được giao xử lý khả năng tìm hồ sơ đã gửi, đọc dữ liệu cần cho việc xét duyệt và xem bằng chứng riêng tư đúng phạm vi.

# Quy tắc sản phẩm

* Chỉ Admin có quyền admin.tutor\_profiles.view và được giao hồ sơ/vụ việc tương ứng mới xem được chi tiết.  
* Hàng đợi quyết định bao gồm hồ sơ hoặc revision đang submitted; changes\_requested chỉ quay lại hàng đợi sau khi Tutor gửi lại. Với revision của Profile approved, màn hình review phân biệt rõ bản công khai hiện hành và bản chờ duyệt.
* Quyền xem hồ sơ, quyền đưa ra quyết định và quyền tải verification document độc lập; Admin role không đồng nghĩa với mọi quyền.  
* Tài liệu xác minh không được lộ trong list/public profile. Chi tiết chỉ cung cấp dữ liệu phục vụ review theo phạm vi được phép; tải file cần admin.verification\_documents.download.  
* Truy cập hồ sơ/bằng chứng thuộc dữ liệu kiểm toán và phải được ghi nhận.

# Luồng

* Admin mở danh sách hồ sơ trong phạm vi được giao; UI hiển thị trạng thái và thông tin đủ để chọn hồ sơ.  
* Admin mở chi tiết để xem hồ sơ và trạng thái hiện tại. Nút decision chỉ hiện khi actor có quyền và trạng thái cho phép.  
* Bằng chứng chỉ tải được khi có quyền riêng và assignment hợp lệ.

# Tiêu chí chấp nhận

* Không có quyền xem hoặc không thuộc scope assignment thì không thể truy cập danh sách/chi tiết bằng cách đổi ID hay query.  
* Hồ sơ/revision chưa submitted không xuất hiện trong hàng đợi quyết định.
* Không có quyền download thì response không chứa nội dung bằng chứng hoặc URL tải có thể dùng.  
* List/detail phản ánh status và dữ liệu mới nhất; thao tác trên dữ liệu stale không được ghi đè quyết định mới hơn.

# Chênh lệch triển khai cần đồng bộ

* API hiện dùng status filter pending/changes\_requested/active/rejected và chỉ trả hồ sơ có submitted\_at. Domain target dùng draft/submitted/changes\_requested/approved/rejected/suspended.  
* API hiện kiểm tra permission chung, chưa thể hiện ràng buộc assignment của hồ sơ trong handler. Cần bổ sung policy/scope tương ứng Roles & Actors.  
* API hiện chỉ đưa verification\_documents vào chi tiết khi có quyền download; cần xác nhận cách tách metadata xem được khỏi thao tác download và cách kiểm assignment.

# Nguồn và phụ thuộc

Roles & Actors; Enum Catalog; Domain Model; State Lifecycles. Phụ thuộc Admin permissions, audit và M02-F02.

F03 — Phạm vi hàng đợi và chi tiết  
Nguồn scope là tutor\_profiles.assigned\_admin\_id. Người có admin.tutor\_profiles.assign xem được metadata tối thiểu để giao/chuyển/thu hồi, nhưng muốn đọc chi tiết phải có admin.tutor\_profiles.view và assignment hiện tại. Duyệt/yêu cầu sửa/từ chối cần admin.tutor\_profiles.decide và assignment hiện tại; tải giấy tờ còn cần admin.verification\_documents.download. Lọc unassigned/mine/all phải áp dụng trước pagination/count; assignment mutation gửi assignment\_version hiện đọc được. Không để assignment permission trả nội dung hồ sơ/giấy tờ.  
Open dependency for assignment UI: `GET /api/v1/admin/users` remains in the target Catalog but is not registered yet. Define its permission, scope and minimal DTO before implementation or UI integration. Assignment requests still send assigned\_admin\_id, which the server validates for active status and required profile-processing permissions.
