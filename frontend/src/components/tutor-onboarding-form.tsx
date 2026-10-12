"use client";

import { useEffect, useState, type ChangeEvent } from "react";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import {
  attachTutorAvatar,
  completeFile,
  createVerificationDocument,
  getTutorProfile,
  saveTutorProfile,
  submitTutorProfile,
  uploadFile,
} from "@/lib/data/services";
import type { FileRecord, TutorOnboardingData, TutorProfile } from "@/lib/data/types";
import { Badge, Button, Field } from "./ui";

type UploadState = { file: FileRecord | null; message: string; error: string; busy?: boolean };
type UploadPurpose = "avatar" | "verification_document";

const emptyUpload: UploadState = { file: null, message: "", error: "" };

function statusLabel(profile: TutorProfile | null) {
  if (!profile || profile.status === "draft") return "Draft";
  if (profile.status === "changes_requested" || profile.approval_status === "changes_requested") return "Changes Requested";
  if (profile.status === "approved" || profile.approval_status === "active") return "Approved";
  if (profile.status === "rejected" || profile.approval_status === "rejected") return "Rejected";
  return "Submitted";
}

function statusTone(profile: TutorProfile | null): "neutral" | "success" | "warning" {
  if (profile?.approval_status === "active" || profile?.status === "approved") return "success";
  if (profile?.status === "changes_requested" || profile?.approval_status === "changes_requested") return "warning";
  if (profile?.status === "submitted") return "warning";
  return "neutral";
}

