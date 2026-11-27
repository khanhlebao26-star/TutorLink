// @vitest-environment node

import { AUTH_HINT_COOKIE, safeLoginReturnTo } from "@/lib/auth-navigation";
import { config, proxy } from "@/proxy";
import { getRedirectUrl, unstable_doesMiddlewareMatch } from "next/experimental/testing/server";
import { NextRequest } from "next/server";
import { afterEach, expect, it, vi } from "vitest";

const origin = "https://app.example.test";

afterEach(() => { vi.unstubAllEnvs(); vi.unstubAllGlobals(); });

it("matches account routes and tutor profiles while leaving unrelated public pages and assets outside the proxy", () => {
  const matches = (url: string) => unstable_doesMiddlewareMatch({ config, nextConfig: {}, url });
  for (const path of ["/admin/dashboard", "/programs/7/sessions", "/invitations/8", "/me/availability", "/settings", "/monitoring", "/favorites", "/tutors/tutor-a"]) {
    expect(matches(path)).toBe(true);
  }
  for (const path of ["/", "/marketplace", "/auth/login", "/auth/verify-email", "/api/v1/me", "/_next/static/chunk.js", "/favicon.ico"]) {
    expect(matches(path)).toBe(false);
  }
});

it("redirects a known guest without caching the redirect and preserves the requested URL", async () => {
  const destination = "/programs/7/sessions?date=2026-11-15";
  const request = new NextRequest(origin + destination, { headers: { Cookie: `${AUTH_HINT_COOKIE}=0` } });
  const response = await proxy(request);
  const redirect = new URL(getRedirectUrl(response)!);

  expect(response.status).toBe(307);
  expect(response.headers.get("cache-control")).toBe("private, no-store");
  expect(redirect.origin).toBe(origin);
  expect(redirect.pathname).toBe("/auth/login");
  expect(redirect.searchParams.get("next")).toBe(destination);
});

it("allows the page to verify a possible session and skips form submissions", async () => {
  const route = origin + "/profile";
  expect(getRedirectUrl(await proxy(new NextRequest(route)))).toBeNull();
  expect(getRedirectUrl(await proxy(new NextRequest(route, { headers: { Cookie: `${AUTH_HINT_COOKIE}=1` } })))).toBeNull();

  const guestHeaders = { Cookie: `${AUTH_HINT_COOKIE}=0` };
  expect(getRedirectUrl(await proxy(new NextRequest(route, { method: "POST", headers: guestHeaders })))).toBeNull();

});

it("does not let a legacy mock setting bypass the guest redirect", async () => {
  vi.stubEnv("NEXT_PUBLIC_DATA_MODE", "mock");
  const request = new NextRequest(origin + "/profile", { headers: { Cookie: `${AUTH_HINT_COOKIE}=0` } });
  expect(new URL(getRedirectUrl(await proxy(request))!).pathname).toBe("/auth/login");
});

it("accepts local destinations and rejects external, malformed and recursive login redirects", () => {
  expect(safeLoginReturnTo("/settings/password?via=account#password")).toBe("/settings/password?via=account#password");
  for (const value of ["https://others.example/", "//others.example/", "/\\others.example/", "/x/..//others.example/", "/\n/[", "/auth/login", "javascript:alert(1)", ["/settings", "/admin"]]) {
    expect(safeLoginReturnTo(value)).toBeUndefined();
  }
});


it.each([403, 404])("returns an uncached not-found rewrite for a withdrawn tutor (%s)", async (status) => {
  const fetcher = vi.fn().mockResolvedValue(new Response(null, { status }));
  vi.stubGlobal("fetch", fetcher);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.example.test/api/v1/");
  const response = await proxy(new NextRequest(origin + "/tutors/tutor-a", { headers: { Cookie: `${AUTH_HINT_COOKIE}=0`, Authorization: "Bearer must-not-forward" } }));
  expect(response.status).toBe(404);
  expect(response.headers.get("x-middleware-rewrite")).toBe(origin + "/404");
  expect(response.headers.get("cache-control")).toBe("private, no-store");
  expect(fetcher).toHaveBeenCalledWith("https://api.example.test/api/v1/tutors/tutor-a?listings_page=1", expect.objectContaining({ cache: "no-store", redirect: "error", headers: { Accept: "application/json" } }));
});

it.each([200, 429, 500])("lets the data-free tutor shell handle an API response of %s", async (status) => {
  vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status })));
  const response = await proxy(new NextRequest(origin + "/tutors/tutor-a"));
  expect(response.headers.get("x-middleware-next")).toBe("1");
  expect(response.headers.get("cache-control")).toBe("private, no-store");
});

it("keeps public tutor reviews and non-navigation requests outside the auth redirect", async () => {
  const fetcher = vi.fn();
  vi.stubGlobal("fetch", fetcher);
  for (const request of [new NextRequest(origin + "/tutors/tutor-a/reviews", { headers: { Cookie: `${AUTH_HINT_COOKIE}=0` } }), new NextRequest(origin + "/tutors/tutor-a", { method: "POST" })]) {
    expect(getRedirectUrl(await proxy(request))).toBeNull();
  }
  expect(fetcher).not.toHaveBeenCalled();
});

it("keeps the tutor shell retryable on an API network failure", async () => {
  vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("API offline")));
  const response = await proxy(new NextRequest(origin + "/tutors/tutor-a"));
  expect(response.headers.get("x-middleware-next")).toBe("1");
  expect(response.headers.get("cache-control")).toBe("private, no-store");
});
