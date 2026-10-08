# Miền nghiệp vụ TutorLink

Identity quản lý User và quyền; Tutor Approval quản lý eligibility; Marketplace quản lý public offer; Messaging quản lý trao đổi; Program quản lý thỏa thuận; Workspace quản lý thực hiện; Trust quản lý review/report; File và Notification hỗ trợ các miền.

Không ánh xạ mỗi bảng thành một CRUD endpoint. Accept invitation là một command cập nhật nhiều bảng; public Listing phụ thuộc Profile/User/Catalog; file download phụ thuộc resource, không chỉ uploader.

Đọc theo thứ tự: [terminology](terminology.md) → [entity model](entity-model.md) → [lifecycle](program-lifecycle.md) → [state](state-transition.md) → [rules](business-rules.md). Domain là hành vi đích; [data model](../07-architecture/data-model.md) ghi các khoảng cách với schema thực tế.
