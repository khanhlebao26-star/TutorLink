"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useSyncExternalStore } from "react";

const sections = [
  { href: "/me/listings", label: "Dịch vụ", description: "Quản lý dịch vụ và trạng thái công khai" },
  { href: "/programs", label: "Chương trình học", description: "Theo dõi học viên và tiến độ giảng dạy" },
  { href: "/me/availability", label: "Lịch khả dụng", description: "Thiết lập khung giờ bạn có thể dạy" },
];

function subscribeToQueries(update: () => void) {
  window.addEventListener("teaching-query-updated", update);
  return () => window.removeEventListener("teaching-query-updated", update);
}

export function TeachingManagement({ userId }: { userId: number }) {
  const pathname = usePathname();
  const search = useSearchParams().toString();
  const savedQueries = useSyncExternalStore(subscribeToQueries, () => {
    try {
      return JSON.stringify(sections.map(({ href }) => sessionStorage.getItem(`teaching:${userId}:${href}`) || ""));
    } catch { return "[]"; }
  }, () => "[]");
  const saved: string[] = JSON.parse(savedQueries);

  useEffect(() => {
    if (!sections.some(({ href }) => href === pathname)) return;
    try {
      sessionStorage.setItem(`teaching:${userId}:${pathname}`, search);
      window.dispatchEvent(new Event("teaching-query-updated"));
    } catch { /* Navigation also works without saved filters. */ }
  }, [pathname, search, userId]);

  const editingProgram = pathname === "/programs/new" || /^\/programs\/\d+\/edit$/.test(pathname);
  return <section className={`teaching-management${editingProgram ? " teaching-management-compact" : ""}`} aria-label="Quản lý giảng dạy">
    <div className="teaching-management-heading">
      <h1>Quản lý giảng dạy</h1>
      <p>Dịch vụ, học viên và chương trình giảng dạy của bạn trong cùng một không gian.</p>
    </div>
    <nav className="teaching-management-tabs" aria-label="Các mục quản lý giảng dạy">
      {sections.map((section, index) => {
        const active = pathname === section.href || pathname.startsWith(`${section.href}/`);
        return <Link key={section.href} href={`${section.href}${saved[index] ? `?${saved[index]}` : ""}`} aria-current={active ? "page" : undefined}>
          <span className="teaching-tab-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none">{index === 0 ? <><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></> : index === 1 ? <><path d="M12 6C9 4 6 4 3 5v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1Zm0 0v14"/></> : <><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M7.5 3v4M16.5 3v4M3.5 10h17"/></>}</svg></span>
          <span><strong>{section.label}</strong><small>{section.description}</small></span>
        </Link>;
      })}
    </nav>
  </section>;
}
