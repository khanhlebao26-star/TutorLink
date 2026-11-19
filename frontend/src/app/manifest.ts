import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "TutorLink",
    short_name: "TutorLink",
    description: "Marketplace kết nối Customer và Tutor",
    start_url: "/",
    display: "standalone",
    background_color: "#f8f7ff",
    theme_color: "#071a4c",
    icons: [
      {
        src: "/icon.svg",
        sizes: "any",
        type: "image/svg+xml",
        purpose: "any",
      },
    ],
  };
}
