import { FeaturedServices } from "@/components/marketing/featured-services";
import { FeaturedTutors } from "@/components/marketing/featured-tutors";
import { request } from "@/lib/api/client";
import { render, screen } from "@testing-library/react";
import { expect, it, vi } from "vitest";
vi.mock("@/lib/api/client", () => ({ request: vi.fn() }));

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
