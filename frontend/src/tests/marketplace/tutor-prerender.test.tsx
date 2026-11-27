import PublicTutorPage, { dynamic, dynamicParams, generateStaticParams, revalidate } from "@/app/tutors/[slug]/page";
import { getFeaturedListingCandidates, getPublicTutor } from "@/lib/data/marketplace";
import { render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/data/marketplace", () => ({ getFeaturedListingCandidates: vi.fn(), getPublicTutor: vi.fn() }));
vi.mock("@/components/layout/app-shell", () => ({ AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock("@/components/tutors/public-tutor", () => ({ PublicTutor: ({ slug, initialTutor }: { slug: string; initialTutor?: { name: string } }) => <div data-testid="tutor-profile">{slug}:{initialTutor?.name ?? "client-load"}</div> }));

beforeEach(() => vi.resetAllMocks());

it("prebuilds at most six distinct tutor shells from public listing candidates", async () => {
  vi.mocked(getFeaturedListingCandidates).mockResolvedValue({ data: ["a", "a", null, "b", "c", "d", "e", "f", "g"].map(tutor_slug => ({ tutor_slug })) } as never);
  await expect(generateStaticParams()).resolves.toEqual(["a", "b", "c", "d", "e", "f"].map(slug => ({ slug })));
  expect(dynamic).toBe("force-static");
  expect(dynamicParams).toBe(true);
  expect(revalidate).toBe(300);
});

it("never puts a mutable tutor snapshot into generated HTML", async () => {
  render(await PublicTutorPage({ params: Promise.resolve({ slug: "anna" }) }));
  expect(screen.getByTestId("tutor-profile")).toHaveTextContent("anna:client-load");
  expect(getPublicTutor).not.toHaveBeenCalled();
});

it("keeps offline builds working and renders unlisted slugs on demand", async () => {
  vi.spyOn(console, "warn").mockImplementation(() => undefined);
  vi.mocked(getFeaturedListingCandidates).mockRejectedValue(new Error("API offline"));
  await expect(generateStaticParams()).resolves.toEqual([]);
});
