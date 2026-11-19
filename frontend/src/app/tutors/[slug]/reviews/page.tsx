import { redirect } from "next/navigation";

export default async function PublicTutorReviewsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  redirect(`/tutors/${encodeURIComponent(slug)}?tab=reviews`);
}
