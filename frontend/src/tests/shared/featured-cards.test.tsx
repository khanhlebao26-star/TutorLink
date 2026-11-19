import { FeaturedServices } from "@/components/marketing/featured-services";
import { FeaturedListingProvider } from "@/components/marketing/featured-listing-provider";
import { FeaturedTutors } from "@/components/marketing/featured-tutors";
import { publicRequest as request } from "@/lib/api/client";
import { render, screen } from "@testing-library/react";
import { expect, it, vi } from "vitest";
vi.mock("@/lib/api/client", () => ({ publicRequest: vi.fn() }));

it("renders both homepage sections from one compact request without profile or review fetches", async () => {
  vi.mocked(request).mockResolvedValue({ data: { data: [{ id: 1, slug: "english", title: "English lessons", description: "Conversation",
    status: "active", tutor_slug: "tutor-one", tutor_name: "Tutor One", price_from_minor: 200000, price_unit: "hour",
    tutor_card: { headline: "English tutor", experience_years: 4, rating_count: 3, rating_average: 5 } }],
    meta: { total: 1, current_page: 1, per_page: 50 } } } as never);
  render(<><FeaturedServices /><FeaturedTutors /></>);
  await screen.findByRole("heading", { name: /^Tutor One/ });
  await screen.findByRole("heading", { name: "English lessons" });
  expect(request).toHaveBeenCalledTimes(1);
  expect(request).toHaveBeenCalledWith(expect.objectContaining({ url: "/listings", params: { sort: "newest", per_page: 50, include: "tutor_card" } }));
});

it('reuses the homepage snapshot when the tutor section mounts after services resolve', async () => {
  vi.mocked(request).mockClear();
  vi.mocked(request).mockResolvedValue({data:{data:[],meta:{total:0,current_page:1,per_page:50}}} as never);
  const view = render(<FeaturedListingProvider><FeaturedServices/></FeaturedListingProvider>);
  await screen.findByText('Chưa có dịch vụ công khai. Hãy quay lại sau hoặc khám phá danh mục dịch vụ.');
  view.rerender(<FeaturedListingProvider><FeaturedServices/><FeaturedTutors/></FeaturedListingProvider>);
  await screen.findByRole('heading',{name:'Gặp gỡ Tutor & Coach nổi bật'});
  expect(request).toHaveBeenCalledTimes(1);
});
