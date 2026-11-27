"use client";

import { AppShell } from "@/components/layout/app-shell";
import { Invitations } from "@/components/programs/invitations";
import { useRouter } from "next/navigation";

export default function InvitationsPage() {
  const router = useRouter();
  return <AppShell role="customer" title="Lời mời học"><Invitations embedded onAccepted={(programId) => router.push(`/programs/${programId}`)} /></AppShell>;
}
