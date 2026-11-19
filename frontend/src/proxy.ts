import { AUTH_HINT_COOKIE } from "@/lib/auth-navigation";
import { NextResponse, type NextRequest } from "next/server";

export function proxy(request: NextRequest) {
  const isNavigation = request.method === "GET" || request.method === "HEAD";
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
