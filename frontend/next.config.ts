import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  distDir: process.env.TUTORLINK_NEXT_DIST_DIR ?? ".next",
  async headers() {
    const api = new URL(process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").origin;
    const realtime = new URL(process.env.NEXT_PUBLIC_REVERB_URL ?? "ws://localhost:8080").origin;
    // Static Next pages require inline bootstrap scripts; no unsafe-eval in production.
    const csp = ["default-src 'self'", `connect-src 'self' ${api} ${realtime}`,
      `script-src 'self' 'unsafe-inline'${process.env.NODE_ENV === "development" ? " 'unsafe-eval'" : ""}`,
      "style-src 'self' 'unsafe-inline'", `img-src 'self' data: blob: ${api}`,
      "font-src 'self' data:", "object-src 'none'", "base-uri 'self'",
      "form-action 'self'", "frame-ancestors 'none'"].join("; ");
    return [{ source: "/:path*", headers: [
      { key: "Content-Security-Policy", value: csp },
      { key: "X-Frame-Options", value: "DENY" },
      { key: "X-Content-Type-Options", value: "nosniff" },
      { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
      ...(process.env.NODE_ENV === "production" ? [{ key: "Strict-Transport-Security", value: "max-age=31536000" }] : []),
    ] }];
  },
  redirects() {
    return [
      {
        source: "/:path*",
        has: [{ type: "host", value: "0\\.0\\.0\\.0" }],
        destination: "http://localhost:3000/:path*",
        permanent: false,
      },
    ];
  },
};

export default nextConfig;
