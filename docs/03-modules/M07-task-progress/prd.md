# PRD Task Submission and Progress

FR-09, BR-10/11. Tutor tạo/giao Task cho Customer member, đính File hợp lệ; Customer PUT submission hiện tại; Tutor feedback/complete; Progress là metric/value/note theo ngày, owner quản lý và member đọc.

Chốt Task todo=Not Started và cách xử lý in_progress trước W3. Submission version tăng mỗi update; feedback dùng expected_submission_version; nếu Customer resubmit đồng thời, stale feedback 409 không ghi lên bản mới. Unique task/customer giữ một current submission.

AC-G3-02/03: wrong assignee/member denied; owner không nộp thay; attachment không cross-resource; overdue chỉ mở và due_at<now; submitted_late đúng timestamp; stale feedback race PostgreSQL; Paused vẫn submit/feedback/progress theo policy; Completed/Cancelled readonly. Complete Program không âm thầm complete Task chưa làm.
