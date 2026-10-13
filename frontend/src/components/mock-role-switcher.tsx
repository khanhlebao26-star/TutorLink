"use client";

import { useSyncExternalStore } from "react";
import { dataMode, setAuthHint } from "@/lib/api/client";
import { getMockRole, mockCurrentUser, setMockRole } from "@/lib/data/mock";
import type { Role } from "@/lib/data/types";

const destinations: Record<Role, string> = { customer: "/marketplace", tutor: "/profile", admin: "/admin" };

function subscribe(update: () => void) {
  window.addEventListener("storage", update);
  window.addEventListener("tutorlink-mock-role", update);
  return () => {
    window.removeEventListener("storage", update);
    window.removeEventListener("tutorlink-mock-role", update);
  };
}

export function MockRoleSwitcher() {
  const role = useSyncExternalStore(subscribe, getMockRole, () => "tutor" as Role);
  if (dataMode !== "mock") return null;

  return (
    <aside className="mock-preview-bar" aria-label="Xem thử bằng dữ liệu mẫu">
      <strong>Dữ liệu mẫu</strong>
      <label htmlFor="mock-role">Xem giao diện:</label>
      <select id="mock-role" value={role} onChange={(event) => {
        const nextRole = event.target.value as Role;
        setMockRole(nextRole);
        setAuthHint(true);
        window.location.assign(destinations[nextRole]);
      }}>
        <option value="customer">Học viên</option>
        <option value="tutor">Gia sư</option>
        <option value="admin">Admin</option>
      </select>
      <span>{mockCurrentUser(role).full_name}</span>
      <a href={destinations[role]}>Mở khu vực của role này →</a>
    </aside>
  );
}
