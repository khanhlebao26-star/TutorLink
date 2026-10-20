# M03-F10 · Quản trị viên ẩn Tin dịch vụ

## PRD chức năng · M03 Catalog & Marketplace · Quy tắc hide/restore đã chốt; API chưa triển khai

# 1\. Mục tiêu và actor

Cho Admin có quyền kiểm duyệt Listing ẩn nội dung vi phạm chính sách khỏi bề mặt công khai, đồng thời giữ nguyên quyền sở hữu của Tutor và lịch sử liên quan.

# 2\. Yêu cầu từ Product

* Trong MVP, `admin.listings.moderate` cấp phạm vi toàn bộ Listing cho Admin nội bộ active có quyền này; không có assignment theo Listing, Category, địa bàn hoặc assignment Report. Thu hồi permission có hiệu lực ở request tiếp theo.
* Admin không sửa nội dung Listing, không impersonate Tutor và không dùng quyền ẩn để quản lý Listing thay chủ sở hữu.  
* Listing bị ẩn khỏi public search, direct detail và danh sách Listing trên public Tutor profile; không tự hủy Program hoặc snapshot.  
* Thao tác moderation cần có actor, thời điểm, phạm vi và lý do để có thể audit.

# 3\. Mô hình moderation đã chốt

Product đã chọn moderation hold riêng để ghi lý do và nguồn tạm ẩn; `hidden` không phải giá trị mới của ListingStatus hoặc ListingStatusFilter. Khi Admin hide Listing đang `active`, cùng giao dịch đặt hold và chuyển `ListingStatus` thành `inactive`, đồng thời ghi nhận rằng trạng thái inactive này do moderation tạo ra và Listing trước đó đang active. Hide Listing đã `inactive` vì Tutor tự tạm dừng có thể đặt hold nhưng không ghi quyền tự kích hoạt lại; draft/archived không chuyển sang inactive bằng hide. Hold còn hiệu lực làm Listing không công khai và chặn Tutor publish lại, nhưng không cấm Tutor ghi nhận quyết định tự tạm dừng. Quyền Admin đích là `admin.listings.moderate` với phạm vi toàn bộ Listing trong MVP; không có quyền sửa nội dung hoặc giả danh Tutor. Hide và restore đều yêu cầu lý do, ghi actor/thời điểm/scope vào audit; chỉ Admin đang có quyền mới gỡ hold.

# 4\. Thông báo, lý do và kết quả API đã chốt

* Hide thành công phát một notification trong ứng dụng cho Tutor owner sau commit: Listing bị ẩn, lý do có thể chia sẻ và hướng liên hệ hỗ trợ. Không lộ reporter hoặc evidence riêng tư. Khiếu nại trong MVP đi qua kênh hỗ trợ do vận hành công bố và được xử lý thủ công; không tạo route appeal hoặc SLA tự động. Kết quả xem xét được ghi vào audit và, nếu gỡ hold, dùng đúng route restore. Restore thành công phát notification hiện có, nêu Listing đã active hay vẫn inactive do Tutor tự pause.
* Hide/restore đều nhận `reason_code` thuộc ListingModerationReasonCode và `reason` giải thích không rỗng. Hide dùng `policy_violation` hoặc `other`; restore dùng `issue_resolved`, `decision_reversed` hoặc `other`. Admin không gửi owner/status/actor/timestamp trong payload; server xác định chúng.
* Success DTO chỉ cho Admin gồm `listing_id`, `listing_status`, `hold_active`, `hold_id`, `reactivated`, `acted_at`, `reason_code` và `reason`; response không chứa reporter/evidence. Public response không lộ reason/audit/hold. Input sai trả 422, không có permission trả 403, Listing không tồn tại trả 404, transition/gate xung đột trả 409; request bị từ chối không ghi audit hành động thành công hoặc notification thành công. Security log vẫn có thể ghi lần truy cập bị từ chối.
* Nếu Listing được hide đang active, restore chỉ thành công khi User active, email verified, Tutor Profile approved/không suspended, catalog chain active và Listing còn đủ dữ liệu công khai. Thiếu bất kỳ gate nào: trả 409, giữ hold cùng trạng thái inactive, không phát notification restore; Admin thử lại sau khi gate đạt. Nếu Tutor đã tự pause trước hoặc trong hold, Admin có thể gỡ hold khi thiếu public gate nhưng Listing giữ inactive để Tutor tự publish khi đủ điều kiện; notification nói đúng kết quả.
* Listing có hold không được làm nguồn cho Program Draft mới hoặc Invitation mới; các thao tác này bị chặn trước khi ghi snapshot. Tutor vẫn có thể dùng Listing khác hợp lệ. Program/snapshot đã tồn tại không tự hủy hay sửa. Theo BR-18, Invitation pending có trước hold vẫn giữ nguyên dữ liệu nhưng accept bị chặn bằng 409 trong lúc hold còn hiệu lực; nếu hold được gỡ trước expiry và Customer/Tutor vẫn đủ điều kiện, Customer có thể accept Invitation đó; không yêu cầu source Listing active lại vì offer snapshot đã khóa. Hold kéo dài quá expiry không gia hạn offer; Tutor cần gửi Invitation mới sau khi gate được khôi phục.

