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

export function Button({
  children,
  type = "button",
  variant = "primary",
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: "primary" | "secondary" | "outline" | "ghost" | "danger" }) {
  return (
    <button className={joinClasses("button", `button-${variant}`, className)} type={type} {...props}>
      {children}
    </button>
  );
}

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={joinClasses("input", className)} {...props} />;
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={joinClasses("select", className)} {...props} />;
}

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={joinClasses("card", className)} {...props} />;
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
    <div className="field">
      <label className="field-label" htmlFor={htmlFor}>{label}</label>
      {child}
      {error ? <span className="field-error" id={errorId} role="alert">{error}</span> : null}
      {hint && !error ? <span className="field-hint" id={hintId}>{hint}</span> : null}
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
      <strong>
        {kind === "loading" ? "Đang tải" : kind === "empty" ? "Chưa có dữ liệu" : "Không thể tải dữ liệu"}
      </strong>
      <span>{message}</span>
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
              {columns.map((column) => <td key={column.key}>{column.render(row)}</td>)}
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
  return <span className={joinClasses("badge", `badge-${tone}`, className)} {...props}>{children}</span>;
}
