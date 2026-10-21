"use client";

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
import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from "react";
import { Badge, Button, Card, Field, Input, Select } from "./ui";

type UploadPurpose = "avatar" | "verification_document";
type UploadState = { file: FileRecord | null; message: string; error: string; busy: boolean };
type FieldErrors = Record<string, string[]>;

const steps = ["Thông tin cơ bản", "Chuyên môn", "Kinh nghiệm", "Tài liệu xác minh", "Xem lại"];
const emptyUpload: UploadState = { file: null, message: "", error: "", busy: false };

function statusLabel(profile: TutorProfile | null) {
  if (!profile) return "Bản nháp";

  const labels: Record<TutorProfile["status"], string> = {
    draft: "Bản nháp",
    submitted: "Đã gửi xét duyệt",
    changes_requested: "Yêu cầu chỉnh sửa",
    approved: "Đã phê duyệt",
    rejected: "Đã từ chối",
    suspended: "Đình chỉ hồ sơ",
  };

  return labels[profile.status];
}

function statusTone(
  profile: TutorProfile | null,
): "neutral" | "success" | "warning" | "brand" {
  if (!profile) return "brand";
  if (profile.status === "approved") return "success";

  if (
    profile.status === "submitted"
    || profile.status === "changes_requested"
    || profile.status === "suspended"
  ) {
    return "warning";
  }

  return "neutral";
}

function profileIsLocked(profile: TutorProfile | null) {
  return profile !== null
    && profile.status !== "draft"
    && profile.status !== "changes_requested";
}

function fieldStep(name: string) {
  if (name === "headline" || name === "bio") return 1;
  if (name === "specialization_id") return 2;
  if (name === "experience_years") return 3;
  if (name === "avatar_file_id") return 1;
  if (name === "verification_document_file_id" || name === "document_type") return 4;
  return null;
}

