# System context

Customer/Tutor/Admin dùng Next.js; browser gọi Laravel /api/v1 bằng cookie Sanctum. Laravel giữ validation/policy/transaction; PostgreSQL là nguồn sự thật; private file nằm Laravel local disk hiện tại. Email Auth local qua Mailpit.

Demo tương lai cần HTTPS, email provider, storage dùng chung phù hợp, worker/scheduler cho notification/expiry. Reverb/Echo optional sau REST/polling. Google Meet/Zoom/Teams chỉ là URL do người dùng nhập, không integration video.

System boundaries: TutorLink không giữ tiền, không xác nhận payment, không slot booking, không cung cấp AI trong sản phẩm. AI trợ lý chỉ hỗ trợ phát triển qua quy trình docs, không là service runtime.
