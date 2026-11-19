import { redirect } from "next/navigation";

export default function InvitationsPage() {
  redirect("/learning-profile?tab=invitations");
}
