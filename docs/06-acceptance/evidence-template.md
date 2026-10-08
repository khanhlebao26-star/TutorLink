# Mẫu ghi bằng chứng

Sao chép vào task/PR hoặc file evidence riêng khi được giao; template này không phải kết quả chạy.

- Task/issue thật và plan code:
- Requirement/BR/AC, backlog testcase ID nếu có:
- Owner/reviewer/người nghiệm thu:
- Commit + dirty diff liên quan; thời điểm/môi trường:
- DB driver/database/config-cache path hiệu lực; xác nhận disposable trước migration/test:
- Fixture/actor/role/state (không credential/PII):
- Command hoặc bước browser, input, expected:
- Actual result và Pass/Fail/Not run/Blocked từng assertion:
- Log/screenshot/request-response redacted, đường dẫn evidence:
- DB invariant trước/sau, rollback/race connections nếu cần:
- Known defects severity/owner và test chưa chạy:
- Reviewer decision/ngày; gate vẫn pending nếu chưa ký:

Không chép số test của phiên trước như kết quả phiên này. Khi DB/browser/tool không chạy được, ghi limitation cụ thể; không chuyển “không test” thành Pass.
