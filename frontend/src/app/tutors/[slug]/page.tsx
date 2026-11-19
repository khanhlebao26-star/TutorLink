import { AppShell } from "@/components/layout/app-shell";
import { PublicTutor } from "@/components/tutors/public-tutor";
import "@/styles/public-detail.css";

export default async function PublicTutorPage({
  params,
  searchParams,
}: {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ tab?: string | string[] }>;
}) {
  const [{ slug }, { tab: rawTab }] = await Promise.all([params, searchParams]);
  const tab = Array.isArray(rawTab) ? rawTab[0] : rawTab;
  const initialTab = tab === "services" || tab === "reviews" || tab === "schedule" ? tab : "overview";
  return <AppShell title="Hồ sơ gia sư" mode="public"><PublicTutor slug={slug} initialTab={initialTab} /></AppShell>;
}
