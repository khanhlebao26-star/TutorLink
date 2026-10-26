# M05-F01 · Tạo và chỉnh sửa Program Draft

## PRD chức năng · M05 Program & Invitation · Draft

# 1\. Mục tiêu

Tutor đủ điều kiện tạo một Program Draft nhắm tới đúng một Customer và chỉnh sửa offer trước khi gửi Invitation.

# 2\. Phạm vi và dữ liệu

* Draft gồm target\_customer\_id (wire OpenAPI: customer\_id; DB: programs.customer\_id), mục tiêu/phạm vi dịch vụ, delivery\_mode, agreed\_fee\_minor, currency=VND, fee\_unit và source Listing tùy chọn.  
* Program là thỏa thuận riêng; Listing chỉ làm nguồn tham khảo. Archive/chỉnh Listing không sửa Program offer hoặc snapshot đã gửi.  
* Nếu request tạo Program Draft mới tham chiếu source Listing đang có moderation hold, server từ chối trước khi tạo. Tutor vẫn có thể tạo Draft không tham chiếu Listing đó, nếu đủ eligibility. Hold đặt sau khi Draft tồn tại không tự xóa/sửa Draft hoặc snapshot cũ; gửi Invitation mới từ Draft tham chiếu Listing đang hold bị chặn theo M05-F02.
* Một Program có một Tutor owner và một Customer target; chỉ một Customer member được tạo khi Invitation được accept.

# 3\. Quy tắc

* Tạo Program yêu cầu Tutor account active, email verified, Profile approved, giấy tờ bắt buộc còn hiệu lực theo BR-16, không suspended; Customer target phải là Customer hợp lệ.
* `Program.version` là phiên bản offer của Draft, bắt đầu từ 1 và tăng đúng 1 sau mỗi lần sửa Draft thành công. PATCH Draft nhận `expected_version`; version không khớp trả 409 và không ghi một phần. Dữ liệu tiền là số nguyên VND không âm; không dùng float.
* Khi có Invitation pending, Draft bị khóa đến khi Invitation đạt accepted/rejected/cancelled/expired. Sau trạng thái terminal, Tutor có thể sửa Draft theo version hiện tại hoặc gửi Invitation mới với snapshot/version mới.
* Tạo/sửa Draft không kích hoạt Program, không tạo membership và không cho tạo workspace activity.

# 4\. Tiêu chí chấp nhận

* Tutor đủ điều kiện tạo Draft và nhận Program ID; API trả trạng thái draft.  
* Tutor khác, Customer hoặc outsider không thể xem/sửa Draft.  
* Sai role, amount, currency, delivery\_mode hoặc target bị từ chối bằng lỗi validation.  
* Thay Listing/Profile sau này không thay đổi các giá trị đã lưu trong Draft; snapshot sẽ khóa ở thời điểm gửi.

# 5\. Trạng thái triển khai

* B1 đã đăng ký `GET|POST /api/v1/programs` và `GET|PATCH /api/v1/programs/{program}`. API dùng `customer_id` cho Customer đích, không nhận owner/status/version do client quyết định; Draft bắt đầu ở version 1 và PATCH thành công tăng version sau khi kiểm `expected_version`.
* Danh sách/chi tiết giới hạn theo Tutor owner hoặc Customer member active. Customer chưa là member không đọc được Draft. PATCH kiểm eligibility, trạng thái Draft và Invitation pending; Invitation quá hạn được expire trong cùng transaction, và lỗi PATCH sẽ rollback transition đó.
* Đây là phạm vi B1; gửi/đọc Invitation và Program lifecycle còn thuộc các bước khác của BE-D02.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
