import PublicTutorPage, { dynamic, dynamicParams, generateStaticParams, revalidate } from "@/app/tutors/[slug]/page";
import { getFeaturedListingCandidates, getPublicTutor } from "@/lib/data/marketplace";
import { render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/data/marketplace", () => ({ getFeaturedListingCandidates: vi.fn(), getPublicTutor: vi.fn() }));
vi.mock("@/components/layout/app-shell", () => ({ AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock("@/components/tutors/public-tutor", () => ({ PublicTutor: ({ slug, initialTutor }: { slug: string; initialTutor?: { name: string | null } }) => <div data-testid="tutor-profile">{slug}:{initialTutor?.name ?? "client-load"}</div> }));

beforeEach(() => vi.clearAllMocks());

it("uses a bounded set of unique public tutor slugs for ISR prebuilds", async () => {
  vi.mocked(getFeaturedListingCandidates).mockResolvedValue({ data: [
    { tutor_slug: "tutor-a" }, { tutor_slug: "tutor-a" }, { tutor_slug: null },
    { tutor_slug: "tutor-b" }, { tutor_slug: "tutor-c" }, { tutor_slug: "tutor-d" },
    { tutor_slug: "tutor-e" }, { tutor_slug: "tutor-f" }, { tutor_slug: "tutor-g" },
  ] } as never);

  await expect(generateStaticParams()).resolves.toEqual([
    { slug: "tutor-a" }, { slug: "tutor-b" }, { slug: "tutor-c" },
    { slug: "tutor-d" }, { slug: "tutor-e" }, { slug: "tutor-f" },
  ]);
  expect(dynamic).toBe("force-static");
  expect(dynamicParams).toBe(true);
  expect(revalidate).toBe(300);
});

it("keeps the build working when the public listing API is unavailable", async () => {
  const warning = vi.spyOn(console, "warn").mockImplementation(() => undefined);
  vi.mocked(getFeaturedListingCandidates).mockRejectedValue(new Error("API offline"));
  await expect(generateStaticParams()).resolves.toEqual([]);
  expect(warning).toHaveBeenCalledWith(expect.stringContaining("profiles will render on demand"), "API offline");
});

it("renders a client-retry shell when the tutor API is unavailable during prerender", async () => {
  const warning = vi.spyOn(console, "warn").mockImplementation(() => undefined);
  vi.mocked(getPublicTutor).mockRejectedValue(new Error("API offline"));
  const page = await PublicTutorPage({ params: Promise.resolve({ slug: "new-tutor" }) });
  render(page);
  expect(screen.getByTestId("tutor-profile")).toHaveTextContent("new-tutor:client-load");
  expect(warning).toHaveBeenCalledWith(expect.stringContaining("will load on visit"), "API offline");
});
