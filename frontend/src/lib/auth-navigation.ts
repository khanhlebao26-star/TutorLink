export const AUTH_HINT_COOKIE = "tutorlink-auth";

const returnOrigin = "https://tutorlink.invalid";

export function safeLoginReturnTo(value: string | string[] | undefined) {
  if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) {
    return;
  }

  try {
    const url = new URL(value, returnOrigin);
    const { pathname } = url;
    if (url.origin !== returnOrigin || pathname.startsWith("//") || pathname === "/auth" || pathname.startsWith("/auth/")) {
      return;
    }

    return `${pathname}${url.search}${url.hash}`;
  } catch {
    return;
  }
}