export function TutorOnboardingForm({ data, onReload }: { data: TutorOnboardingData; onReload: () => void }) {
  const [profile, setProfile] = useState<TutorProfile | null>(data.profile);
  const [headline, setHeadline] = useState(data.profile?.headline ?? "");
  const [bio, setBio] = useState(data.profile?.bio ?? "");
  const [experienceYears, setExperienceYears] = useState(String(data.profile?.experience_years ?? 0));
  const [specializationId, setSpecializationId] = useState(String(data.profile?.specialization_id ?? ""));
  const [documentType, setDocumentType] = useState("teaching_certificate");
  const [uploads, setUploads] = useState<Record<UploadPurpose, UploadState>>({ avatar: emptyUpload, verification_document: emptyUpload });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setProfile(data.profile);
    setHeadline(data.profile?.headline ?? "");
    setBio(data.profile?.bio ?? "");
    setExperienceYears(String(data.profile?.experience_years ?? 0));
    setSpecializationId(String(data.profile?.specialization_id ?? ""));
  }, [data]);

  const changesRequested = profile?.status === "changes_requested" || profile?.approval_status === "changes_requested";
  const locked = Boolean(profile?.suspended_at || (profile && !changesRequested && profile.status !== "draft"));
  const uploadBusy = Object.values(uploads).some((upload) => upload.busy);
  const busy = saving || uploadBusy;
  const verified = Boolean(data.user.email_verified_at);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  function selectedSpecialization() {
    const value = Number(specializationId);
    if (!Number.isInteger(value) || value < 1) {
      throw new ApiClientError("Hãy chọn specialization.", {
        status: 422,
        fieldErrors: { specialization_id: ["Hãy chọn specialization."] },
      });
    }
    return value;
  }

  function draftInput() {
    return {
      headline,
      bio,
      experience_years: Number(experienceYears),
      specialization_id: selectedSpecialization(),
    };
  }

  async function handleProfileError(reason: unknown) {
    if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
    setError(describeApiError(reason));
    if (reason instanceof ApiClientError && [403, 409, undefined].includes(reason.status)) {
      try { setProfile(await getTutorProfile()); } catch { /* Preserve the original error. */ }
    }
  }

  async function saveDraft() {
    setError("");
    setMessage("");
    setFieldErrors({});
    setSaving(true);
    try {
      const saved = await saveTutorProfile(draftInput());
      setProfile(saved);
      setMessage("Đã lưu bản nháp và specialization.");
    } catch (reason) {
      await handleProfileError(reason);
    } finally {
      setSaving(false);
    }
  }

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setFieldErrors({});
    setSaving(true);
    try {
      const saved = await saveTutorProfile(draftInput());
      const submitted = await submitTutorProfile();
      setProfile({ ...saved, ...submitted });
      setMessage("Hồ sơ đã được gửi để xét duyệt.");
    } catch (reason) {
      await handleProfileError(reason);
    } finally {
      setSaving(false);
    }
  }

  async function attachCompleted(purpose: UploadPurpose, fileId: number) {
    const attached = purpose === "avatar"
      ? await attachTutorAvatar(fileId)
      : (await createVerificationDocument({ fileId, documentType: documentType.trim() }), await getTutorProfile());
    setProfile(attached);
    setUploads((current) => ({
      ...current,
      [purpose]: {
        file: current[purpose].file,
        message: "Upload, complete và attach thành công.",
        error: "",
      },
    }));
  }

  async function retryUpload(purpose: UploadPurpose) {
    let file = uploads[purpose].file;
    if (!file || locked || busy) return;
    setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], message: "Đang thử lại…", error: "", busy: true } }));
    try {
      if (!file.complete) file = await completeFile(file.id);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file } }));
      await attachCompleted(purpose, file.id);
    } catch (reason) {
      await handleProfileError(reason);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file, error: describeApiError(reason) } }));
    } finally {
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], busy: false } }));
    }
  }

  async function handleUpload(event: ChangeEvent<HTMLInputElement>, purpose: UploadPurpose) {
    const selectedFile = event.target.files?.[0];
    if (!selectedFile || locked || busy || !profile) return;
    if (purpose === "verification_document" && !documentType.trim()) {
      setUploads((current) => ({ ...current, [purpose]: { file: null, message: "", error: "Nhập loại verification document trước khi upload." } }));
      return;
    }
    const maxBytes = purpose === "avatar" ? 2 * 1024 * 1024 : 10 * 1024 * 1024;
    const allowed = purpose === "avatar"
      ? ["image/jpeg", "image/png"]
      : ["application/pdf", "image/jpeg", "image/png", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
    if (selectedFile.size > maxBytes || !allowed.includes(selectedFile.type)) {
      setUploads((current) => ({ ...current, [purpose]: { file: null, message: "", error: purpose === "avatar" ? "Avatar phải là JPG/PNG và tối đa 2 MiB." : "Tài liệu phải là PDF/JPG/PNG/DOC/DOCX và tối đa 10 MiB." } }));
      return;
    }

    setUploads((current) => ({ ...current, [purpose]: { file: null, message: "Đang upload…", error: "", busy: true } }));
    let uploaded: FileRecord | null = null;
    try {
      uploaded = await uploadFile(selectedFile, purpose);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file: uploaded, message: "Đang complete…" } }));
      uploaded = await completeFile(uploaded.id);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file: uploaded, message: "Đang attach…" } }));
      await attachCompleted(purpose, uploaded.id);
    } catch (reason) {
      await handleProfileError(reason);
      setUploads((current) => ({ ...current, [purpose]: {
        file: uploaded,
        message: uploaded?.complete ? "Upload và complete thành công; cần xác nhận lại kết quả attach." : uploaded ? "Upload thành công; complete chưa được xác nhận." : "",
        error: describeApiError(reason),
      } }));
    } finally {
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], busy: false } }));
    }
  }

  function renderUpload(purpose: UploadPurpose, label: string) {
    const state = uploads[purpose];
    const attachedFileId = purpose === "avatar" ? profile?.avatar_file_id : profile?.verification_document_file_id;
    return (
      <div className="card upload-card">
        {purpose === "verification_document" ? (
          <Field label="Loại verification document" htmlFor="verification-document-type" hint="Ví dụ: teaching_certificate.">
            <input
              id="verification-document-type"
              value={documentType}
              maxLength={50}
              disabled={locked || busy}
              onChange={(event) => setDocumentType(event.target.value)}
              required
            />
          </Field>
        ) : null}
        <Field label={label} htmlFor={`${purpose}-file`} hint={purpose === "avatar" ? "JPG/PNG, tối đa 2 MiB." : "PDF/JPG/PNG/DOC/DOCX, tối đa 10 MiB."}>
          <input id={`${purpose}-file`} type="file" accept={purpose === "avatar" ? "image/jpeg,image/png" : ".pdf,.jpg,.jpeg,.png,.doc,.docx"} disabled={locked || busy || !profile || (purpose !== "avatar" && !verified)} onChange={(event) => void handleUpload(event, purpose)} />
        </Field>
        {!profile ? <p className="muted">Lưu bản nháp trước khi tải file lên.</p> : null}
        {fieldError(purpose === "avatar" ? "avatar_file_id" : "verification_document_file_id") ? <p className="inline-error" role="alert">{fieldError(purpose === "avatar" ? "avatar_file_id" : "verification_document_file_id")}</p> : null}
        {attachedFileId ? <p className="muted">Đã attach file #{attachedFileId}.</p> : null}
        {state.message ? <p className="inline-success" role="status">{state.message}</p> : null}
        {state.error ? <p className="inline-error" role="alert">{state.error}</p> : null}
        {state.file ? <p className="muted">File #{state.file.id} · {state.file.original_name ?? "file"} · {state.file.complete ? "complete" : "chưa complete"}</p> : null}
        {state.file && state.error ? <Button type="button" disabled={locked || busy} onClick={() => void retryUpload(purpose)}>{state.file.complete ? "Thử attach lại" : "Thử complete và attach lại"}</Button> : null}
      </div>
    );
  }

  return (
    <div className="content-stack">
      <div className="status-card card">
        <div>
          <p className="eyebrow">Trạng thái hồ sơ</p>
          <h2>{statusLabel(profile)}</h2>
        </div>
        <Badge tone={statusTone(profile)}>{statusLabel(profile)}</Badge>
      </div>
      {profile?.suspended_at ? <p className="inline-error" role="alert">Hồ sơ đang tạm ngưng. {profile.suspension_reason}</p> : null}
      {!verified ? <p className="muted">Xác minh email trước khi tải tài liệu hoặc gửi xét duyệt. <a className="text-button" href="/auth/verify-email">Xác minh email</a></p> : null}
      {profile?.review_reason ? <div className="card"><strong>Lý do xét duyệt</strong><p className="muted">{profile.review_reason}</p></div> : null}
      <form className="card form-stack" onSubmit={submit}>
        <Field label="Headline" htmlFor="tutor-headline" error={fieldError("headline")}>
          <input id="tutor-headline" value={headline} disabled={locked || busy} onChange={(event) => setHeadline(event.target.value)} required maxLength={160} />
        </Field>
        <Field label="Giới thiệu" htmlFor="tutor-bio" error={fieldError("bio")}>
          <textarea id="tutor-bio" rows={6} value={bio} disabled={locked || busy} onChange={(event) => setBio(event.target.value)} />
        </Field>
        <Field label="Số năm kinh nghiệm" htmlFor="tutor-experience" error={fieldError("experience_years")}>
          <input id="tutor-experience" type="number" min={0} max={80} value={experienceYears} disabled={locked || busy} onChange={(event) => setExperienceYears(event.target.value)} required />
        </Field>
        <Field label="Specialization từ Catalog" htmlFor="tutor-specialization" error={fieldError("specialization_id")}>
          <select id="tutor-specialization" value={specializationId} disabled={locked || busy} onChange={(event) => setSpecializationId(event.target.value)} required>
            <option value="">Chọn specialization</option>
            {data.specializations.map((specialization) => <option key={specialization.id} value={specialization.id}>{specialization.name}</option>)}
          </select>
        </Field>
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        <div className="button-row">
          <Button type="button" disabled={locked || busy} onClick={() => void saveDraft()}>Lưu Draft</Button>
          <Button type="submit" disabled={locked || busy || !verified}>{saving ? "Đang xử lý…" : "Submit hồ sơ"}</Button>
        </div>
        {locked ? <p className="muted">Hồ sơ đang bị khóa theo trạng thái xét duyệt hoặc tạm ngưng hiện tại.</p> : null}
      </form>
      {renderUpload("avatar", "Avatar")}
      {renderUpload("verification_document", "Verification document")}
      <button className="text-button" type="button" disabled={busy} onClick={onReload}>Tải lại dữ liệu từ API</button>
    </div>
  );
}
