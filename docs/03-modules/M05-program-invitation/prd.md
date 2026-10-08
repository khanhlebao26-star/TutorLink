# PRD Program and Invitation

FR-06/07, BR-08/09/10/15. Approved Tutor tạo Draft cho một Customer target; snapshot có mục tiêu/delivery_mode/agreed_fee_minor/currency/fee_unit và phạm vi thỏa thuận. Draft sửa được khi chưa khóa; source Listing archive không làm mất offer.

Send tạo một Pending, locks snapshot; inbox/detail theo quyền; accept/reject/cancel/expire theo actor/state/expiry. Schema cần target_customer_id, Program state mapping và pending uniqueness theo Program; không ghi là đã có.

Accept transaction khóa theo thứ tự chung, kiểm target Customer active, Tutor eligibility, snapshot/version, expiry; tạo member và Active cùng transaction. Retry cùng key không tạo trùng; cancel/expire/suspend cạnh tranh không để state nửa chừng. Side effect sau commit có event_key.

AC-G2-03/04/05: Snapshot không đổi khi Listing/Profile sửa; create Draft thành công nhưng send fail FE giữ Draft, retry cùng Program; wrong user/expired/terminal denied; double accept tạo đúng một Customer, một Active; audit/notification rollback và race PostgreSQL thực. Program/detail/list trả capability server; UI không tự quyết quyền bằng status string.
