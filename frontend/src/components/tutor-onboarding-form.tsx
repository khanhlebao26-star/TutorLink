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

type UploadState = { file: FileRecord | null; message: string; error: string };
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
  const locked = Boolean(profile && !changesRequested && profile.status !== "draft");

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
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      setError(describeApiError(reason));
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
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      setError(describeApiError(reason));
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

  async function retryAttach(purpose: UploadPurpose) {
    const completed = uploads[purpose].file;
    if (!completed) return;
    setUploads((current) => ({
      ...current,
      [purpose]: { ...current[purpose], message: "Đang attach lại…", error: "" },
    }));
    try {
      await attachCompleted(purpose, completed.id);
    } catch (reason) {
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], error: describeApiError(reason) } }));
    }
  }

  async function handleUpload(event: ChangeEvent<HTMLInputElement>, purpose: UploadPurpose) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (purpose !== "avatar" && purpose !== "verification_document") return;
    if (purpose === "verification_document" && !documentType.trim()) {
      setUploads((current) => ({ ...current, [purpose]: { file: null, message: "", error: "Nhập loại verification document trước khi upload." } }));
      return;
    }
    const maxBytes = purpose === "avatar" ? 2 * 1024 * 1024 : 10 * 1024 * 1024;
    const allowed = purpose === "avatar"
      ? ["image/jpeg", "image/png"]
      : ["application/pdf", "image/jpeg", "image/png", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
    if (file.size > maxBytes || !allowed.includes(file.type)) {
      setUploads((current) => ({ ...current, [purpose]: { file: null, message: "", error: purpose === "avatar" ? "Avatar phải là JPG/PNG và tối đa 2 MiB." : "Tài liệu phải là PDF/JPG/PNG/DOC/DOCX và tối đa 10 MiB." } }));
      return;
    }

    setUploads((current) => ({ ...current, [purpose]: { file: null, message: "Đang upload…", error: "" } }));
    let completed: FileRecord | null = null;
    try {
      const uploaded = await uploadFile(file, purpose);
      completed = await completeFile(uploaded.id);
      await attachCompleted(purpose, completed.id);
      setUploads((current) => ({
        ...current,
        [purpose]: {
          file: completed,
          message: "Upload, complete và attach thành công.",
          error: "",
        },
      }));
    } catch (reason) {
      setUploads((current) => ({ ...current, [purpose]: { file: completed, message: completed ? "Upload và complete thành công nhưng attach chưa hoàn tất." : "", error: describeApiError(reason) } }));
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
              disabled={locked || saving}
              onChange={(event) => setDocumentType(event.target.value)}
              required
            />
          </Field>
        ) : null}
        <Field label={label} htmlFor={`${purpose}-file`} hint={purpose === "avatar" ? "JPG/PNG, tối đa 2 MiB." : "PDF/JPG/PNG/DOC/DOCX, tối đa 10 MiB."}>
          <input id={`${purpose}-file`} type="file" disabled={locked || saving} onChange={(event) => void handleUpload(event, purpose)} />
        </Field>
        {attachedFileId ? <p className="muted">Đã attach file #{attachedFileId}.</p> : null}
        {state.message ? <p className="inline-success" role="status">{state.message}</p> : null}
        {state.error ? <p className="inline-error" role="alert">{state.error}</p> : null}
        {state.file ? <p className="muted">File #{state.file.id} · {state.file.original_name ?? "file"} · complete</p> : null}
        {state.file && state.error ? <Button type="button" disabled={locked || saving} onClick={() => void retryAttach(purpose)}>Thử attach lại</Button> : null}
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
      {profile?.review_reason ? <div className="card"><strong>Lý do xét duyệt</strong><p className="muted">{profile.review_reason}</p></div> : null}
      <form className="card form-stack" onSubmit={submit}>
        <Field label="Headline" htmlFor="tutor-headline" error={fieldError("headline")}>
          <input id="tutor-headline" value={headline} disabled={locked || saving} onChange={(event) => setHeadline(event.target.value)} required maxLength={160} />
        </Field>
        <Field label="Giới thiệu" htmlFor="tutor-bio" error={fieldError("bio")}>
          <textarea id="tutor-bio" rows={6} value={bio} disabled={locked || saving} onChange={(event) => setBio(event.target.value)} />
        </Field>
        <Field label="Số năm kinh nghiệm" htmlFor="tutor-experience" error={fieldError("experience_years")}>
          <input id="tutor-experience" type="number" min={0} max={80} value={experienceYears} disabled={locked || saving} onChange={(event) => setExperienceYears(event.target.value)} required />
        </Field>
        <Field label="Specialization từ Catalog" htmlFor="tutor-specialization" error={fieldError("specialization_id")}>
          <select id="tutor-specialization" value={specializationId} disabled={locked || saving} onChange={(event) => setSpecializationId(event.target.value)} required>
            <option value="">Chọn specialization</option>
            {data.specializations.map((specialization) => <option key={specialization.id} value={specialization.id}>{specialization.name}</option>)}
          </select>
        </Field>
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        <div className="button-row">
          <Button type="button" disabled={locked || saving} onClick={() => void saveDraft()}>Lưu Draft</Button>
          <Button type="submit" disabled={locked || saving}>{saving ? "Đang xử lý…" : "Submit hồ sơ"}</Button>
        </div>
        {locked ? <p className="muted">Hồ sơ đã Submitted nên form và upload đang bị khóa cho tới khi backend trả trạng thái Changes Requested.</p> : null}
      </form>
      {renderUpload("avatar", "Avatar")}
      {renderUpload("verification_document", "Verification document")}
      <button className="text-button" type="button" onClick={onReload}>Tải lại dữ liệu từ API</button>
    </div>
  );
}
