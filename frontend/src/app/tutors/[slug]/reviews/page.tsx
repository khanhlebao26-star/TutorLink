import { AppShell } from "@/components/app-shell";
import { StatePanel } from "@/components/ui";
import "@/app/public-detail.css";

export default function PublicTutorReviewsPage() {
  return <AppShell title="Nhận xét gia sư" mode="public"><section className="content-stack"><StatePanel kind="empty" message="Nhận xét công khai sẽ được bổ sung sau khi chính sách Review của W3-C3 được chốt." /></section></AppShell>;
}
