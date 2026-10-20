# M08 — Fee, Announcement & Notification

## PRD Index · 4 chức năng · Draft

# Mục tiêu

Ghi nhận khoản phí Tutor khai báo, công bố Announcement trong Program, phát Notification sau commit và cung cấp inbox riêng theo recipient.

# PRD chức năng

[M08-F01 PRD — Quản lý Fee Record](https://docs.google.com/document/d/1fb9VxGqYJ-G_6bv-uzT7dzY8gjw9V5z1Kz0iOA46_Hs/edit)  
[M08-F02 PRD — Tạo và công bố Announcement](https://docs.google.com/document/d/1NxmriEAiAsw43_jHRwu_qH8iBVOgjyBo6oYO2418Gd4/edit)  
[M08-F03 PRD — Phát sinh và chuyển Notification](https://docs.google.com/document/d/1bjM83iXY_-z7utMdN7qMvPzS-GOI0cbUtJoNt-FpIBU/edit)  
[M08-F04 PRD — Notification Inbox và trạng thái đã đọc](https://docs.google.com/document/d/19JpjWOLQwwdC3Lv-smGDz_90bX1ZUpHKvixXfHHrCUk/edit)

# Quy tắc đích

* Fee: một Program/tháng YYYY-MM, số nguyên VND không âm, Tutor khai báo; không phải payment.  
* FeeRecordStatus declared/voided tách TutorPaymentMark unmarked/marked\_paid/marked\_unpaid; audit mark change/void.  
* Announcement Draft/Published trong Program; create mới bị chặn khi Paused/terminal.  
* Notification recipient riêng; event sau commit và event\_key unique; in\_app là kênh MVP.

# Implementation gaps

* Migration có fee\_records, announcements, notifications; API, worker/scheduler và event writer chưa có baseline.  
* Schema enum legacy cần mapping; không tự coi confirmed là payment thật.

# Nguồn

Product/Domain docs hoàn tất và WF-05; nguồn cụ thể liệt kê ở từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
