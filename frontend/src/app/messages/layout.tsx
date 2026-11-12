import type { ReactNode } from "react";
import { MessagingRoute } from "@/components/messaging";

export default function MessagesLayout({ children }: { children: ReactNode }) {
  return <MessagingRoute>{children}</MessagingRoute>;
}
