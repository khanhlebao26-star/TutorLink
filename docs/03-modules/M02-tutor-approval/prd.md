# PRD Tutor Profile and Approval

FR-02, BR-02/03/04/14. Tutor lưu headline/bio/experience/specialization, avatar và verification complete đúng purpose; submit, khóa edit; Request Changes mở chỉnh sửa/resubmit; Admin approve/reject/request-changes/suspend/restore với reason.

Code tồn tại; eligibility dùng approval_status active và suspended_at riêng. Submit completeness cần test thực tế, không suy ra “đủ hồ sơ” chỉ từ có submitted_at.

AC: đầy đủ profile/verification hợp lệ mới submit theo contract đã duyệt; draft thiếu dữ liệu không bypass; request changes → edit → resubmit → approve; reject thành công rồi không sửa/approve lại; permission decide/view/download/suspend/restore độc lập; audit failure rollback cả state; stale double decision 409; Account suspend khác Profile suspend.

Đóng prerequisite trước công bố phase cũ Done: LOCAL_ADMIN credential riêng, full seed chạy lặp không trùng, browser flow thật và evidence AC-G1-02/05/06. Không ghi password vào docs/evidence. Không mở edit Approved hoặc public verification trong A3.
