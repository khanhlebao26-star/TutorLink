"use client";

import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Link from "next/link";
import { useCallback, useId, useRef, useState } from "react";

export function ServicesNavMenu({ onNavigate }: { onNavigate: () => void }) {
  const [open, setOpen] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const menuId = useId();
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog, false);

  return (
    <div className={`public-nav-dropdown group relative flex h-auto flex-none items-center self-center max-lg:block max-lg:w-full max-lg:self-auto${open ? " is-open" : ""}`} onMouseLeave={() => setOpen(false)} onBlur={(event) => {
      if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
    }} onKeyDown={(event) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        trigger.current?.focus();
      }
    }}>
      <button
        ref={trigger}
        className="public-nav-trigger relative inline-flex h-auto flex-none cursor-pointer items-center gap-[5px] whitespace-nowrap bg-transparent p-0 text-[.8125rem] font-semibold text-brand transition-colors hover:text-accent-dark focus-visible:text-accent-dark max-lg:w-full max-lg:justify-start max-lg:px-1 max-lg:py-2.5"
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        onClick={() => setOpen((value) => !value)}
        onMouseEnter={() => setOpen(true)}
        onFocus={() => setOpen(true)}
      >
        Dịch vụ
      </button>
      <div className="public-nav-menu absolute left-[-16px] top-full z-30 hidden min-w-[230px] grid-cols-1 gap-1 rounded-xl border border-line bg-white p-2.5 shadow-[0_16px_38px_rgba(16,35,79,.14)] group-hover:grid group-focus-within:grid [&.is-open]:grid max-lg:relative max-lg:left-0 max-lg:top-auto max-lg:my-0.5 max-lg:mb-2 max-lg:w-full max-lg:min-w-0 max-lg:rounded-[10px] max-lg:shadow-none" id={menuId} role="group" aria-label="Danh mục dịch vụ">
        <strong className="public-nav-menu-title px-[9px] pb-[7px] pt-[5px] text-[.72rem] text-muted">Khám phá theo danh mục</strong>
        {catalog.loading ? <span className="public-nav-menu-state p-[9px] text-[.78rem] text-muted">Đang tải danh mục…</span> : catalog.error ? <span className="public-nav-menu-state p-[9px] text-[.78rem] text-muted">Chưa tải được danh mục.</span> : catalog.data?.length ? catalog.data.map((category) => (
          <Link
            className="flex min-h-[38px] items-center justify-between rounded-lg px-[9px] py-2 text-[.82rem] font-semibold text-brand transition-colors hover:bg-accent-soft hover:text-accent-dark focus-visible:bg-accent-soft focus-visible:text-accent-dark"
            href={`/marketplace?category=${category.id}`}
            key={category.id}
            onClick={() => { setOpen(false); onNavigate(); }}
          >
            <span>{category.name}</span><span aria-hidden="true">→</span>
          </Link>
        )) : <span className="public-nav-menu-state p-[9px] text-[.78rem] text-muted">Chưa có danh mục dịch vụ.</span>}
      </div>
    </div>
  );
}
