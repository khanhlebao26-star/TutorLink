# M02-F01 — Tạo và cập nhật hồ sơ Tutor

## Trạng thái: Draft  |  Actor: Tutor  |  API hiện tại: GET/PUT /api/v1/tutor/profile

# Mục tiêu

Cho phép Tutor tạo và quản lý hồ sơ của chính mình làm nguồn thông tin để xét duyệt và hiển thị hồ sơ công khai khi đủ điều kiện.

# Quy tắc sản phẩm

* Tutor chỉ đọc và sửa hồ sơ gắn với chính tài khoản của mình; không thể chọn ID để xem hoặc cập nhật hồ sơ Tutor khác.  
* Hồ sơ mô tả người cung cấp dịch vụ. Các trường nghiệp vụ được lưu theo schema Tutor Profile đã thống nhất; contract hiện có các trường headline, bio, experience và specialization. Avatar và giấy tờ xác minh được liên kết theo M02-F02.  
* Hồ sơ draft hoặc chưa approved không xuất hiện trong dữ liệu công khai. Chỉ hồ sơ approved mới đủ điều kiện hiển thị công khai; các điều kiện công khai khác vẫn do module Listing áp dụng.  
* Tutor có thể sửa draft và nội dung được yêu cầu chỉnh sửa. Khi đã submitted, hồ sơ chờ quyết định; chỉ được gửi lại sau trạng thái changes\_requested. Hồ sơ suspended không cho sửa.  
* Hồ sơ approved được sửa trên một revision chờ duyệt, không ghi đè bản công khai. GET của Tutor trả cả bản approved và revision hiện có; PUT cập nhật revision của owner. Khi revision được duyệt, thay bản công khai nguyên tử; nếu yêu cầu sửa hoặc từ chối, bản approved trước vẫn công khai nếu các gate khác thỏa. Mỗi hồ sơ chỉ có một revision đang xử lý.

# Luồng

* Tutor mở hồ sơ của mình; hệ thống trả dữ liệu và trạng thái hiện tại.  
* Tutor tạo draft hoặc cập nhật trường được phép. Backend lưu dữ liệu và giữ nguyên trạng thái nghiệp vụ ngoài các transition được cho phép.  
* Nếu tài khoản không phải Tutor, hồ sơ thuộc tài khoản khác, hoặc trạng thái không cho sửa, backend từ chối và không ghi một phần.

# Tiêu chí chấp nhận

* Tutor chỉ truy cập được hồ sơ của mình; truy cập chéo tài khoản bị từ chối ở backend.  
* Draft có thể được tạo và cập nhật theo schema; dữ liệu sai định dạng trả lỗi gắn trường và không làm hỏng dữ liệu đã lưu.  
* Hồ sơ submitted không bị thay đổi trong lúc chờ Admin; changes\_requested mở lại khả năng sửa để Tutor gửi lại.  
* Hồ sơ draft, submitted, changes\_requested, rejected hoặc suspended không bị trả như hồ sơ công khai đã duyệt.
* Sửa bản approved không làm TutorProfileStatus rời approved; revision riêng không xuất hiện công khai trước khi được duyệt.

# Điểm cần chốt

* Wire DTO/version và migration để lưu revision approved cần đặc tả/triển khai; quy tắc xét duyệt bản sửa đã chốt tại BR-16.
* API hiện tại cho phép lưu headline, bio, experience và specialization; điều kiện required/optional cần được đối chiếu với schema sản phẩm hoàn tất.

# Nguồn và phụ thuộc

Roles & Actors; Project Overview; Domain Model; State Lifecycles. Phụ thuộc M02-F02 để liên kết avatar/giấy tờ và M02-F03 để gửi xét duyệt.
