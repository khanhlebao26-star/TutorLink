import Link from "next/link";
import type { ReactNode } from "react";

export function Button({ children, type = "button", disabled = false, onClick }: { children: ReactNode; type?: "button" | "submit"; disabled?: boolean; onClick?: () => void }) {
  return <button className="button" type={type} disabled={disabled} onClick={onClick}>{children}</button>;
}

export function Field({ label, htmlFor, error, hint, children }: { label: string; htmlFor: string; error?: string; hint?: string; children: ReactNode }) {
  return <label className="field" htmlFor={htmlFor}><span className="field-label">{label}</span>{children}{hint && !error ? <span className="field-hint">{hint}</span> : null}{error ? <span className="field-error" role="alert">{error}</span> : null}</label>;
}

export function StatePanel({ kind, message, action }: { kind: "loading" | "empty" | "error"; message: string; action?: ReactNode }) {
  return <div className={`state-panel state-${kind}`} role={kind === "error" ? "alert" : "status"}><strong>{kind === "loading" ? "Đang tải" : kind === "empty" ? "Chưa có dữ liệu" : "Không thể tải dữ liệu"}</strong><span>{message}</span>{action}</div>;
}

export function ErrorActions({ login = false, onRetry }: { login?: boolean; onRetry: () => void }) {
  return <div className="button-row">{login ? <Link className="button" href="/auth/login">Đăng nhập</Link> : null}<button className="button button-secondary" type="button" onClick={onRetry}>Thử lại</button></div>;
}

export function DataTable<T>({ columns, rows, rowKey }: { columns: Array<{ key: string; label: string; render: (row: T) => ReactNode }>; rows: T[]; rowKey: (row: T) => string | number }) {
  return <div className="table-wrap"><table className="data-table"><thead><tr>{columns.map((column) => <th key={column.key} scope="col">{column.label}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={rowKey(row)}>{columns.map((column) => <td key={column.key}>{column.render(row)}</td>)}</tr>)}</tbody></table></div>;
}

export function Badge({ children, tone = "neutral" }: { children: ReactNode; tone?: "neutral" | "success" | "warning" }) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}
