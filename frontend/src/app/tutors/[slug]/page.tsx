import { AppShell } from "@/components/layout/app-shell";
import { PublicTutor } from "@/components/tutors/public-tutor";
import { getFeaturedListingCandidates, getPublicTutor } from "@/lib/data/marketplace";
import type { TutorPublic } from "@/lib/data/marketplace-types";

export const dynamic = "force-static";
export const dynamicParams = true;
export const revalidate = 300;

/** Prebuild a small, bounded set of tutors represented by current public listings. */
export async function generateStaticParams(): Promise<Array<{ slug: string }>> {
  try {
    const candidates = await getFeaturedListingCandidates();
    const slugs = new Set<string>();
    for (const listing of candidates.data) {
      if (listing.tutor_slug) slugs.add(listing.tutor_slug);
      if (slugs.size === 6) break;
    }
    return [...slugs].map((slug) => ({ slug }));
  } catch (error) {
    console.warn("[prerender] Could not load public tutor slugs; profiles will render on demand.", error instanceof Error ? error.message : error);
    return [];
  }
}

export default async function PublicTutorPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  let initialTutor: TutorPublic | undefined;
  try {
    initialTutor = await getPublicTutor(slug, 1);
  } catch (error) {
    // Keep the build resilient when the public API is offline. The client will retry on visit.
    console.warn(`[prerender] Could not load public tutor profile for ${slug}; it will load on visit.`, error instanceof Error ? error.message : error);
  }
  return <AppShell title="Hồ sơ gia sư" mode="public"><PublicTutor key={slug} slug={slug} initialTutor={initialTutor} /></AppShell>;
}
