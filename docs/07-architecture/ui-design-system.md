# UI design system — W1-B1/B2 Figma

Nguồn code: [globals.css](../../frontend/src/app/globals.css), [ui.tsx](../../frontend/src/components/ui.tsx), [app-shell](../../frontend/src/components/app-shell.tsx). Nguồn thiết kế: [TutorLink Screens](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=12-3). Route/frame và evidence được ghi trong [bàn giao issue #22](../06-acceptance/issue-22-figma-layout.md). Đây không phải chứng nhận accessibility toàn bộ sản phẩm.

## Tokens hiện có

`--background: #f8f7ff`, `--surface: #fff`, `--foreground` và `--brand: #071a4c`, `--muted: #7280a9`, `--line: #dde4f2`, `--accent: #ff219b`, `--accent-dark: #e71788`, `--accent-soft: #fff0f8`. Font Inter weights 400–800 được lưu cục bộ trong `frontend/public/fonts`, gồm Vietnamese/Latin/Latin-ext và giấy phép OFL; stack dự phòng Arial/Helvetica/sans-serif. Không tải Google Fonts lúc runtime/build. Focus-visible 3px `var(--focus)`, offset 3px. Giữ token chung; không tạo theme riêng mỗi module.

Khung trang public và authenticated dùng full-width, không giới hạn max-width hay căn giữa bằng auto margin. Header phủ ngang viewport; sidebar desktop 180px và main chiếm phần còn lại, có padding responsive. Panel chi tiết dùng toàn bộ hàng nội dung. Auth dùng scene full-width với card form riêng để giữ khả năng đọc/nhập liệu. Card chung radius16/padding24, button radius10/min-height46, input radius9/min-height44. Auth action dạng pill; dashboard dùng button chữ nhật bo góc. Các style theo màn hình nằm trong `auth.css` và `onboarding.css`, dùng token chung. Bàn giao thay đổi chiều rộng: [Full-width layout](../06-acceptance/full-width-layout.md); acceptance kiểm 360/768/1536/2560px.

## Components và hành vi

Reuse Button, Input, Select, Card, Field(label/error/hint), StatePanel(loading/empty/error), ErrorActions(retry/login), Badge(neutral/success/warning/danger/brand), DataTable. Button nhận native props và `variant="primary|secondary|outline|ghost|danger"`; Input/Select/Card/Badge nhận `className` và native props. Status luôn có text.

```tsx
<Card>
  <Field label="Email" htmlFor="account-email" error={errors.email?.[0]}>
    <Input id="account-email" type="email" required disabled={saving} />
  </Field>
  <Button type="submit" disabled={saving} aria-busy={saving}>Lưu</Button>
</Card>
```

`Field` gắn error/hint ID ổn định, `aria-invalid` và `aria-describedby` cho control trực tiếp. Với control nằm trong wrapper (ví dụ password có nút hiện/ẩn), gắn các aria props lên input bên trong và giữ ID tương ứng. Tránh wrapper và input trùng ID.

`AppShell` giữ props `role`, `title`, bổ sung `mode="public|auth"`. Auth dùng public header; các trang tài khoản lấy role từ `getCurrentUser()`, kiểm role cần thiết trước khi hiện nội dung. Customer/Tutor không có link Admin. Navigation dùng `aria-current="page"`; menu mobile có `aria-expanded`/`aria-controls`. `/admin` được bàn giao shell chung, không thay nghiệp vụ duyệt hồ sơ.

Field 422 gắn đúng input; lỗi tổng thể alert; pending disable submit; keyboard focus không mất sau refetch. Icon-only có accessible name. Modal mới cần focus trap/return focus; component hiện tại không chứng minh đã có modal primitive đạt chuẩn.

## Quy tắc screen

Marketplace filter ở server, URL/query/page thống nhất; empty theo filter, không fallback mock. Owner dùng nút Lưu trữ, không Xóa khi archive. Profile status mapping active→Approved, suspension riêng.

Program tabs đúng sáu tab; Active không Resume, Paused không Pause; terminal readonly. Actions theo server capability sau khi DTO triển khai; 409 refetch state. Create Draft/send hai bước, lỗi send giữ Draft. Chat retry giữ UUID và dedupe server ID.

Tiếng Việt, VND 200000 → 200.000 ₫ cùng price_unit; không FX/switch giả. Fee disclaimer mọi trạng thái. Không bulk checkbox nếu không API, không gallery/intro/upload progress ngoài scope. Mỗi UI PR kèm viewport/keyboard/error-state evidence, không chỉ ảnh happy path.
