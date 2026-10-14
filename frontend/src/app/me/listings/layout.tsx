import type { ReactNode } from "react";
import { AppShell } from "@/components/app-shell";
import "../../owner-listings.css";

export default function OwnerListingsLayout({ children }: { children: ReactNode }) {
  return <AppShell role="tutor" title="Dịch vụ của tôi">{children}</AppShell>;
}
