import { AppShell } from "@/components/app-shell";
import { PublicTutor } from "@/components/public-tutor";
import "@/app/public-detail.css";

export default async function PublicTutorPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return <AppShell title="Hồ sơ gia sư" mode="public"><PublicTutor slug={slug} /></AppShell>;
}
