"use client";

import { initials, isActive } from "@/components/layout/navigation-utils";
import type { User } from "@/lib/data/types";
import "@/styles/landing.css";
import Link from "next/link";

export function TutorWorkspaceProfile({ user, pathname }: { user: User; pathname: string }) {
  const tabs = [
    { href: "/profile", label: "Hồ sơ" },
    { href: "/me/listings", label: "Dịch vụ" },
    { href: "/programs", label: "Chương trình học" },
    { href: "/me/availability", label: "Lịch khả dụng" },
  ];
  return <section className="tutor-workspace-profile" aria-label="Hồ sơ gia sư">
    <div className="tutor-profile-cover" aria-hidden="true" />
    <div className="tutor-profile-identity">
      <div className="tutor-profile-avatar" aria-label={`Ảnh đại diện ${user.full_name}`}>{initials(user.full_name)}</div>
      <div className="tutor-profile-name"><p>HỒ SƠ GIA SƯ</p><h1>{user.full_name}</h1><span>Gia sư · TutorLink</span></div>
    </div>
    <nav className="tutor-profile-tabs" aria-label="Khu vực gia sư">
      {tabs.map((tab) => <Link href={tab.href} key={tab.href} aria-current={isActive(pathname, tab.href) ? "page" : undefined}>{tab.label}</Link>)}
    </nav>
  </section>;
}
