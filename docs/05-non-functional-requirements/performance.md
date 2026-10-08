# Performance

Mục tiêu proposal §10: khoảng 5.000 accounts, 500–1.000 MAU, 50 concurrent users, đỉnh ngắn 100; p95 API thường <2s, search <3s ở tải mục tiêu. Đây không phải SLA hay kết quả hiện tại.

W4-A2 đo Listing search, conversation/message history, Program list/dashboard và Admin report list. Ghi môi trường/commit, fixture size, mix/read-write, concurrent users, duration/warm-up, error rate và p95 từng route. Đo polling load khi chat đang mở, không chỉ request độc lập.

Giới hạn per_page/limit và filter/sort whitelist; eager load summary, kiểm N+1/query plan. Thêm index dựa đo; không đưa Redis/search engine riêng khi chưa có evidence cần thiết. Kết quả load test trên máy local không đại diện production nếu cấu hình khác.
