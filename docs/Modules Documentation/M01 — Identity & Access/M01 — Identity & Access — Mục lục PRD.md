# M01 — Identity & Access

## PRD theo chức năng

# Mục tiêu

Tách M01 thành các luồng người dùng/API có thể giao việc và nghiệm thu riêng. Các PRD chức năng hiện ở trạng thái Draft.

# Danh sách PRD

* [M01-F01 — Đăng ký tài khoản](https://docs.google.com/document/d/1kqYxgvXJjh8_7w2Hbf9GqpXGtPHYwYbjnDsfzdqYQRA/edit)  
* [M01-F02 — Xác minh và gửi lại email](https://docs.google.com/document/d/1aqXGF1YBZRJw21WEDcFEyajJLKPEE6pRZzvqNvyYYoM/edit)  
* [M01-F03 — Đăng nhập](https://docs.google.com/document/d/1Xh3tiZtPy-KWBc06Gtg1R63dqmLl8fyKJVp4gkcO5p0/edit)  
* [M01-F04 — Đăng xuất và phiên bảo mật](https://docs.google.com/document/d/1FdiKYH4l8eKgw1edacCcsaWlir-34C6dsQSiX0wXPzc/edit)  
* [M01-F05 — Yêu cầu đặt lại mật khẩu](https://docs.google.com/document/d/12nVQNtjtC6Wwzxqfhf6xYcEvU1JCxkjtWESCoZCir4c/edit)  
* [M01-F06 — Đặt lại mật khẩu](https://docs.google.com/document/d/1mxbshFFkCFMLdBGlNpSO5X3vzwqFEpdToSJkOWgO6lc/edit)  
* [M01-F07 — Thông tin tài khoản của tôi](https://docs.google.com/document/d/1FHFR7cyCsLKQdgkI8k09vL0535HMXxw6jD9Hwj5fu1I/edit)  
* [M01-F08 — Role và trạng thái truy cập](https://docs.google.com/document/d/1VjvAR8qfUzUGLS7Z1cQv2d_WHmzRKkpOjeAUdv1GU0Q/edit)

# Quyết định sản phẩm

* Customer từ đủ 15 tuổi; Tutor từ đủ 18 tuổi. Admin được tạo nội bộ; Guest là trạng thái truy cập công khai, không phải role.  
* Một tài khoản chỉ có một role cố định. Backend kiểm tra role, trạng thái và quyền trên mọi API riêng tư.  
* Dùng session cookie Sanctum và CSRF cho mutation; không lưu bearer token dài hạn trong localStorage.

# Các điểm cần đồng bộ

* Project Overview, Roles & Actors, Goals & Non-goals và Business Rules thống nhất Customer từ đủ 15 tuổi, Tutor từ đủ 18 tuổi. RegisterRequest/runtime hiện vẫn yêu cầu 18 tuổi cho cả hai role; API contract, validation và giao diện cần cập nhật trước khi coi quyết định này đã được triển khai.  
* terms\_version hiện nullable trong schema/validation; chưa tự coi là điều kiện bắt buộc.  
* Route login hiện chưa khai báo rate limit. Product/Domain Documents đã hoàn tất chưa quy định giới hạn này; chốt trong Security NFR trước khi đưa thành acceptance bắt buộc.

# NFR liên quan

Mục tiêu tham chiếu: 5.000 tài khoản, 500–1.000 MAU, 50 người dùng đồng thời (đỉnh ngắn hạn 100), API p95 dưới 2 giây và 99% sẵn sàng nội bộ.

# Nguồn

Nguồn sản phẩm: Project Overview, Roles & Actors, Problem Statement, Goals & Non-goals. Nguồn domain: Business Rules, State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. OpenAPI/code chỉ dùng để ghi nhận chênh lệch triển khai hiện tại.
