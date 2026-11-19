// @vitest-environment node

import { AUTH_HINT_COOKIE, safeLoginReturnTo } from "@/lib/auth-navigation";
import { config, proxy } from "@/proxy";
import { getRedirectUrl, unstable_doesMiddlewareMatch } from "next/experimental/testing/server";
import { NextRequest } from "next/server";
import { afterEach, expect, it, vi } from "vitest";

const origin = "https://app.example.test";

afterEach(() => vi.unstubAllEnvs());

it("matches account routes and leaves public pages, login, APIs and assets outside the proxy", () => {
  const matches = (url: string) => unstable_doesMiddlewareMatch({ config, nextConfig: {}, url });
  for (const path of ["/admin/dashboard", "/programs/7/sessions", "/invitations/8", "/me/availability", "/settings", "/monitoring", "/favorites"]) {
    expect(matches(path)).toBe(true);
  }
  for (const path of ["/", "/marketplace", "/tutors/tutor-a", "/auth/login", "/auth/verify-email", "/api/v1/me", "/_next/static/chunk.js", "/favicon.ico"]) {
    expect(matches(path)).toBe(false);
  }
});

it("redirects a known guest without caching the redirect and preserves the requested URL", () => {
  const destination = "/programs/7/sessions?date=2026-11-15";
  const request = new NextRequest(origin + destination, { headers: { Cookie: `${AUTH_HINT_COOKIE}=0` } });
  const response = proxy(request);
  const redirect = new URL(getRedirectUrl(response)!);

  expect(response.status).toBe(307);
  expect(response.headers.get("cache-control")).toBe("private, no-store");
  expect(redirect.origin).toBe(origin);
  expect(redirect.pathname).toBe("/auth/login");
  expect(redirect.searchParams.get("next")).toBe(destination);
});

it("allows the page to verify a possible session and skips form submissions", () => {
  const route = origin + "/profile";
  expect(getRedirectUrl(proxy(new NextRequest(route)))).toBeNull();
  expect(getRedirectUrl(proxy(new NextRequest(route, { headers: { Cookie: `${AUTH_HINT_COOKIE}=1` } })))).toBeNull();

  const guestHeaders = { Cookie: `${AUTH_HINT_COOKIE}=0` };
  expect(getRedirectUrl(proxy(new NextRequest(route, { method: "POST", headers: guestHeaders })))).toBeNull();

});

it("does not let a legacy mock setting bypass the guest redirect", () => {
  vi.stubEnv("NEXT_PUBLIC_DATA_MODE", "mock");
  const request = new NextRequest(origin + "/profile", { headers: { Cookie: `${AUTH_HINT_COOKIE}=0` } });
  expect(new URL(getRedirectUrl(proxy(request))!).pathname).toBe("/auth/login");
});

it("accepts local destinations and rejects external, malformed and recursive login redirects", () => {
  expect(safeLoginReturnTo("/settings/password?via=account#password")).toBe("/settings/password?via=account#password");
  for (const value of ["https://others.example/", "//others.example/", "/\\others.example/", "/x/..//others.example/", "/\n/[", "/auth/login", "javascript:alert(1)", ["/settings", "/admin"]]) {
    expect(safeLoginReturnTo(value)).toBeUndefined();
  }
});
