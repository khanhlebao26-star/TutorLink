# Usability

UI tiếng Việt tại 360 px, 768 px và desktop. Keyboard navigation/focus-visible, label input/icon-only, modal focus trap nếu dùng, status có chữ không chỉ màu. Kiểm zoom/wrapping và table scroll, không chỉ resize screenshot.

API screen phải có loading, empty đúng filter, retryable error, 401 login, 403/404 an toàn, 409 refetch, 422 field errors, 429 retry. Disable submit lúc pending nhưng backend vẫn chống duplicate. Draft giữ sau send fail; message retry giữ client UUID.

Theo [UI design system](../07-architecture/ui-design-system.md); reuse Button/Field/StatePanel/Badge/DataTable. Không language/currency switch giả hoặc bulk checkbox khi không bulk API. Không đưa upload progress/gallery vào G1 khi chưa scope.
