"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

const sections = [
  { href: "/me/listings", label: "Dịch vụ", description: "Quản lý dịch vụ và trạng thái công khai" },
  { href: "/programs", label: "Chương trình học", description: "Theo dõi học viên và tiến độ giảng dạy" },
];

export function TeachingManagement({ userId }: { userId: number }) {
  const pathname = usePathname();
  const search = useSearchParams().toString();
  const [saved, setSaved] = useState<Record<string, string>>({});
  useEffect(() => {
    try {
      const values: Record<string, string> = {};
      for (const section of sections) {
        const key = `teaching:${userId}:${section.href}`;
        if (pathname === section.href) sessionStorage.setItem(key, search);
        values[section.href] = sessionStorage.getItem(key) || "";
      }
      setSaved(values);
    } catch { /* Navigation still works when browser storage is unavailable. */ }
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
        return <Link key={section.href} href={`${section.href}${saved[section.href] ? `?${saved[section.href]}` : ""}`} aria-current={active ? "page" : undefined}>
          <span className="teaching-tab-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none">{index === 0 ? <><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></> : <><path d="M12 6C9 4 6 4 3 5v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1Zm0 0v14"/></>}</svg></span>
          <span><strong>{section.label}</strong><small>{section.description}</small></span>
        </Link>;
      })}
    </nav>
  </section>;
}
