"use client";

import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/styles/landing.css";
import Link from "next/link";
import { useCallback, useId, useRef, useState } from "react";

export function ServicesNavMenu({ onNavigate }: { onNavigate: () => void }) {
  const [open, setOpen] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const menuId = useId();
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog);

  return (
    <div className={`public-nav-dropdown${open ? " is-open" : ""}`} onMouseLeave={() => setOpen(false)} onBlur={(event) => {
      if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
    }} onKeyDown={(event) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        trigger.current?.focus();
      }
    }}>
      <button
        ref={trigger}
        className="public-nav-trigger"
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        onClick={() => setOpen((value) => !value)}
        onMouseEnter={() => setOpen(true)}
        onFocus={() => setOpen(true)}
      >
        Dịch vụ
      </button>
      <div className="public-nav-menu" id={menuId} role="group" aria-label="Danh mục dịch vụ">
        <strong className="public-nav-menu-title">Khám phá theo danh mục</strong>
        {catalog.loading ? <span className="public-nav-menu-state">Đang tải danh mục…</span> : catalog.error ? <span className="public-nav-menu-state">Chưa tải được danh mục.</span> : catalog.data?.length ? catalog.data.map((category) => (
          <Link
            href={`/marketplace?category=${category.id}`}
            key={category.id}
            onClick={() => { setOpen(false); onNavigate(); }}
          >
            <span>{category.name}</span><span aria-hidden="true">→</span>
          </Link>
        )) : <span className="public-nav-menu-state">Chưa có danh mục dịch vụ.</span>}
      </div>
    </div>
  );
}
