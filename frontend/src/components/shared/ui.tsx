import Link from "next/link";
import {
  cloneElement,
  isValidElement,
  type ButtonHTMLAttributes,
  type HTMLAttributes,
  type InputHTMLAttributes,
  type ReactElement,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";

const joinClasses = (...values: Array<string | undefined>) => values.filter(Boolean).join(" ");

const buttonBase = "inline-flex min-h-10 cursor-pointer items-center justify-center rounded-[10px] border border-transparent px-[14px] py-[9px] text-center text-[.94rem] font-bold leading-[1.25] transition-[background-color,border-color,color,box-shadow,transform] duration-150 disabled:cursor-not-allowed disabled:opacity-[.55] disabled:transform-none";
const buttonVariants = {
  primary: "border-[var(--accent)] bg-[var(--accent)] text-white shadow-[0_4px_12px_rgba(255,10,153,.14)] hover:-translate-y-px hover:border-[var(--accent-dark)] hover:bg-[var(--accent-dark)] hover:text-white hover:shadow-[0_6px_16px_rgba(255,10,153,.2)]",
  secondary: "border-[#c9d1e6] bg-white text-[var(--brand)] hover:border-[#aeb9d5] hover:bg-[var(--surface-soft)] hover:text-[var(--brand)]",
  outline: "border-[#ff89c7] bg-white text-[var(--accent-dark)] hover:border-[var(--accent)] hover:bg-[var(--accent-soft)] hover:text-[var(--accent-dark)]",
  ghost: "bg-transparent text-[var(--brand)] hover:bg-[var(--surface-soft)] hover:text-[var(--accent-dark)]",
  danger: "border-[#b42318] bg-[#b42318] text-white hover:border-[#8f1b13] hover:bg-[#8f1b13] hover:text-white",
} satisfies Record<string, string>;

export function Button({
  children,
  type = "button",
  variant = "primary",
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: "primary" | "secondary" | "outline" | "ghost" | "danger" }) {
  return (
    <button className={joinClasses("button", "button-base", buttonBase, buttonVariants[variant], className)} type={type} {...props}>
      {children}
    </button>
  );
}

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={joinClasses("block w-full rounded-lg border border-[var(--line)] bg-white px-[11px] py-2 text-[var(--foreground)] disabled:cursor-not-allowed disabled:bg-[#f0f2f8] disabled:text-[#8692b0] aria-[invalid=true]:border-[var(--danger)]", className)} {...props} />;
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={joinClasses("block min-h-[38px] w-full rounded-lg border border-[var(--line)] bg-white px-[11px] py-2 text-[var(--foreground)] disabled:cursor-not-allowed disabled:bg-[#f0f2f8] disabled:text-[#8692b0] aria-[invalid=true]:border-[var(--danger)]", className)} {...props} />;
}

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={joinClasses("card rounded-[10px] border border-[var(--line)] bg-[var(--surface)] p-[18px] shadow-[0_4px_14px_rgba(7,26,76,.035)]", className)} {...props} />;
}

type FieldControlProps = {
  id?: string;
  "aria-invalid"?: boolean | "true" | "false";
  "aria-describedby"?: string;
};

export function Field({
  label,
  htmlFor,
  error,
  hint,
  children,
}: {
  label: string;
  htmlFor: string;
  error?: string;
  hint?: string;
  children: ReactNode;
}) {
  const hintId = `${htmlFor}-hint`;
  const errorId = `${htmlFor}-error`;
  const child = isValidElement<FieldControlProps>(children) && (
    children.type === "input" || children.type === "textarea" || children.type === "select" ||
    children.type === Input || children.type === Select
  )
    ? cloneElement(children as ReactElement<FieldControlProps>, {
        id: children.props.id ?? htmlFor,
        "aria-invalid": error ? true : children.props["aria-invalid"],
        "aria-describedby": [
          children.props["aria-describedby"],
          error ? errorId : hint ? hintId : undefined,
        ]
          .filter(Boolean)
          .join(" ") || undefined,
      })
    : children;

  return (
    <div className="field grid gap-1.5">
      <label className="field-label text-[.8125rem] font-bold text-[var(--brand)]" htmlFor={htmlFor}>{label}</label>
      {child}
      {error ? <span className="field-error text-[.8125rem] leading-[1.45] text-[var(--danger)]" id={errorId} role="alert">{error}</span> : null}
      {hint && !error ? <span className="field-hint text-[.8125rem] leading-[1.45] text-[var(--muted)]" id={hintId}>{hint}</span> : null}
    </div>
  );
}

export function StatePanel({
  kind,
  message,
  action,
}: {
  kind: "loading" | "empty" | "error";
  message: string;
  action?: ReactNode;
}) {
  return (
    <div className={`state-panel state-${kind}`} role={kind === "error" ? "alert" : "status"}>
      {kind === "loading" ? <span className="state-loading-spinner" aria-hidden="true"><svg viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="15"/><path d="M20 5a15 15 0 0 1 15 15"/></svg></span> : null}
      <span className={kind === "loading" ? "state-loading-copy" : undefined}>
        <strong>
          {kind === "loading" ? "Đang tải" : kind === "empty" ? "Chưa có dữ liệu" : "Không thể tải dữ liệu"}
        </strong>
        <span>{message}</span>
        {kind === "loading" ? <span className="state-loading-progress" aria-hidden="true"><i /></span> : null}
      </span>
      {action}
    </div>
  );
}

export function ErrorActions({
  login = false,
  onRetry,
}: {
  login?: boolean;
  onRetry: () => void;
}) {
  return (
    <div className="button-row">
      {login ? <Link className="button button-secondary" href="/auth/login">Đăng nhập</Link> : null}
      <Button variant="secondary" onClick={onRetry}>Thử lại</Button>
    </div>
  );
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
}: {
  columns: Array<{ key: string; label: string; render: (row: T) => ReactNode }>;
  rows: T[];
  rowKey: (row: T) => string | number;
}) {
  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>{columns.map((column) => <th key={column.key} scope="col">{column.label}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)}>
              {columns.map((column) => <td key={column.key} data-label={column.label}>{column.render(row)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function Badge({
  children,
  tone = "neutral",
  className,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: "neutral" | "success" | "warning" | "danger" | "brand" }) {
  const tones = {
    neutral: "bg-[#eef1f8] text-[#4b5b73]",
    success: "bg-[#e8f7ef] text-[#147d52]",
    warning: "bg-[#fff4d6] text-[var(--warning)]",
    danger: "bg-[#fff0ef] text-[var(--danger)]",
    brand: "bg-[var(--accent-soft)] text-[var(--accent-dark)]",
  } satisfies Record<string, string>;
  return <span className={joinClasses("badge", `badge-${tone}`, "inline-flex whitespace-nowrap rounded-full px-[9px] py-[5px] text-[.74rem] font-extrabold leading-[1.2]", tones[tone], className)} {...props}>{children}</span>;
}
