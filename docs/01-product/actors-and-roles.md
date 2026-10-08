# Actor và vai trò

Một User có một role cố định: `customer`, `tutor`, `admin`. Customer/Tutor tối thiểu 18 tuổi; phụ huynh dùng Customer account khi cần. Admin tạo nội bộ, không qua đăng ký public.

- Customer: khám phá public, chat của mình, nhận/accept invitation gửi cho mình, đọc Program member, nộp Task được giao, review đủ điều kiện, report.
- Tutor: quản lý profile và Listing của mình; tạo Program khi Approved và đủ điều kiện; vận hành Program mình sở hữu.
- Admin: duyệt/suspend/report theo từng permission, không tạo hoặc accept Program thay người dùng.

Năm permission hiện có tại [AdminPermissions](../../backend/app/Support/AdminPermissions.php): `admin.tutor_profiles.view`, `admin.tutor_profiles.decide`, `admin.verification_documents.download`, `admin.tutor_profiles.suspend`, `admin.accounts.suspend`. Restore dùng quyền suspension tương ứng. Quyền report phải bổ sung khi làm Trust, không giả định đã tồn tại.

Không dùng nút ẩn như authorization. Backend kiểm role + ownership/member + resource state. Suspension Profile và Account khác nhau; xem BR-04.
