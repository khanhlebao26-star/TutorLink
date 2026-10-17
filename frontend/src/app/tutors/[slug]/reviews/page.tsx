import { AppShell } from "@/components/app-shell";
import { VisibleReviews } from "@/components/visible-reviews";
import "@/app/public-detail.css";

export default async function PublicTutorReviewsPage({
  params,
  searchParams,
}: {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string | string[] }>;
}) {
  const [{ slug }, { page: rawPage }] = await Promise.all([params, searchParams]);
  const pageValue = Array.isArray(rawPage) ? rawPage[0] : rawPage;
  const requestedPage = pageValue ? Number(pageValue) : 1;
  const page = Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1;

  return <AppShell title="Nhận xét gia sư" mode="public"><VisibleReviews slug={slug} page={page} /></AppShell>;
}
