# M08 Operations

W2-A3 queue/scheduler/event writer; W3-A2 Fee API; W3-C2 Announcement/Notification; W3-B3 tích hợp UI. Đọc [PRD](prd.md), BR-12/15 và NFR reliability/deployment. Schema có, nhưng API/worker/scheduler chưa có tại baseline.

Mailpit là email inbox local, không phải worker. Thêm worker/scheduler khi slice notification/expiry yêu cầu, không vì số container cần đẹp.
