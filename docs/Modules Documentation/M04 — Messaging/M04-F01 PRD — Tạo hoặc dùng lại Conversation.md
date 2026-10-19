# M04-F01 · Tạo hoặc dùng lại Conversation

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu và actor

Customer bắt đầu trao đổi với Tutor. Máy chủ tạo hoặc trả về đúng Conversation của cặp đó; Listing chỉ lưu làm ngữ cảnh tùy chọn.

# 2\. Quy tắc nghiệp vụ

* Mỗi cặp Customer–Tutor có một Conversation dùng chung. Mở từ Listing A hay Listing B của cùng Tutor không tạo hội thoại mới.  
* direct\_key do máy chủ tạo từ cặp participant đã sắp xếp ổn định; không nhận key tùy ý từ client và không đưa Listing/Program vào natural key.  
* context\_listing\_id là metadata tùy chọn. Nếu được truyền, Listing phải liên quan đúng Tutor; yêu cầu Listing còn active/công khai khi bắt đầu chat cần được chốt contract.  
* Tạo mới chỉ cho cặp user hợp lệ đã đăng nhập. Máy chủ kiểm tra tư cách tham gia, trạng thái account/profile và quyền trong transaction; tiêu chí chính xác cho lần liên hệ đầu tiên cần được thống nhất với M01/M02.  
* Tìm/tạo phải nguyên tử và chịu được hai request đồng thời; unique direct\_key là ràng buộc cuối cùng chống tạo trùng.  
* Conversation cancelled chỉ được tái kích hoạt khi có trao đổi dịch vụ mới đủ điều kiện; dùng lại chính Conversation cũ.

# 3\. Hành vi API đích

Client gửi yêu cầu tạo Conversation kèm Tutor và ngữ cảnh Listing tùy chọn; không gửi direct\_key. Máy chủ xác thực actor, derive key, khóa/tra cứu key và atomically find-or-create. Endpoint và request/response shape chưa được thêm vào OpenAPI.

# 4\. Tiêu chí chấp nhận

* Hai request cạnh tranh của cùng cặp trả cùng một Conversation và chỉ có một hàng direct.  
* Hai Listing khác nhau của cùng Tutor trả cùng Conversation; context có thể cập nhật theo chính sách được chốt nhưng không đổi natural key.  
* Request không đủ quyền, cặp trùng vai trò, Tutor không đủ điều kiện hoặc Listing không thuộc Tutor bị từ chối; không tạo dữ liệu dở dang.  
* Retry sau timeout không tạo Conversation thứ hai.

# 5\. Chênh lệch/câu hỏi mở

* Chốt eligibility cụ thể cho khởi tạo chat: account active, email verified, Tutor Profile approved, age/role, và xử lý trạng thái suspended.  
* Migration có type=direct/program và status mặc định pending; target Domain mô tả một Conversation theo cặp Customer–Tutor với status active/cancelled. Cần mapping trước endpoint.  
* Chốt policy khi request có Listing inactive, bị archive hoặc không còn public.

# 6\. Nguồn

Product: Roles & Actors, Business Rules, Project Overview. Domain: Domain Model, State Lifecycles, Enum Catalog. Thiết kế: WF-03 Direct chat và retry, ADR-002. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://drive.google.com/drive/folders/1T\_d6ESJJUN5acqykfmrM1B9f3\_-SMxxf
