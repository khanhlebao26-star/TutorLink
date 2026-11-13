"use client";

export function MenuIcon() {
  return <span className="menu-icon" aria-hidden="true"><span /><span /><span /></span>;
}

export function BrandMark() {
  return <svg className="brand-mark" viewBox="0 0 32 28" fill="none" aria-hidden="true">
    <path d="M2 4.2c5.1-1.9 9.7-.8 14 3.1v17c-4.3-3.9-8.9-5-14-3.1v-17Z" fill="currentColor" />
    <path d="M30 4.2c-5.1-1.9-9.7-.8-14 3.1v17c4.3-3.9 8.9-5 14-3.1v-17Z" fill="currentColor" />
    <path d="M16 7.4v16.8" stroke="white" strokeWidth="1.5" />
  </svg>;
}

export function LocaleDisplay() {
  return (
    <span className="locale-display">
      <span className="locale-flag" aria-hidden="true">🇻🇳</span>
      Tiếng Việt
    </span>
  );
}
