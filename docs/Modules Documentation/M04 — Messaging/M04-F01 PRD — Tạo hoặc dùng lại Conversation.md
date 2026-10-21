# M04-F01 · Tạo hoặc dùng lại Conversation

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu và actor

Customer hoặc Tutor bắt đầu trao đổi với người thuộc vai trò còn lại. Máy chủ tạo hoặc trả về đúng Conversation của cặp đó; Listing chỉ lưu làm ngữ cảnh tùy chọn.

# 2\. Quy tắc nghiệp vụ

* Mỗi cặp Customer–Tutor có một Conversation dùng chung. Mở từ Listing A hay Listing B của cùng Tutor không tạo hội thoại mới.  
* direct\_key do máy chủ tạo từ cặp participant đã sắp xếp ổn định; không nhận key tùy ý từ client và không đưa Listing/Program vào natural key.  
* context\_listing\_id là metadata tùy chọn. Nếu được truyền lúc mở liên hệ mới, Listing phải thuộc Tutor và đang hiển thị công khai theo gate của M03; Listing bị ẩn/archived/inactive không thể dùng để mở liên hệ mới. Context chỉ lưu khi tạo Conversation lần đầu và không bị ghi đè khi dùng lại/tái mở, kể cả khi request đến từ Listing khác. Việc thay đổi visibility về sau không xóa Conversation hoặc lịch sử.
* Customer hoặc Tutor đang đăng nhập đều có thể khởi tạo, nhưng chỉ với cặp khác vai trò và tài khoản hai bên đang active. Tutor phải có email verified, Profile approved, giấy tờ bắt buộc còn hiệu lực theo BR-16 và không suspended; không mở liên hệ mới khi gate này không đạt. Quyền đọc lịch sử khi participant bị đình chỉ theo BR-17/M04-F02.
* Tìm/tạo phải nguyên tử và chịu được hai request đồng thời; unique direct\_key là ràng buộc cuối cùng chống tạo trùng.  
* Conversation cancelled chỉ được tái kích hoạt bởi một yêu cầu mở liên hệ mới đáp ứng lại toàn bộ gate trên; dùng lại chính Conversation cũ. GET, poll hoặc retry gửi tin cũ không tự mở lại.

# 3\. Hành vi API đích

Request đích `POST /api/v1/conversations` nhận `peer_user_id` và `context_listing_id` tùy chọn; client không gửi `direct_key` hoặc `sender_id`. Máy chủ suy ra Customer/Tutor từ vai trò hai participant, xác thực cả Listing đầu vào nếu có, derive key, khóa/tra cứu key và atomically find-or-create. Response tối thiểu: `id`, `status`, `peer: {id, display_name}`, `context_listing_id` đã lưu; không trả direct_key, email, điện thoại hoặc thông tin riêng tư của peer. `can_send` nếu được trả chỉ là gợi ý UI do server tính, không thay kiểm quyền lúc gửi. Nếu Conversation đã có, trả đúng context gốc và cho UI biết đây là chat cũ; không gán lịch sử cũ cho Listing mới. Method/path theo Catalog vẫn Dự kiến; DTO này chưa có trong OpenAPI sinh từ code.

# 4\. Tiêu chí chấp nhận

* Hai request cạnh tranh của cùng cặp trả cùng một Conversation và chỉ có một hàng direct.  
* Hai Listing khác nhau của cùng Tutor trả cùng Conversation và context lần tạo đầu tiên; không ghi đè context khi dùng lại/tái mở.
* Request không đủ quyền, cặp trùng vai trò, Tutor không đủ điều kiện hoặc Listing không thuộc Tutor bị từ chối; không tạo dữ liệu dở dang.  
* Retry sau timeout không tạo Conversation thứ hai.

# 5\. Chênh lệch/câu hỏi mở

* Migration có type=direct/program và status mặc định pending; target Domain mô tả một Conversation theo cặp Customer–Tutor với status active/cancelled. Cần mapping trước endpoint.  
* BR-17 đã chốt field/allow-list tối thiểu và xử lý lịch sử khi một bên bị suspend. Migration, schema lỗi và chi tiết wire DTO cần hiện thực/kiểm thử; không coi Catalog hay PRD là API đã khả dụng.

# 6\. Nguồn

Product: Roles & Actors, Business Rules, Project Overview. Domain: Domain Model, State Lifecycles, Enum Catalog. Thiết kế: WF-03 Direct chat và retry, ADR-002. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://drive.google.com/drive/folders/1T\_d6ESJJUN5acqykfmrM1B9f3\_-SMxxf
