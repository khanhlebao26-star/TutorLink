import type { TutorPublic } from "@/lib/data/marketplace-types";

// Presentation fixtures keyed by the listings in LocalFeaturedDemoSeeder.
// Public tutors without a demo listing keep the values supplied by the API.
const demoProfiles = [
  { listing: "demo-featured-math-online", students_taught_count: 120, response_time_minutes: 15 },
  { listing: "demo-featured-strength-training", students_taught_count: 180, response_time_minutes: 15 },
  { listing: "demo-featured-badminton-beginners", students_taught_count: 95, response_time_minutes: 15 },
];

export function tutorProfileHighlights(tutor: TutorPublic) {
  const demo = demoProfiles.find((profile) => tutor.listings.some((listing) => listing.slug === profile.listing));
  return {
    studentsTaught: tutor.students_taught_count ?? demo?.students_taught_count ?? null,
    responseMinutes: tutor.response_time_minutes ?? demo?.response_time_minutes ?? null,
  };
}
