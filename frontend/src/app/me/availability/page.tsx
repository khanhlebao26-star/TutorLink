import { AppShell } from "@/components/app-shell";
import { AvailabilityEditor } from "@/components/availability-editor";
import "../../availability.css";

export default function AvailabilityPage() {
  return <AppShell role="tutor" title="Lịch khả dụng"><AvailabilityEditor /></AppShell>;
}