export function TutorOnboardingForm({ data, onReload }: { data: TutorOnboardingData; onReload: () => void }) {
  const [profile, setProfile] = useState<TutorProfile | null>(data.profile);
  const [headline, setHeadline] = useState(data.profile?.headline ?? "");
  const [bio, setBio] = useState(data.profile?.bio ?? "");
  const [experienceYears, setExperienceYears] = useState(String(data.profile?.experience_years ?? ""));
  const [specializationId, setSpecializationId] = useState(String(data.profile?.specialization_id ?? ""));
  const [documentType, setDocumentType] = useState("teaching_certificate");
  const [step, setStep] = useState(1);
  const [furthestStep, setFurthestStep] = useState(1);
  const [uploads, setUploads] = useState<Record<UploadPurpose, UploadState>>({
    avatar: { ...emptyUpload },
    verification_document: { ...emptyUpload },
  });
  const [queuedAvatar, setQueuedAvatar] = useState<File | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);
  const savingRef = useRef(false);
  const uploadRef = useRef(false);
  const submitRef = useRef(false);

  useEffect(() => {
    setProfile(data.profile);
    setHeadline(data.profile?.headline ?? "");
    setBio(data.profile?.bio ?? "");
    setExperienceYears(String(data.profile?.experience_years ?? ""));
    setSpecializationId(String(data.profile?.specialization_id ?? ""));
    setUploads({ avatar: { ...emptyUpload }, verification_document: { ...emptyUpload } });
    setQueuedAvatar(null);
  }, [data]);

  const locked = profileIsLocked(profile);
  const changesRequested =
    profile?.status === "changes_requested";
  const busy = saving || Object.values(uploads).some((upload) => upload.busy);
  const verified = Boolean(data.user.email_verified_at);
  const attachedAvatar = Boolean(profile?.avatar_file_id);
  const attachedDocument = Boolean(profile?.verification_document_file_id);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  function clearFieldError(name: string) {
    setFieldErrors((current) => ({ ...current, [name]: [] }));
  }

  function applyProfile(next: TutorProfile) {
    setProfile(next);
    setHeadline(next.headline ?? "");
    setBio(next.bio ?? "");
    setExperienceYears(String(next.experience_years ?? ""));
    setSpecializationId(String(next.specialization_id ?? ""));
  }

  async function handleProfileError(reason: unknown) {
    if (reason instanceof ApiClientError && reason.fieldErrors) {
      const errors = reason.fieldErrors;
      setFieldErrors(errors);
      const firstField = Object.keys(errors).find((name) => errors[name]?.length);
      const destination = firstField ? fieldStep(firstField) : null;
      if (destination) setStep(destination);
    }
    setError(describeApiError(reason));

    if (reason instanceof ApiClientError && (reason.status === 403 || reason.status === 409)) {
      try {
        applyProfile(await getTutorProfile());
      } catch {
        // Keep the original error visible when a refresh also fails.
      }
    }
  }

  function draftInput() {
    return {
      headline: headline.trim(),
      bio: bio.trim(),
      experience_years: Number(experienceYears),
      specialization_id: Number(specializationId),
    };
  }

  function validateStepFields(targetStep: number) {
    const errors: FieldErrors = {};
    if (targetStep === 1) {
      if (!headline.trim()) errors.headline = ["Vui lòng nhập tiêu đề hồ sơ."];
      else if (headline.trim().length > 160) errors.headline = ["Tiêu đề không được vượt quá 160 ký tự."];
      if (!bio.trim()) errors.bio = ["Vui lòng giới thiệu ngắn về bạn."];
    }
    if (targetStep === 2) {
      const selected = data.specializations.find((item) => String(item.id) === specializationId && item.status === "active");
      if (!selected) errors.specialization_id = ["Vui lòng chọn một chuyên môn đang hoạt động."];
    }
    if (targetStep === 3) {
      const years = Number(experienceYears);
      if (experienceYears === "" || !Number.isInteger(years) || years < 0 || years > 80) {
        errors.experience_years = ["Nhập số năm kinh nghiệm từ 0 đến 80."];
      }
    }
    const checkedFields = targetStep === 1 ? ["headline", "bio"] : targetStep === 2 ? ["specialization_id"] : ["experience_years"];
    setFieldErrors((current) => ({ ...current, ...Object.fromEntries(checkedFields.map((name) => [name, errors[name] ?? []])) }));
    const firstField = Object.keys(errors)[0];
    if (firstField) {
      window.requestAnimationFrame(() => document.getElementById(`tutor-${firstField}`)?.focus());
      return false;
    }
    return true;
  }

  async function saveDraft() {
    if (savingRef.current) return null;
    savingRef.current = true;
    setError("");
    setMessage("");
    setFieldErrors({});
    setSaving(true);
    try {
      const saved = await saveTutorProfile(draftInput());
      applyProfile(saved);
      setMessage("Đã lưu bản nháp.");
      return saved;
    } catch (reason) {
      await handleProfileError(reason);
      return null;
    } finally {
      savingRef.current = false;
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
      [purpose]: { ...current[purpose], message: purpose === "avatar" ? "Đã tải ảnh đại diện lên." : "Đã tải tài liệu xác minh lên.", error: "", busy: false },
    }));
  }

  async function runUpload(file: File, purpose: UploadPurpose, existingProfile: TutorProfile | null = profile) {
    if (locked || busy || uploadRef.current) return false;
    uploadRef.current = true;
    let readyProfile = existingProfile;
    let uploaded: FileRecord | null = null;
    try {
      if (!readyProfile) readyProfile = await saveDraft();
      if (!readyProfile) return false;
      setError("");
      setUploads((current) => ({
        ...current,
        [purpose]: { ...current[purpose], message: "Đang tải lên…", error: "", busy: true },
      }));
      uploaded = await uploadFile(file, purpose);
      if (purpose === "avatar") setQueuedAvatar(null);
      setUploads((current) => ({
        ...current,
        [purpose]: { ...current[purpose], file: uploaded, message: "Đang kiểm tra tệp…" },
      }));
      uploaded = await completeFile(uploaded.id);
      setUploads((current) => ({
        ...current,
        [purpose]: { ...current[purpose], file: uploaded, message: "Đang cập nhật hồ sơ…" },
      }));
      await attachCompleted(purpose, uploaded.id);
      return true;
    } catch (reason) {
      await handleProfileError(reason);
      setUploads((current) => ({
        ...current,
        [purpose]: {
          ...current[purpose],
          file: uploaded,
          message: "",
          error: describeApiError(reason),
          busy: false,
        },
      }));
      return false;
    } finally {
      uploadRef.current = false;
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], busy: false } }));
    }
  }

  async function handleUpload(event: ChangeEvent<HTMLInputElement>, purpose: UploadPurpose) {
    const file = event.currentTarget.files?.[0];
    event.currentTarget.value = "";
    if (!file || locked || busy || uploadRef.current) return;

    const maxBytes = purpose === "avatar" ? 2 * 1024 * 1024 : 10 * 1024 * 1024;
    const allowed = purpose === "avatar"
      ? ["image/jpeg", "image/png"]
      : ["application/pdf", "image/jpeg", "image/png", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
    if (file.size > maxBytes || !allowed.includes(file.type)) {
      setUploads((current) => ({
        ...current,
        [purpose]: {
          ...emptyUpload,
          error: purpose === "avatar"
            ? "Ảnh đại diện phải là JPG hoặc PNG và tối đa 2 MiB."
            : "Tài liệu phải là PDF, JPG, PNG, DOC hoặc DOCX và tối đa 10 MiB.",
        },
      }));
      return;
    }

    if (purpose === "verification_document" && !documentType.trim()) {
      setUploads((current) => ({ ...current, [purpose]: { ...emptyUpload, error: "Nhập loại tài liệu xác minh trước khi tải lên." } }));
      setFieldErrors((current) => ({ ...current, document_type: ["Nhập loại tài liệu xác minh."] }));
      return;
    }

    if (purpose === "avatar" && !profile) {
      setQueuedAvatar(file);
      setUploads((current) => ({
        ...current,
        avatar: { ...emptyUpload, message: "Ảnh đã chọn. Lưu hồ sơ ở Bước 3 để tải ảnh lên." },
      }));
      return;
    }

    await runUpload(file, purpose);
  }

  async function retryUpload(purpose: UploadPurpose) {
    if (locked || busy || uploadRef.current) return;
    const state = uploads[purpose];
    if (!state.file) {
      if (purpose === "avatar" && queuedAvatar) await runUpload(queuedAvatar, purpose);
      return;
    }

    uploadRef.current = true;
    setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], message: "Đang thử lại…", error: "", busy: true } }));
    let file = state.file;
    try {
      if (!file.complete) file = await completeFile(file.id);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file, message: "Đang cập nhật hồ sơ…" } }));
      await attachCompleted(purpose, file.id);
    } catch (reason) {
      await handleProfileError(reason);
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], file, message: "", error: describeApiError(reason), busy: false } }));
    } finally {
      uploadRef.current = false;
      setUploads((current) => ({ ...current, [purpose]: { ...current[purpose], busy: false } }));
    }
  }

  async function nextStep() {
    if (busy) return;
    if (locked) {
      setStep((current) => Math.min(current + 1, steps.length));
      setFurthestStep((current) => Math.max(current, Math.min(step + 1, steps.length)));
      return;
    }
    if (!validateStepFields(step)) return;
    setError("");
    setFieldErrors({});
    if (step === 3) {
      const saved = await saveDraft();
      if (!saved) return;
      setStep(4);
      setFurthestStep((current) => Math.max(current, 4));
      if (queuedAvatar) await runUpload(queuedAvatar, "avatar", saved);
      return;
    }
    setStep((current) => Math.min(current + 1, steps.length));
    setFurthestStep((current) => Math.max(current, Math.min(step + 1, steps.length)));
  }

  function allProfileFieldsValid() {
    const errors: FieldErrors = {};
    if (!headline.trim()) errors.headline = ["Vui lòng nhập tiêu đề hồ sơ."];
    if (!bio.trim()) errors.bio = ["Vui lòng giới thiệu ngắn về bạn."];
    if (!data.specializations.some((item) => item.status === "active" && String(item.id) === specializationId)) {
      errors.specialization_id = ["Vui lòng chọn một chuyên môn đang hoạt động."];
    }
    const years = Number(experienceYears);
    if (experienceYears === "" || !Number.isInteger(years) || years < 0 || years > 80) errors.experience_years = ["Nhập số năm kinh nghiệm từ 0 đến 80."];
    setFieldErrors(errors);
    const firstField = Object.keys(errors)[0];
    if (firstField) {
      const destination = fieldStep(firstField);
      if (destination) setStep(destination);
      window.requestAnimationFrame(() => document.getElementById(`tutor-${firstField}`)?.focus());
      return false;
    }
    if (!attachedAvatar) {
      setStep(1);
      setUploads((current) => ({ ...current, avatar: { ...current.avatar, error: "Cần tải ảnh đại diện trước khi gửi hồ sơ." } }));
      return false;
    }

    return true;
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    if (locked || busy || !verified || submitRef.current || !allProfileFieldsValid()) return;
    submitRef.current = true;
    setSaving(true);
    try {
      await saveTutorProfile(draftInput());
      const submitted = await submitTutorProfile();
      applyProfile(submitted);
      setMessage("Hồ sơ đã được gửi để xét duyệt.");
    } catch (reason) {
      await handleProfileError(reason);
    } finally {
      submitRef.current = false;
      setSaving(false);
    }
  }

  function goToStep(targetStep: number) {
    if (!busy && (targetStep <= furthestStep || locked)) setStep(targetStep);
  }

  function renderUpload(purpose: UploadPurpose, label: string) {
    const state = uploads[purpose];
    const file = purpose === "avatar" ? queuedAvatar : null;
    const attachedFileId = purpose === "avatar" ? profile?.avatar_file_id : profile?.verification_document_file_id;
    const uploadFieldError = fieldError(purpose === "avatar" ? "avatar_file_id" : "verification_document_file_id");
    const inputId = `tutor-${purpose}-file`;
    const errorId = `tutor-${purpose}-error`;
    const disabled = locked || busy || (purpose === "verification_document" && (!verified || !profile));
    return (
      <div className="onboarding-upload-card">
        <div className="onboarding-upload-copy">
          <span className={`onboarding-upload-icon ${purpose === "avatar" ? "onboarding-avatar-mark" : ""}`} aria-hidden="true">
            {purpose === "avatar" ? (data.user.full_name.trim().charAt(0).toUpperCase() || "T") : "↥"}
          </span>
          <div>
            <strong className="onboarding-field-label">
              {label}
              {purpose === "avatar" ? <span className="onboarding-required"> *</span> : <span className="muted"> · Không bắt buộc</span>}
            </strong>
            <p>{purpose === "avatar" ? "Ảnh hồ sơ rõ mặt, JPG hoặc PNG, tối đa 2 MiB." : "Tài liệu chứng minh thông tin chuyên môn, PDF/JPG/PNG/DOC/DOCX, tối đa 10 MiB. Có thể bổ sung sau."}</p>
          </div>
        </div>
        {purpose === "verification_document" ? (
          <div className="onboarding-field onboarding-document-type">
            <Field label="Loại tài liệu" htmlFor="tutor-document_type" error={fieldError("document_type")}>
              <Input
                value={documentType}
                maxLength={50}
                disabled={locked || busy}
                aria-invalid={Boolean(fieldError("document_type"))}
                onChange={(event) => {
                  setDocumentType(event.target.value);
                  setFieldErrors((current) => ({ ...current, document_type: [] }));
                }}
              />
            </Field>
          </div>
        ) : null}
        <input
          className="onboarding-file-input"
          id={inputId}
          type="file"
          accept={purpose === "avatar" ? "image/jpeg,image/png" : ".pdf,.jpg,.jpeg,.png,.doc,.docx"}
          disabled={disabled}
          aria-describedby={[
            state.error ? errorId : undefined,
            uploadFieldError ? `${inputId}-validation` : undefined,
          ].filter(Boolean).join(" ") || undefined}
          onChange={(event) => void handleUpload(event, purpose)}
        />
        <label className={`onboarding-file-picker ${disabled ? "is-disabled" : ""}`} htmlFor={inputId}>
          <span>{state.busy ? "Đang xử lý tệp…" : purpose === "avatar" ? "Chọn ảnh đại diện" : "Chọn tài liệu xác minh"}</span>
          <small>{purpose === "avatar" ? "JPG, PNG · tối đa 2 MiB" : "PDF, JPG, PNG, DOC, DOCX · tối đa 10 MiB"}</small>
        </label>
        {!verified && purpose === "verification_document" ? <p className="onboarding-inline-note">Xác minh email trước khi tải tài liệu. <a href="/auth/verify-email">Xác minh email</a></p> : null}
        {!profile && purpose === "verification_document" ? <p className="onboarding-inline-note">Lưu các bước thông tin trước khi tải tài liệu.</p> : null}
        {attachedFileId ? <p className="onboarding-upload-status is-complete" role="status">✓ {purpose === "avatar" ? "Đã tải ảnh đại diện lên." : "Đã tải tài liệu xác minh lên."}</p> : null}
        {file ? <p className="onboarding-upload-status">Đã chọn: {file.name}</p> : null}
        {state.file ? <p className="onboarding-upload-status">{state.file.original_name ?? (state.file.complete ? "Tệp đã tải lên" : "Đang hoàn tất tải lên")}</p> : null}
        {state.message ? <p className="onboarding-inline-success" role="status">{state.message}</p> : null}
        {state.error ? <p className="onboarding-field-error" id={errorId} role="alert">{state.error}</p> : null}
        {uploadFieldError ? <p className="onboarding-field-error" id={`${inputId}-validation`} role="alert">{uploadFieldError}</p> : null}
        {state.error && (state.file || file) ? (
          <Button variant="ghost" className="onboarding-link-button" type="button" disabled={locked || busy} onClick={() => void retryUpload(purpose)}>
            Thử lại
          </Button>
        ) : null}
      </div>
    );
  }

  const selectedSpecialization = data.specializations.find((item) => String(item.id) === specializationId)?.name ?? "Chưa chọn";

  return (
    <div className="onboarding-layout">
      {profile?.status === "suspended"? <p className="onboarding-alert" role="alert">Hồ sơ đang tạm ngưng. {profile.suspension_reason}</p> : null}
      {changesRequested && profile?.review_reason ? (
        <div className="onboarding-review-reason" role="status">
          <strong>Ý kiến từ người xét duyệt</strong>
          <p>{profile.review_reason}</p>
          <span>Bạn có thể chỉnh sửa thông tin và gửi lại hồ sơ.</span>
        </div>
      ) : null}
      {!verified ? (
        <p className="onboarding-verification-note">Xác minh email trước khi gửi hồ sơ hoặc tải tài liệu. <a href="/auth/verify-email">Xác minh email</a></p>
      ) : null}

      <ol className="onboarding-stepper" aria-label="Các bước hoàn thiện hồ sơ">
        {steps.map((label, index) => {
          const number = index + 1;
          return (
            <li className={`onboarding-step ${number === step ? "is-current" : number < step ? "is-complete" : ""}`} key={label}>
              <Button variant="ghost" type="button" aria-current={number === step ? "step" : undefined} disabled={busy || (number > furthestStep && !locked)} onClick={() => goToStep(number)}>
                <span className="onboarding-step-number">{number < step ? "✓" : number}</span>
                <span className="onboarding-step-label">{label}</span>
              </Button>
            </li>
          );
        })}
      </ol>

      <form className="onboarding-form" onSubmit={submit} noValidate>
        <div className={`onboarding-editor-grid onboarding-editor-grid-${step}`}>
        <Card className={`onboarding-step-main onboarding-step-main-${step}`}>
        <div className="onboarding-panel-heading">
          <div>
            <h2>{step === 1 ? "Thông tin cơ bản" : step === 2 ? "Chuyên môn giảng dạy" : step === 3 ? "Kinh nghiệm giảng dạy" : step === 4 ? "Tài liệu xác minh" : "Xem lại hồ sơ"}</h2>
            <p>{step === 1 ? "Giúp học viên hiểu rõ hơn về bạn và cách bạn giảng dạy." : step === 2 ? "Chọn chuyên môn phù hợp nhất với dịch vụ giảng dạy của bạn." : step === 3 ? "Chia sẻ số năm kinh nghiệm giảng dạy." : step === 4 ? "Tải lên tài liệu chứng minh thông tin chuyên môn." : "Kiểm tra thông tin trước khi gửi xét duyệt."}</p>
          </div>
          <div className="onboarding-heading-status">
            <Badge tone={statusTone(profile)} className={`onboarding-status-pill ${changesRequested ? "is-warning" : profile?.status === "approved" ? "is-success" : ""}`}>
              {statusLabel(profile)}
            </Badge>
            {locked ? <span className="onboarding-lock-note">Chỉ xem</span> : null}
          </div>
        </div>

        {step === 1 ? (
          <div className="onboarding-fields">
            {renderUpload("avatar", "Ảnh đại diện")}
            <div className="onboarding-field">
              <Field label="Tiêu đề hồ sơ *" htmlFor="tutor-headline" error={fieldError("headline")} hint="Tối đa 160 ký tự.">
                <Input value={headline} disabled={locked || busy} maxLength={160} required aria-invalid={Boolean(fieldError("headline"))} onChange={(event) => { setHeadline(event.target.value); clearFieldError("headline"); }} />
              </Field>
            </div>
            <div className="onboarding-field">
              <Field label="Giới thiệu bản thân *" htmlFor="tutor-bio" error={fieldError("bio")} hint="Viết vài câu về phong cách giảng dạy và điều bạn có thể giúp học viên đạt được.">
                <textarea id="tutor-bio" rows={3} value={bio} disabled={locked || busy} required aria-invalid={Boolean(fieldError("bio"))} onChange={(event) => { setBio(event.target.value); clearFieldError("bio"); }} />
              </Field>
            </div>
          </div>
        ) : null}

        {step === 2 ? (
          <div className="onboarding-fields">
            <div className="onboarding-field">
              <Field label="Chuyên môn *" htmlFor="tutor-specialization_id" error={fieldError("specialization_id")} hint="Chọn chuyên môn phù hợp với dịch vụ giảng dạy mà bạn có thể cung cấp.">
                <Select value={specializationId} disabled={locked || busy} required aria-invalid={Boolean(fieldError("specialization_id"))} onChange={(event) => { setSpecializationId(event.target.value); clearFieldError("specialization_id"); }}>
                  <option value="">Chọn chuyên môn</option>
                  {data.specializations.filter((item) => item.status === "active").map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </Select>
              </Field>
            </div>
          </div>
        ) : null}

        {step === 3 ? (
          <div className="onboarding-fields">
            <div className="onboarding-field onboarding-years-field">
              <Field label="Số năm kinh nghiệm *" htmlFor="tutor-experience_years" error={fieldError("experience_years")} hint="Nhập số năm hoàn chỉnh, từ 0 đến 80.">
                <Input type="number" min={0} max={80} step={1} value={experienceYears} disabled={locked || busy} required aria-invalid={Boolean(fieldError("experience_years"))} onChange={(event) => { setExperienceYears(event.target.value); clearFieldError("experience_years"); }} />
              </Field>
            </div>
            <div className="onboarding-info-card">
              <span className="onboarding-info-icon" aria-hidden="true">✦</span>
              <p>Số năm kinh nghiệm giúp học viên hiểu rõ hơn về nền tảng giảng dạy của bạn.</p>
            </div>
          </div>
        ) : null}

        {step === 4 ? (
          <div className="onboarding-fields">
            {renderUpload("verification_document", "Tài liệu xác minh")}
            <div className="onboarding-document-summary">
              <strong>Yêu cầu tải tệp</strong>
              <p>Tải lên bằng cấp hoặc chứng chỉ rõ nét để người xét duyệt xác minh chuyên môn của bạn.</p>
            </div>
            {uploads.avatar.error || queuedAvatar || !attachedAvatar ? (
              <div className="onboarding-avatar-summary">
                <span>{attachedAvatar ? "✓ Ảnh đại diện đã gắn" : "Ảnh đại diện còn thiếu"}</span>
                {!attachedAvatar ? <Button variant="ghost" className="onboarding-link-button" type="button" onClick={() => setStep(1)}>Quay lại bước ảnh đại diện</Button> : null}
                {uploads.avatar.error ? <span className="onboarding-field-error" role="alert">{uploads.avatar.error}</span> : null}
                {!attachedAvatar && (uploads.avatar.file || queuedAvatar) && uploads.avatar.error ? <Button variant="ghost" className="onboarding-link-button" type="button" disabled={busy || locked} onClick={() => void retryUpload("avatar")}>Thử tải ảnh lại</Button> : null}
              </div>
            ) : null}
          </div>
        ) : null}

        {step === 5 ? (
          <div className="onboarding-review-main">
            <div className="onboarding-review-identity">
              <span className="onboarding-review-avatar" aria-hidden="true">{data.user.full_name.trim().charAt(0).toUpperCase() || "T"}</span>
              <div>
                <strong>{data.user.full_name}</strong>
                <p>{headline.trim() || "Chưa nhập tiêu đề hồ sơ"}</p>
                <small>{experienceYears === "" ? "Chưa nhập kinh nghiệm" : `${experienceYears} năm kinh nghiệm`} · {selectedSpecialization}</small>
              </div>
            </div>
            <dl className="onboarding-review-details">
              <div><dt>Giới thiệu</dt><dd className="onboarding-review-bio">{bio.trim() || "Chưa nhập"}</dd></div>
              <div><dt>Ảnh đại diện</dt><dd>{attachedAvatar ? "Đã tải lên" : "Chưa tải lên"}</dd></div>
              <div><dt>Tài liệu xác minh</dt><dd>{attachedDocument ? "Đã tải lên" : "Chưa tải lên"}</dd></div>
            </dl>
            <div className="onboarding-review-edit-links">
              <Button variant="ghost" type="button" className="onboarding-link-button" disabled={busy} onClick={() => goToStep(1)}>{locked ? "Xem thông tin cơ bản" : "Chỉnh sửa thông tin"}</Button>
              <Button variant="ghost" type="button" className="onboarding-link-button" disabled={busy} onClick={() => goToStep(2)}>{locked ? "Xem chuyên môn" : "Chỉnh sửa chuyên môn"}</Button>
              <Button variant="ghost" type="button" className="onboarding-link-button" disabled={busy} onClick={() => goToStep(4)}>{locked ? "Xem tài liệu" : "Chỉnh sửa tài liệu"}</Button>
            </div>
          </div>
        ) : null}
        </Card>

        {step === 5 ? (
          <aside className="onboarding-editor-aside" aria-label="Trạng thái hồ sơ">
            <Card className="onboarding-review-status">
              <h3>Trạng thái hồ sơ</h3>
              <ol className="onboarding-status-list" aria-label="Các trạng thái xét duyệt">
                {["Bản nháp", "Đã gửi xét duyệt", "Yêu cầu chỉnh sửa", "Đã phê duyệt", ...(statusLabel(profile) === "Đã từ chối" ? ["Đã từ chối"] : [])].map((label) => (
                  <li key={label} aria-current={statusLabel(profile) === label ? "step" : undefined}>
                    {label}
                  </li>
                ))}
              </ol>
              {locked ? <p>Hồ sơ đang ở trạng thái xét duyệt và không thể chỉnh sửa.</p> : <p>Kiểm tra thông tin rồi gửi hồ sơ để bắt đầu xét duyệt.</p>}
            </Card>
          </aside>
        ) : null}
        </div>

        {error ? <p className="onboarding-alert onboarding-form-notice" role="alert">{error}</p> : null}
        {message ? <p className="onboarding-inline-success onboarding-form-notice" role="status">{message}</p> : null}
        {locked && !changesRequested ? <p className="onboarding-lock-note onboarding-form-notice">Hồ sơ đã gửi hoặc đã được xét duyệt nên hiện chỉ có thể xem lại.</p> : null}
        {step === 5 && !verified ? <p className="onboarding-verification-note onboarding-form-notice">Xác minh email trước khi gửi hồ sơ. <a href="/auth/verify-email">Xác minh email</a></p> : null}

        <div className="onboarding-actions">
          {step > 1 ? <Button variant="secondary" className="onboarding-button onboarding-button-secondary" type="button" disabled={busy} onClick={() => setStep((current) => Math.max(current - 1, 1))}>← Quay lại</Button> : <Button variant="ghost" className="onboarding-button onboarding-button-secondary onboarding-refresh" type="button" disabled={busy} onClick={onReload}>Tải lại dữ liệu</Button>}
          {step < steps.length ? (
            <Button variant="primary" className="onboarding-button onboarding-button-primary" type="button" disabled={busy} onClick={(event) => { event.preventDefault(); void nextStep(); }}>{saving ? "Đang lưu…" : "Tiếp theo"}<span aria-hidden="true">→</span></Button>
          ) : (
            <Button variant="primary" className="onboarding-button onboarding-button-primary" type="submit" disabled={busy || locked || !verified}>{saving ? "Đang gửi…" : changesRequested ? "Gửi lại xét duyệt" : "Gửi xét duyệt"}<span aria-hidden="true">→</span></Button>
          )}
        </div>
      </form>
    </div>
  );
}
