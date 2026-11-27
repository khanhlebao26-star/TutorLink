import { AppShell } from "@/components/layout/app-shell";
import { PublicTutor } from "@/components/tutors/public-tutor";
import { getFeaturedListingCandidates } from "@/lib/data/marketplace";

export const dynamic = "force-static";
export const dynamicParams = true;
export const revalidate = 300;

export async function generateStaticParams(): Promise<Array<{ slug: string }>> {
  try {
    const candidates = await getFeaturedListingCandidates();
    const slugs = [...new Set(candidates.data.map((listing) => listing.tutor_slug).filter((slug): slug is string => Boolean(slug)))];
    return slugs.slice(0, 6).map((slug) => ({ slug }));
  } catch (error) {
    console.warn("[prerender] Could not load tutor slugs; shells will render on demand.", error instanceof Error ? error.message : error);
    return [];
  }
}

export default async function PublicTutorPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  // Only the stable loading shell is cached. Mutable profile content comes from the API.
  return <AppShell title="Hồ sơ gia sư" mode="public"><PublicTutor key={slug} slug={slug} /></AppShell>;
}
