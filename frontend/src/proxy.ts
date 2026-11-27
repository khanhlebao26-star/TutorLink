import { AUTH_HINT_COOKIE } from "@/lib/auth-navigation";
import { NextResponse, type NextRequest } from "next/server";

export async function proxy(request: NextRequest) {
  const isNavigation = request.method === "GET" || request.method === "HEAD";
  if (request.nextUrl.pathname.startsWith("/tutors/")) {
    const profilePath = request.nextUrl.pathname.match(/^\/tutors\/([^/]+)\/?$/);
    let response = NextResponse.next();
    if (isNavigation && profilePath) {
      try {
        const apiOrigin = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "").replace(/\/api\/v1$/, "");
        const slug = encodeURIComponent(decodeURIComponent(profilePath[1]));
        const visibility = await fetch(`${apiOrigin}/api/v1/tutors/${slug}?listings_page=1`, {
          cache: "no-store",
          redirect: "error",
          signal: AbortSignal.timeout(5_000),
          headers: { Accept: "application/json" },
        });
        if (visibility.status === 403 || visibility.status === 404) {
          response = NextResponse.rewrite(new URL("/404", request.url), { status: 404 });
        }
      } catch {
        // The shell contains no profile data; the client can show a retryable API error.
      }
    }
    response.headers.set("Cache-Control", "private, no-store");
    return response;
  }
  // Sessions can live on the API host. An absent hint lets the page check /me.
  const knownGuest = request.cookies.get(AUTH_HINT_COOKIE)?.value === "0";
  if (!isNavigation || !knownGuest) {
    return NextResponse.next();
  }

  const login = request.nextUrl.clone();
  login.pathname = "/auth/login";
  login.search = "";
  login.searchParams.set("next", `${request.nextUrl.pathname}${request.nextUrl.search}`);

  const response = NextResponse.redirect(login);
  response.headers.set("Cache-Control", "private, no-store");
  return response;
}

export const config = {
  matcher: [
    "/tutors/:slug",
    "/admin/:path*",
    "/favorites",
    "/invitations/:path*",
    "/learning-profile",
    "/me/:path*",
    "/messages/:path*",
    "/monitoring",
    "/notifications",
    "/profile",
    "/programs/:path*",
    "/reports",
    "/settings/:path*",
  ],
};
