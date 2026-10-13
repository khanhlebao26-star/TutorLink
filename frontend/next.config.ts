import type { NextConfig } from "next";

const nextConfig: NextConfig = {
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
