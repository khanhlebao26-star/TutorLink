"use client";

import { ListIcon } from "@phosphor-icons/react/dist/ssr/List";

export function MenuIcon() {
  return <ListIcon className="block h-6 w-6" weight="regular" aria-hidden="true" />;
}

export function BrandMark() {
  return <svg className="mr-[5px] h-[31px] w-[34px] flex-[0_0_34px] text-[var(--accent)]" viewBox="0 0 32 28" fill="none" aria-hidden="true">
    <path d="M2 4.2c5.1-1.9 9.7-.8 14 3.1v17c-4.3-3.9-8.9-5-14-3.1v-17Z" fill="currentColor" />
    <path d="M30 4.2c-5.1-1.9-9.7-.8-14 3.1v17c4.3-3.9 8.9-5 14-3.1v-17Z" fill="currentColor" />
    <path d="M16 7.4v16.8" stroke="white" strokeWidth="1.5" />
  </svg>;
}

export function LocaleDisplay() {
  return (
    <span className="locale-display hidden items-center gap-[7px] whitespace-nowrap text-[.8125rem] font-bold text-[var(--brand)] sm:inline-flex">
      <span className="text-base leading-none" aria-hidden="true">🇻🇳</span>
      Tiếng Việt
    </span>
  );
}
