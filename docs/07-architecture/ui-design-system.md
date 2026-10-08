# UI design system baseline

Nguồn code: [globals.css](../../frontend/src/app/globals.css), [ui.tsx](../../frontend/src/components/ui.tsx), [app-shell](../../frontend/src/components/app-shell.tsx). Đây là design baseline hiện có, không phải Figma library hoặc chứng nhận accessibility đã đạt.

## Tokens hiện có

`--background: #f5f7fb`, `--foreground: #172033`, `--muted: #64748b`, `--line: #dbe3ef`, `--brand: #2354a6`, `--brand-dark: #183d7e`. Font Arial/Helvetica/sans-serif. Focus-visible 3px `#f5a524`, offset 2px. Giữ token chung; không invent theme/font theo mỗi module.

Page max-width 1180px, horizontal padding clamp(18px,4vw,64px); card radius16/padding24, button radius10, input radius9. CSS breakpoint hiện 780px chuyển grids sang một cột. Acceptance vẫn kiểm 360/768/desktop; không coi breakpoint hiện có là test responsive đã pass.

## Components và hành vi

Reuse Button, Field(label/error/hint), StatePanel(loading/empty/error), ErrorActions(retry/login), Badge(neutral/success/warning), DataTable. Status có text; trạng thái reject/error chưa có tone riêng thì thêm component/token thống nhất theo task, không CSS inline riêng mỗi screen.

Field 422 gắn đúng input; lỗi tổng thể alert; pending disable submit; keyboard focus không mất sau refetch. Icon-only có accessible name. Modal mới cần focus trap/return focus; component hiện tại không chứng minh đã có modal primitive đạt chuẩn.

## Quy tắc screen

Marketplace filter ở server, URL/query/page thống nhất; empty theo filter, không fallback mock. Owner dùng nút Lưu trữ, không Xóa khi archive. Profile status mapping active→Approved, suspension riêng.

Program tabs đúng sáu tab; Active không Resume, Paused không Pause; terminal readonly. Actions theo server capability sau khi DTO triển khai; 409 refetch state. Create Draft/send hai bước, lỗi send giữ Draft. Chat retry giữ UUID và dedupe server ID.

Tiếng Việt, VND 200000 → 200.000 ₫ cùng price_unit; không FX/switch giả. Fee disclaimer mọi trạng thái. Không bulk checkbox nếu không API, không gallery/intro/upload progress ngoài scope. Mỗi UI PR kèm viewport/keyboard/error-state evidence, không chỉ ảnh happy path.
