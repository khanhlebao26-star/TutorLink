"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";

// Keep each list's filter and page in its own URL, including browser Back/Forward.
export function useListFilters(statuses: readonly string[]) {
  const params = useSearchParams();
  const pathname = usePathname();
  const router = useRouter();
  const candidate = params.get("status") || "all";
  const status = statuses.includes(candidate) ? candidate : "all";
  const candidatePage = Number(params.get("page") || 1);
  const page = Number.isSafeInteger(candidatePage) && candidatePage > 0 ? candidatePage : 1;
  const update = (nextStatus: string, nextPage: number) => {
    const next = new URLSearchParams(params.toString());
    if (nextStatus === "all") next.delete("status"); else next.set("status", nextStatus);
    if (nextPage === 1) next.delete("page"); else next.set("page", String(nextPage));
    router.push(`${pathname}${next.size ? `?${next}` : ""}`, { scroll: false });
  };
  return { status, page, setStatus: (value: string) => update(value, 1), setPage: (value: number) => update(status, value) };
}
