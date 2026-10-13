import { AppShell } from "@/components/app-shell";
import { VisibleReviews } from "@/components/visible-reviews";
import "@/app/public-detail.css";

export default async function PublicTutorReviewsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return <AppShell title="Nhận xét gia sư" mode="public"><VisibleReviews slug={slug} /></AppShell>;
}
