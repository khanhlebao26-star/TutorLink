import { MessagingRoute } from "@/components/messaging/messaging";
import type { ReactNode } from "react";

export default function MessagesLayout({ children }: { children: ReactNode }) {
  return <MessagingRoute>{children}</MessagingRoute>;
}
