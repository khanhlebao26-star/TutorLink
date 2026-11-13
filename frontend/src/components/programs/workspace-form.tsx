"use client";
import { Button, Field, Input, Select } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { retryIdentity, workspaceWrite } from "@/lib/data/workspace";
import dynamic from "next/dynamic";
import { useRef, useState, type FormEvent } from "react";
const WorkspaceUpload = dynamic(
  () => import("@/components/programs/workspace-upload").then((module) => module.WorkspaceUpload),
  { ssr: false, loading: () => <p role="status">Đang tải trình chọn file…</p> },
);
export interface FormField {
  name: string;
  label: string;
  hint?: string;
  type?: "text" | "number" | "datetime-local" | "month" | "textarea";
  required?: boolean;
  min?: number;
  max?: number;
  maxLength?: number;
  options?: Record<string, string>;
}
export function WorkspaceForm({
  url,
  method = "POST",
  fields = [],
  initial = {},
  fixed = {},
  label,
  onSaved,
  fileUpload = false,
  fileUploadLabel,
}: {
  fileUpload?: boolean;
  fileUploadLabel?: string;
  url: string;
  method?: "POST" | "PATCH" | "PUT";
  fields?: FormField[];
  initial?: Record<string, string | number>;
  fixed?: Record<string, unknown>;
  label: string;
  onSaved: () => void;
}) {
  const [uploadBusy, setUploadBusy] = useState(false);
  const [fileIds, setFileIds] = useState<number[]>([]);
  const [values, setValues] = useState(initial),
    [busy, setBusy] = useState(false),
    [error, setError] = useState<ApiClientError | null>(null);
  const lock = useRef(false),
    receipt = useRef<{ payload: string; key: string } | null>(null);
  const existingFileCount = Array.isArray(fixed.file_ids) ? fixed.file_ids.length : 0;
  const totalFileCount = existingFileCount + fileIds.length;
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (uploadBusy || lock.current || error?.status === 409) return;
    lock.current = true;
    setBusy(true);
    setError(null);
    try {
      const payload: Record<string, unknown> = {
        ...fixed,
        ...(fileUpload
          ? {
              file_ids: [
                ...new Set([
                  ...(Array.isArray(fixed.file_ids) ? fixed.file_ids : []),
                  ...fileIds,
                ]),
              ],
            }
          : {}),
      };
      for (const field of fields) {
        const value = values[field.name];
        if (value === undefined || value === "") {
          if (field.required) throw new Error(`Vui lòng nhập ${field.label}.`);
          if (method === "PATCH" && Object.hasOwn(initial, field.name))
            payload[field.name] = null;
          continue;
        }
        if (
          field.type === "number" &&
          (!Number.isSafeInteger(Number(value)) ||
            (field.min !== undefined && Number(value) < field.min) ||
            (field.max !== undefined && Number(value) > field.max))
        )
          throw new Error(`${field.label} chưa hợp lệ.`);
        payload[field.name] =
          field.type === "number"
            ? Number(value)
            : field.type === "datetime-local"
              ? new Date(String(value)).toISOString()
              : value;
      }
      receipt.current = retryIdentity(receipt.current, {
        url,
        method,
        payload,
      });
      await workspaceWrite(url, method, payload, receipt.current.key);
      onSaved();
    } catch (e) {
      setError(
        e instanceof ApiClientError
          ? e
          : new ApiClientError(describeApiError(e)),
      );
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }
  return (
    <form onSubmit={submit} className="content-stack">
      <fieldset disabled={busy} className="workspace-fields">
        {fields.map((f) => (
          <Field
            key={f.name}
            htmlFor={`${url}-${f.name}`}
            label={f.label}
            error={error?.fieldErrors?.[f.name]?.[0]}
            hint={f.hint}
          >
            {f.options ? (
              <Select
                value={values[f.name] ?? ""}
                required={f.required}
                onChange={(e) =>
                  setValues({ ...values, [f.name]: e.target.value })
                }
              >
                <option value="">Chọn…</option>
                {Object.entries(f.options).map(([k, v]) => (
                  <option value={k} key={k}>
                    {v}
                  </option>
                ))}
              </Select>
            ) : f.type === "textarea" ? (
              <textarea
                className="input"
                rows={3}
                required={f.required}
                maxLength={f.maxLength ?? 10000}
                value={values[f.name] ?? ""}
                onChange={(e) =>
                  setValues({ ...values, [f.name]: e.target.value })
                }
              />
            ) : (
              <Input
                type={f.type ?? "text"}
                required={f.required}
                min={f.min}
                max={f.max}
                step={
                  f.type === "number"
                    ? 1
                    : f.type === "datetime-local"
                      ? "any"
                      : undefined
                }
                maxLength={f.maxLength}
                value={values[f.name] ?? ""}
                onChange={(e) =>
                  setValues({ ...values, [f.name]: e.target.value })
                }
              />
            )}
          </Field>
        ))}
      </fieldset>
      {fileUpload && totalFileCount < 5 && (
        <WorkspaceUpload
          label={fileUploadLabel}
          onBusy={setUploadBusy}
          onComplete={(id) => setFileIds((ids) => [...new Set([...ids, id])])}
        />
      )}{" "}
      {totalFileCount > 0 && (
        <p role="status">
          Đã đính kèm {totalFileCount}/5 tệp.
        </p>
      )}
      {error && (
        <div role="alert">
          <p className="field-error">{describeApiError(error)}</p>
          {error.status === 409 && (
            <>
              <p>
                Dữ liệu đã thay đổi. Nội dung form vẫn được giữ; tải lại trước
                khi thao tác tiếp.
              </p>
              <Button variant="outline" onClick={onSaved}>
                Tải dữ liệu mới
              </Button>
            </>
          )}
          {error.status === 401 && <a href="/auth/login">Đăng nhập lại</a>}
        </div>
      )}
      <Button
        type="submit"
        disabled={uploadBusy || busy || error?.status === 409}
      >
        {busy ? "Đang gửi…" : label}
      </Button>
    </form>
  );
}
