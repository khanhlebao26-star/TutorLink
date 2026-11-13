"use client";

import { Badge, Button, Card, Field, Input, Select } from "@/components/shared/ui";
import { UploadPurpose, statusLabel, statusTone, steps, verificationDocumentsRequired } from "@/components/tutors/onboarding/onboarding-config";
import { useTutorOnboarding } from "@/components/tutors/onboarding/use-tutor-onboarding";
import type { TutorOnboardingData } from "@/lib/data/types";

export function TutorOnboardingForm({ data, onReload }: { data: TutorOnboardingData; onReload: () => void }) {
  const { profile, headline, setHeadline, bio, setBio, experienceYears, setExperienceYears, specializationId, setSpecializationId, documentType, setDocumentType, claims, setClaims, step, setStep, furthestStep, uploads, queuedAvatar, setFieldErrors, message, error, saving, locked, changesRequested, busy, verified, avatarFileId, attachedAvatar, attachedDocument, fieldError, clearFieldError, handleUpload, retryUpload, nextStep, submit, goToStep } = useTutorOnboarding({ data });

  function renderUpload(purpose: UploadPurpose, label: string) {
    const state = uploads[purpose];
    const file = purpose === "avatar" ? queuedAvatar : null;
    const attachedFileId = purpose === "avatar" ? avatarFileId : profile?.verification_document_file_id;
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
              {purpose === "avatar" ? <span className="onboarding-required"> *</span> : <span className="muted"> · {verificationDocumentsRequired ? "Giấy tờ định danh bắt buộc" : "Không bắt buộc khi kiểm thử"}</span>}
            </strong>
            <p>
              {purpose === "avatar"
                ? "Ảnh hồ sơ rõ mặt, JPG hoặc PNG, tối đa 5 MB (5.000.000 byte)."
                : verificationDocumentsRequired
                  ? "Tài liệu chứng minh thông tin chuyên môn, PDF/JPG/PNG/DOC/DOCX, tối đa 5 MB (5.000.000 byte). Cần giấy tờ định danh và tài liệu khớp bằng cấp đã khai báo. Complete chỉ kiểm tra kỹ thuật; nội dung được duyệt riêng."
                  : "Bạn có thể bỏ qua khi kiểm thử. Khi đưa lên production, hồ sơ sẽ cần giấy tờ định danh và tài liệu khớp bằng cấp đã khai báo."}
            </p>
          </div>
        </div>
        {purpose === "verification_document" ? (
          <div className="onboarding-field onboarding-document-type">
            <Field label={`Loại tài liệu${verificationDocumentsRequired ? " *" : ""}`} htmlFor="tutor-document_type" error={fieldError("document_type")}><Select value={documentType} disabled={locked || busy} onChange={event => setDocumentType(event.target.value)}><option value="identity_card">CCCD / CMND</option><option value="passport">Hộ chiếu</option><option value="degree">Bằng cấp</option><option value="certificate">Chứng chỉ</option><option value="other">Khác</option></Select></Field>
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
          <small>
            {purpose === "avatar"
              ? "JPG, PNG · tối đa 5 MB (5.000.000 byte)"
              : "PDF, JPG, PNG, DOC, DOCX · tối đa 5 MB (5.000.000 byte)"}
          </small>
        </label>
        {!verified && purpose === "verification_document" ? <p className="onboarding-inline-note">Xác minh email trước khi tải tài liệu. <a href="/auth/verify-email">Xác minh email</a></p> : null}
        {!profile && purpose === "verification_document" ? <p className="onboarding-inline-note">Lưu các bước thông tin trước khi tải tài liệu.</p> : null}
        {attachedFileId && !state.message ? <p className="onboarding-upload-status is-complete" role="status">✓ {purpose === "avatar" ? "Đã tải ảnh đại diện lên." : "Đã tải tài liệu xác minh lên."}</p> : null}
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
  const verificationDocuments = profile?.verification_documents ?? [];
  const verificationDocumentTypes: Record<string, string> = {
    identity_card: "CCCD / CMND",
    passport: "Hộ chiếu",
    degree: "Bằng cấp",
    certificate: "Chứng chỉ",
    other: "Tài liệu khác",
  };
  const verificationDocumentStatuses: Record<string, string> = {
    pending: "Đang chờ duyệt",
    active: "Đã tải lên",
    approved: "Đã xác minh",
    rejected: "Cần bổ sung",
    expired: "Đã hết hạn",
  };

  return (
    <div className="onboarding-layout">
      {profile?.status === "suspended" ? <p className="onboarding-alert" role="alert">Hồ sơ đang tạm ngưng. {profile.suspension_reason}</p> : null}
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
                <p>{step === 1 ? "Giúp học viên hiểu rõ hơn về bạn và cách bạn giảng dạy." : step === 2 ? "Chọn chuyên môn phù hợp nhất với dịch vụ giảng dạy của bạn." : step === 3 ? "Chia sẻ số năm kinh nghiệm giảng dạy." : step === 4 ? verificationDocumentsRequired ? "Tải giấy tờ định danh và tài liệu tương ứng với bằng cấp đã khai báo. File complete chỉ xác nhận kiểm tra kỹ thuật; nội dung được duyệt riêng." : "Bạn có thể bỏ qua bước tải tài liệu để thử nghiệm. Hồ sơ vẫn cần các thông tin cơ bản, chuyên môn và ảnh đại diện." : "Kiểm tra thông tin trước khi gửi xét duyệt."}</p>
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
              <div className="onboarding-experience-layout">
                <section className="onboarding-experience-card onboarding-credentials-card" aria-labelledby="tutor-credentials-title">
                  <div className="onboarding-experience-card-heading">
                    <span className="onboarding-experience-icon" aria-hidden="true">▤</span>
                    <div>
                      <h3 id="tutor-credentials-title">Bằng cấp &amp; chứng chỉ</h3>
                      <p>Khai báo những văn bằng liên quan đến chuyên môn giảng dạy của bạn.</p>
                    </div>
                    <span className="onboarding-credential-count">{claims.length}</span>
                  </div>
                  {claims.map((claim, index) => {
                    const nameField = `qualification_claims.${index}.name`;
                    return (
                      <div className="onboarding-credential-row" key={index}>
                        <span className="onboarding-credential-row-icon" aria-hidden="true">{claim.type === "certificate" ? "✧" : "▤"}</span>
                        <div className="onboarding-credential-fields">
                          <Select aria-label={`Loại chứng nhận ${index + 1}`} disabled={locked || busy} value={claim.type}
                            onChange={(event) => setClaims(claims.map((item, i) => i === index
                              ? { ...item, type: event.target.value === "certificate" ? "certificate" : "degree" }
                              : item))}>
                            <option value="degree">Bằng cấp</option>
                            <option value="certificate">Chứng chỉ</option>
                          </Select>
                          <Field label={`Tên chứng nhận ${index + 1}`} htmlFor={`tutor-${nameField}`} error={fieldError(nameField)}>
                            <Input maxLength={160} required disabled={locked || busy} value={claim.name}
                              placeholder="Ví dụ: Cử nhân Sư phạm Toán"
                              onChange={(event) => {
                                setClaims(claims.map((item, i) => i === index ? { ...item, name: event.target.value } : item));
                                clearFieldError(nameField);
                              }} />
                          </Field>
                        </div>
                        <Button variant="ghost" className="onboarding-remove-credential" type="button" aria-label={`Xóa chứng nhận ${index + 1}`} disabled={locked || busy} onClick={() => {
                          setClaims(claims.filter((_, i) => i !== index));
                          setFieldErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => !key.startsWith("qualification_claims."))));
                        }}>Xóa</Button>
                      </div>
                    );
                  })}
                  <Button variant="outline" className="onboarding-add-credential" type="button" disabled={locked || busy || claims.length >= 20}
                    onClick={() => setClaims([...claims, { type: "degree", name: "" }])}><span aria-hidden="true">＋</span> Thêm bằng cấp hoặc chứng chỉ</Button>
                  <p className="onboarding-credential-footnote">Bạn có thể bổ sung giấy tờ xác minh ở bước tiếp theo.</p>
                </section>

                <section className="onboarding-experience-card onboarding-years-card" aria-labelledby="tutor-experience-title">
                  <div className="onboarding-experience-card-heading">
                    <span className="onboarding-experience-icon" aria-hidden="true">◷</span>
                    <div>
                      <h3 id="tutor-experience-title">Thâm niên giảng dạy</h3>
                      <p>Số năm kinh nghiệm thực tế của bạn.</p>
                    </div>
                  </div>
                  <div className="onboarding-field onboarding-years-field">
                    <Field label="Số năm kinh nghiệm *" htmlFor="tutor-experience_years" error={fieldError("experience_years")} hint="Nhập số năm hoàn chỉnh, từ 0 đến 80.">
                      <Input type="number" min={0} max={80} step={1} value={experienceYears} disabled={locked || busy} required aria-invalid={Boolean(fieldError("experience_years"))} onChange={(event) => { setExperienceYears(event.target.value); clearFieldError("experience_years"); }} />
                    </Field>
                  </div>
                  <div className="onboarding-experience-note">
                    <span aria-hidden="true">✦</span>
                    <p>Kinh nghiệm giúp học viên hiểu rõ nền tảng và quá trình giảng dạy của bạn.</p>
                  </div>
                </section>
              </div>
            ) : null}

            {step === 4 ? (
              <div className="onboarding-fields onboarding-document-step">
                <section className="onboarding-document-intro" aria-labelledby="tutor-document-intro-title">
                  <span className="onboarding-document-intro-icon" aria-hidden="true">✓</span>
                  <div className="onboarding-document-intro-copy">
                    <p className="onboarding-eyebrow">XÁC MINH CHUYÊN MÔN</p>
                    <h3 id="tutor-document-intro-title">Tài liệu giúp hồ sơ thêm tin cậy</h3>
                    <p>{verificationDocumentsRequired
                      ? "Tải giấy tờ định danh và tài liệu khớp với bằng cấp, chứng chỉ bạn đã khai báo. Bạn có thể xem trạng thái từng tài liệu ngay tại đây."
                      : "Bạn có thể tải giấy tờ định danh và bằng cấp để hồ sơ đáng tin cậy hơn, hoặc bỏ qua bước này trong khi kiểm thử."}</p>
                  </div>
                  <div className={`onboarding-document-progress ${attachedDocument ? "is-complete" : ""}`}>
                    <span aria-hidden="true">{attachedDocument ? "✓" : "1"}</span>
                    <div><strong>{attachedDocument ? "Đã nhận tài liệu" : "Bước xác minh"}</strong><small>{attachedDocument ? "Tài liệu đã được tải lên" : "Tải lên giấy tờ đầu tiên"}</small></div>
                  </div>
                </section>

                <section className="onboarding-document-upload-section" aria-labelledby="tutor-document-upload-title">
                  <div className="onboarding-document-section-heading">
                    <div>
                      <span className="onboarding-eyebrow">GIẤY TỜ CỦA BẠN</span>
                      <h3 id="tutor-document-upload-title">Tải tài liệu xác minh</h3>
                    </div>
                    <span className="onboarding-document-required">{verificationDocumentsRequired ? <><span aria-hidden="true">*</span> Bắt buộc giấy tờ định danh</> : "Tùy chọn khi kiểm thử"}</span>
                  </div>
                  {renderUpload("verification_document", "Tài liệu xác minh")}
                </section>

                <div className="onboarding-document-guidance-grid">
                  <div className="onboarding-document-summary">
                    <span className="onboarding-document-guidance-icon" aria-hidden="true">▤</span>
                    <div>
                      <strong>Định dạng được chấp nhận</strong>
                      <p>PDF, JPG, PNG, DOC hoặc DOCX. Kích thước tối đa 5 MB cho mỗi tệp.</p>
                    </div>
                  </div>
                  <div className={`onboarding-avatar-summary ${attachedAvatar ? "is-ready" : "is-missing"}`}>
                    <span className="onboarding-document-guidance-icon" aria-hidden="true">{attachedAvatar ? "✓" : "!"}</span>
                    <div className="onboarding-avatar-summary-copy">
                      <strong role="status">{uploads.avatar.busy ? uploads.avatar.message : attachedAvatar ? "Ảnh đại diện đã sẵn sàng" : "Bạn chưa tải ảnh đại diện"}</strong>
                      <p>{attachedAvatar ? "Ảnh đại diện đã được gắn vào hồ sơ của bạn." : "Hồ sơ cần có ảnh đại diện trước khi gửi xét duyệt."}</p>
                      {!attachedAvatar ? <Button variant="ghost" className="onboarding-link-button" type="button" disabled={busy} onClick={() => setStep(1)}>Quay lại thêm ảnh đại diện <span aria-hidden="true">→</span></Button> : null}
                      {uploads.avatar.error ? <span className="onboarding-field-error" role="alert">{uploads.avatar.error}</span> : null}
                      {!attachedAvatar && (uploads.avatar.file || queuedAvatar) && uploads.avatar.error ? <Button variant="ghost" className="onboarding-link-button" type="button" disabled={busy || locked} onClick={() => void retryUpload("avatar")}>Thử tải ảnh lại</Button> : null}
                    </div>
                  </div>
                </div>

                {verificationDocuments.length ? (
                  <section className="onboarding-document-list" aria-labelledby="tutor-document-list-title">
                    <div className="onboarding-document-section-heading">
                      <div><span className="onboarding-eyebrow">HỒ SƠ CỦA BẠN</span><h3 id="tutor-document-list-title">Tài liệu đã gửi</h3></div>
                      <span className="onboarding-document-count">{verificationDocuments.length} tài liệu</span>
                    </div>
                    <ul>
                      {verificationDocuments.map((document) => (
                        <li className="onboarding-document-row" key={document.id}>
                          <span className="onboarding-document-row-icon" aria-hidden="true">▤</span>
                          <div className="onboarding-document-row-copy">
                            <strong>{document.original_name || verificationDocumentTypes[document.document_type] || document.document_type}</strong>
                            <span>{verificationDocumentTypes[document.document_type] || document.document_type}</span>
                          </div>
                          <span className={`onboarding-document-status is-${document.status}`} role="status">{verificationDocumentStatuses[document.status] || document.status}</span>
                        </li>
                      ))}
                    </ul>
                  </section>
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

        {profile?.revision && <p role="status">Bản sửa #{profile.revision.revision_no}: {profile.revision.status} · {profile.revision.review_reason}</p>}
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
