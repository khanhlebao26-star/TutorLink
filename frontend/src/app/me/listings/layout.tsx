import { AppShell } from "@/components/layout/app-shell";
import "@/styles/listings.css";
import type { ReactNode } from "react";

export default function OwnerListingsLayout({ children }: { children: ReactNode }) {
  return <AppShell role="tutor" title="Dịch vụ của tôi">{children}</AppShell>;
}
