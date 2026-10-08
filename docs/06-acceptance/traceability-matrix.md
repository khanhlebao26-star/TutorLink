# Requirement traceability

Mỗi dòng liên kết requirement, rules, module, plan slice và AC. Cột slice dùng mã kế hoạch, không phải GitHub ID. Evidence thực điền trong task handoff; chưa có evidence thì Not verified.

| Requirement | Rules | Module | Slice | AC |
| --- | --- | --- | --- | --- |
| FR-01 Auth SC01 | BR-01/04 | M01 | W1-A2/B2 closeout | AC-G1-01 |
| FR-02 Profile/approval SC01 | BR-02/03/04 | M02 | W1-A2/B2/C2 closeout | AC-G1-02/06 |
| FR-03 Catalog/Listing SC02 | BR-02/05 | M03 | W1-A3/B3 | AC-G1-03 |
| FR-04 Search/public SC02 | BR-02/05/14 | M03 | W1-A3/B3 | AC-G1-04 |
| FR-05 Messaging SC03 | BR-06/07/15 | M04 | W2-C1/B1 | AC-G2-01/02 |
| FR-06 Draft/snapshot | BR-08 | M05 | W2-A1/C2/B2 | AC-G2-03 |
| FR-07 Accept SC04 | BR-09/15 | M05 | W2-C3/B3 | AC-G2-04/05 |
| FR-08 Session/Overview SC05 | BR-04/10 | M06 | W3-A1/B1 | AC-G3-01 |
| FR-09 Task/Progress SC05 | BR-10/11 | M07 | W3-C1/C2/B2/B3 | AC-G3-02/03 |
| FR-10 Fee/Announcement | BR-10/12 | M08 | W3-A2/C2/B3 | AC-G3-04 |
| FR-11 Notification | BR-15 | M08 | W2-A3/W3-C2 | AC-G3-05 |
| FR-12 Review/Report SC06 | BR-13/15 | M09 | W3-C3/A3/B3 | AC-G3-06 |
| FR-13 File/access SC05 | BR-14 | M10 | W1 closeout/W2-A2 | AC-G1-05/G2-06 |
| FR-14 Release/NFR | BR-16 | All | W4-A/B/C1–3 | AC-G4-01/02/03/04 |

SC01–06 kế thừa proposal. FR/BR/AC là taxonomy docs mới để truy vết; không tạo thêm feature ngoài proposal. PR cập nhật behavior phải cập nhật hàng liên quan, OpenAPI và test/evidence, không chỉ README.
