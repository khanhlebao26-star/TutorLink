"use client";

import { initials } from "@/components/layout/navigation-utils";
import type { User } from "@/lib/data/types";

export function TutorWorkspaceProfile({ user }: { user: User }) {
  return <section className="tutor-workspace-profile" aria-label="Hồ sơ gia sư">
    <div className="tutor-profile-cover" aria-hidden="true" />
    <div className="tutor-profile-identity">
      <div className="tutor-profile-avatar" aria-label={`Ảnh đại diện ${user.full_name}`}>{initials(user.full_name)}</div>
      <div className="tutor-profile-name"><p>HỒ SƠ GIA SƯ</p><h1>{user.full_name}</h1><span>Gia sư · TutorLink</span></div>
    </div>
  </section>;
}
