"use client";

import { FieldErrors, UploadPurpose, UploadState, editableProfile, emptyUpload, fieldStep, profileIsLocked, steps, verificationDocumentsRequired } from "@/components/tutors/onboarding/onboarding-config";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import {
  MAX_UPLOAD_BYTES,
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

export function useTutorOnboarding({ data }: { data: TutorOnboardingData }) {
  const [profile, setProfile] = useState<TutorProfile | null>(data.profile);
  const [headline, setHeadline] = useState(editableProfile(data.profile)?.headline ?? "");
  const [bio, setBio] = useState(editableProfile(data.profile)?.bio ?? "");
  const [experienceYears, setExperienceYears] = useState(String(editableProfile(data.profile)?.experience_years ?? ""));
  const [specializationId, setSpecializationId] = useState(String(editableProfile(data.profile)?.specialization_id ?? ""));
  const [documentType, setDocumentType] = useState("identity_card");
  const [claims, setClaims] = useState(data.profile?.revision?.payload.qualification_claims ?? data.profile?.qualification_claims ?? []);
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
    setHeadline(editableProfile(data.profile)?.headline ?? "");
    setBio(editableProfile(data.profile)?.bio ?? "");
    setExperienceYears(String(editableProfile(data.profile)?.experience_years ?? ""));
    setSpecializationId(String(editableProfile(data.profile)?.specialization_id ?? ""));
    setClaims(editableProfile(data.profile)?.qualification_claims ?? []);
    setUploads({ avatar: { ...emptyUpload }, verification_document: { ...emptyUpload } });
    setQueuedAvatar(null);
  }, [data]);

  const locked = profileIsLocked(profile);
  const changesRequested =
    profile?.status === "changes_requested" || profile?.revision?.status === "changes_requested";
  const busy = saving || Object.values(uploads).some((upload) => upload.busy);
  const verified = Boolean(data.user.email_verified_at);
  const avatarFileId = editableProfile(profile)?.avatar_file_id;
  const attachedAvatar = Boolean(avatarFileId);
  const attachedDocument = Boolean(profile?.verification_document_file_id);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  function clearFieldError(name: string) {
    setFieldErrors((current) => ({ ...current, [name]: [] }));
  }

  function applyProfile(next: TutorProfile) {
    setProfile(next);
    next = editableProfile(next)!;
    setHeadline(next.headline ?? "");
    setBio(next.bio ?? "");
    setExperienceYears(String(next.experience_years ?? ""));
    setSpecializationId(String(next.specialization_id ?? ""));
    setClaims(next.qualification_claims ?? []);
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
      expected_version: profile?.status === "approved" ? (["draft", "changes_requested"].includes(profile.revision?.status ?? "") ? profile.revision?.version : 0) : undefined,
      qualification_claims: claims.map((claim) => ({ ...claim, name: claim.name.trim() })),
      headline: headline.trim(),
      bio: bio.trim(),
      experience_years: Number(experienceYears),
      specialization_id: Number(specializationId),
    };
  }

  function qualificationNameErrors(): FieldErrors {
    const errors: FieldErrors = {};
    claims.forEach((claim, index) => {
      if (!claim.name.trim()) {
        errors[`qualification_claims.${index}.name`] = ["Vui lòng nhập tên bằng cấp hoặc chứng chỉ."];
      }
    });
    return errors;
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
      Object.assign(errors, qualificationNameErrors());
      const years = Number(experienceYears);
      if (experienceYears === "" || !Number.isInteger(years) || years < 0 || years > 80) {
        errors.experience_years = ["Nhập số năm kinh nghiệm từ 0 đến 80."];
      }
    }
    const checkedFields = targetStep === 1 ? ["headline", "bio"] : targetStep === 2 ? ["specialization_id"] : ["experience_years", ...claims.map((_, index) => `qualification_claims.${index}.name`)];
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
      ? await attachTutorAvatar(fileId, profile?.status === "approved" && profile.revision && ["draft", "changes_requested"].includes(profile.revision.status) ? profile.revision.version : 0)
      : (await createVerificationDocument({ fileId, documentType: documentType.trim() }), await getTutorProfile());
    setProfile(attached);
    clearFieldError(purpose === "avatar" ? "avatar_file_id" : "verification_document_file_id");
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

    const allowed = purpose === "avatar"
      ? ["image/jpeg", "image/png"]
      : ["application/pdf", "image/jpeg", "image/png", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
    if (
      file.size <= 0
      || file.size > MAX_UPLOAD_BYTES
      || !allowed.includes(file.type)
    ) {
      setUploads((current) => ({
        ...current,
        [purpose]: {
          ...emptyUpload,
          error: purpose === "avatar"
            ? "Ảnh đại diện phải là JPG hoặc PNG, không rỗng và tối đa 5 MB (5.000.000 byte)."
            : "Tài liệu phải là PDF, JPG, PNG, DOC hoặc DOCX, không rỗng và tối đa 5 MB (5.000.000 byte).",
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
    const errors: FieldErrors = qualificationNameErrors();
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

    const documents = profile?.verification_documents;
    if (verificationDocumentsRequired && documents !== undefined) {
      const validTypes = new Set(documents.filter((document) =>
        document.status !== "rejected" && document.status !== "expired" &&
        (!document.expires_at || Date.parse(document.expires_at) > Date.now()),
      ).map((document) => document.document_type));
      const missingIdentity = !validTypes.has("identity_card") && !validTypes.has("passport");
      const missingQualification = claims.find((claim) => !validTypes.has(claim.type));
      if (missingIdentity || missingQualification) {
        setStep(4);
        setFieldErrors({
          verification_document_file_id: [missingIdentity
            ? "Cần tải CCCD / CMND hoặc hộ chiếu còn hiệu lực trước khi gửi hồ sơ."
            : `Cần tải tài liệu xác minh ${missingQualification?.type === "degree" ? "bằng cấp" : "chứng chỉ"} đã khai báo.`]
        });
        return false;
      }
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

  return { profile, headline, setHeadline, bio, setBio, experienceYears, setExperienceYears, specializationId, setSpecializationId, documentType, setDocumentType, claims, setClaims, step, setStep, furthestStep, uploads, queuedAvatar, setFieldErrors, message, error, saving, locked, changesRequested, busy, verified, avatarFileId, attachedAvatar, attachedDocument, fieldError, clearFieldError, handleUpload, retryUpload, nextStep, submit, goToStep };
}