# 5\. Tình trạng implementation

Repo/runtime hiện chưa có API hoặc bản ghi moderation hold riêng cho Listing. Catalog giữ cả hai method/path `POST /api/v1/admin/listings/{listing}/hide` và `POST /api/v1/admin/listings/{listing}/restore-visibility` trong phạm vi API đích; OpenAPI sinh từ code chưa có chúng. Cần triển khai permission, hold, audit, outbox/notification, DTO và kiểm thử theo quy tắc đã chốt. Hiện tại gọi các path này có thể nhận 404/405.

# 6\. Tiêu chí chấp nhận

* Admin không có `admin.listings.moderate` bị từ chối; Admin active có permission này được xử lý Listing bất kỳ trong MVP, không cần assignment Report/Listing.
* Sau khi ẩn, Listing không xuất hiện trong bất kỳ response public nào kể cả khi truy cập slug trực tiếp.  
* Mọi hành động có reason code/giải thích bắt buộc, ghi audit actor, thời điểm và scope; Tutor vẫn là owner và Program history vẫn được giữ.
* Tutor không thể tự bỏ trạng thái moderation bằng pause/reactivate hoặc sửa Listing.
* Nếu hold do Admin đưa Listing `active → inactive` và Tutor không chọn tạm dừng trong thời gian hold, restore hợp lệ gỡ hold và tự chuyển `inactive → active` trong cùng giao dịch sau khi kiểm lại mọi public gate. Không cần Tutor nhấn Publish.
* Nếu Listing đã `inactive` do Tutor trước khi hide, hoặc Tutor chọn Tạm dừng trong lúc hold, restore chỉ gỡ hold và giữ `inactive`; Tutor tự quyết định khi nào publish lại. Hệ thống phải lưu nguồn/ý định tạm dừng để không nhầm hai trường hợp dù cùng giá trị status.
* Restore thành công tạo đúng một notification trong ứng dụng cho Tutor owner: “đã công khai trở lại” khi hệ thống tự active, hoặc “đã gỡ hạn chế; bạn có thể đăng lại” khi Tutor tự tạm dừng. Restore bị từ chối không tạo notification.
* Hide thành công tạo đúng một notification trong ứng dụng cho Tutor owner; không lộ reporter/evidence. Restore khi cần tự active mà thiếu public gate trả 409 và giữ hold, không báo đã gỡ hoặc công khai.
* Program Draft/Invitation mới tham chiếu Listing đang hold bị từ chối; dữ liệu lịch sử không đổi.

# 7\. Phần còn cần đặc tả/triển khai

* Trước rollout, vận hành phải công bố kênh hỗ trợ thực tế để notification có thể chỉ dẫn Tutor; không giới thiệu chức năng khiếu nại trong ứng dụng hoặc thời hạn phản hồi chưa có cam kết.
* BR-18 đã chốt quyền accept Invitation có trước hold. Idempotency, concurrency và retention của hold/audit/outbox thuộc thiết kế kỹ thuật trước triển khai, không làm thay đổi quy tắc Product đã chốt.

# 8\. Nguồn

Product: Roles & Actors, Business Rules. Domain: State Lifecycles, Enum Catalog, Domain Model. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
