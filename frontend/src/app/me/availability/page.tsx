import { AvailabilityEditor } from "@/components/availability/availability-editor";
import { AppShell } from "@/components/layout/app-shell";
import "@/styles/availability.css";

export default function AvailabilityPage() {
  return <AppShell role="tutor" title="Lịch khả dụng"><AvailabilityEditor /></AppShell>;
}
