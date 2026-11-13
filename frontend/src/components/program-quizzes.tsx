"use client";

import Link from "next/link";
import { useCallback, useState, type FormEvent } from "react";
import { useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Button, Card, StatePanel } from "./ui";
import { describeApiError } from "@/lib/api/client";
import { getProgram } from "@/lib/data/collaboration";
import { retryIdentity, workspaceRead, workspaceWrite, type Collection } from "@/lib/data/workspace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/app/workspace.css";

type QuizSummary = {
  id: number;
  program_id: number;
  title: string;
  description: string | null;
  due_at: string | null;
  question_count: number;
  created_at: string;
  attempt_count?: number;
  attempt?: { score: number; question_count: number; submitted_at: string } | null;
};
type QuizQuestion = { id: number; position: number; prompt: string; options: string[]; correct_option?: number };
type QuizResult = { question_id: number; selected_option: number; correct_option: number; is_correct: boolean };
type QuizDetail = {
  id: number;
  program_id: number;
  title: string;
  description: string | null;
  due_at: string | null;
  questions: QuizQuestion[];
  attempt?: { score: number; question_count: number; submitted_at: string; results: QuizResult[] };
};
type DraftQuestion = { key: number; prompt: string; options: string[]; correct: number };

function dateLabel(value: string | null) {
  if (!value) return "Không giới hạn thời gian";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Không giới hạn thời gian" : date.toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" });
}

export function ProgramQuizzesPage({ id }: { id: number }) {
  const actor = useActor();
  const [page, setPage] = useState(1);
  const [createOpen, setCreateOpen] = useState(false);
  const load = useCallback(async () => {
    const [program, quizzes] = await Promise.all([
      getProgram(id),
      workspaceRead<Collection<QuizSummary>>(`/programs/${id}/quizzes`, { page, per_page: 20 }),
    ]);
    return { program, quizzes };
  }, [id, page]);
  const result = useAsyncData(load);
  const owner = actor?.role === "tutor";
  return <section className="content-stack workspace-page quiz-page">
    <header className="quiz-page-heading"><div><p className="task-detail-eyebrow">WORKSPACE · {owner ? "GIA SƯ" : "HỌC VIÊN"}</p><h1>Bài kiểm tra</h1><p className="muted">Tạo bài trắc nghiệm, làm bài và xem kết quả ngay sau khi nộp.</p></div>{owner && result.data?.program.status === "active" ? <Button onClick={() => setCreateOpen(true)}>＋ Tạo bài kiểm tra</Button> : null}</header>
    <ApiState {...result} />
    {result.data ? <>
      <div className="quiz-program-context"><strong>{result.data.program.title}</strong><Badge tone={result.data.program.status === "active" ? "success" : "warning"}>{result.data.program.status === "active" ? "Đang hoạt động" : result.data.program.status === "paused" ? "Tạm dừng" : "Đã kết thúc"}</Badge></div>
      {result.data.quizzes.data.length ? <div className="quiz-list">{result.data.quizzes.data.map((quiz) => <Card key={quiz.id} className="quiz-list-card">
        <div className="quiz-list-copy"><div className="quiz-card-meta"><span className="quiz-type-label">TRẮC NGHIỆM</span><span>{quiz.question_count} câu hỏi</span><span>{owner ? quiz.attempt ? `Học viên đã hoàn thành · ${quiz.attempt.score}/${quiz.attempt.question_count} câu đúng` : "Học viên chưa làm" : quiz.attempt ? `Đã hoàn thành · ${quiz.attempt.score}/${quiz.attempt.question_count} câu đúng` : `Hạn: ${dateLabel(quiz.due_at)}`}</span></div><h2>{quiz.title}</h2>{quiz.description ? <p>{quiz.description}</p> : null}{quiz.attempt ? <small>Nộp lúc {dateLabel(quiz.attempt.submitted_at)}</small> : null}</div>
        <Link className={`button ${owner ? "button-outline" : "button-primary"}`} href={`/programs/${id}/quizzes/${quiz.id}`}>{owner ? "Xem đáp án →" : quiz.attempt ? "Xem kết quả →" : "Làm bài →"}</Link>
      </Card>)}</div> : <StatePanel kind="empty" message={owner ? "Tạo bài trắc nghiệm đầu tiên cho học viên." : "Gia sư chưa giao bài kiểm tra nào trong chương trình này."} />}
      {(result.data.quizzes.meta?.last_page ?? 1) > 1 ? <div className="button-row"><Button variant="outline" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Trước</Button><span>Trang {page}</span><Button variant="outline" disabled={page >= (result.data.quizzes.meta?.last_page ?? 1)} onClick={() => setPage((current) => current + 1)}>Sau</Button></div> : null}
    </> : null}
    {createOpen && result.data ? <QuizBuilderDialog id={id} programTitle={result.data.program.title} onClose={() => setCreateOpen(false)} onSaved={() => { setCreateOpen(false); result.reload(); }} /> : null}
  </section>;
}

function QuizBuilderDialog({ id, programTitle, onClose, onSaved }: { id: number; programTitle: string; onClose: () => void; onSaved: () => void }) {
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [dueAt, setDueAt] = useState("");
  const [questions, setQuestions] = useState<DraftQuestion[]>([{ key: 1, prompt: "", options: ["", "", "", ""], correct: 0 }]);
  const [nextKey, setNextKey] = useState(2);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const receipt = useState(() => ({ current: null as { payload: string; key: string } | null }))[0];
  function updateQuestion(key: number, update: Partial<DraftQuestion>) {
    setQuestions((items) => items.map((item) => item.key === key ? { ...item, ...update } : item));
  }
  function addQuestion() {
    if (questions.length >= 50) return;
    setQuestions((items) => [...items, { key: nextKey, prompt: "", options: ["", "", "", ""], correct: 0 }]);
    setNextKey((value) => value + 1);
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const normalized = questions.map((question) => ({
        prompt: question.prompt.trim(),
        options: question.options.map((option) => option.trim()),
        correct_option: question.correct,
      }));
      if (normalized.some((question) => !question.prompt || question.options.some((option) => !option) || new Set(question.options).size !== question.options.length)) {
        throw new Error("Điền nội dung câu hỏi và các lựa chọn; mỗi lựa chọn cần khác nhau.");
      }
      const payload = { title: title.trim(), description: description.trim() || null, due_at: dueAt ? new Date(dueAt).toISOString() : null, questions: normalized };
      receipt.current = retryIdentity(receipt.current, { url: `/programs/${id}/quizzes`, method: "POST", payload });
      await workspaceWrite(`/programs/${id}/quizzes`, "POST", payload, receipt.current.key);
      onSaved();
    } catch (reason) {
      setError(describeApiError(reason));
    } finally {
      setBusy(false);
    }
  }
  return <div className="quiz-dialog-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <section className="quiz-dialog" role="dialog" aria-modal="true" aria-labelledby="quiz-builder-title">
      <header><div><p className="task-detail-eyebrow">BÀI KIỂM TRA · {programTitle}</p><h2 id="quiz-builder-title">Tạo bài kiểm tra</h2><p>Thêm câu hỏi và chọn đáp án đúng. Hệ thống sẽ tự chấm sau khi học viên nộp bài.</p></div><button className="quiz-dialog-close" type="button" onClick={onClose} aria-label="Đóng">×</button></header>
      <form className="quiz-builder-form" onSubmit={(event) => void submit(event)}>
        <label>Tên bài kiểm tra<input className="input" required maxLength={180} value={title} onChange={(event) => setTitle(event.target.value)} placeholder="Ví dụ: Ôn tập từ vựng Unit 1" /></label>
        <label>Hướng dẫn <span>(không bắt buộc)</span><textarea className="input" rows={2} maxLength={10000} value={description} onChange={(event) => setDescription(event.target.value)} placeholder="Ghi chú ngắn cho học viên" /></label>
        <label>Hạn làm bài <span>(không bắt buộc)</span><input className="input" type="datetime-local" value={dueAt} onChange={(event) => setDueAt(event.target.value)} /></label>
        <div className="quiz-questions-heading"><div><h3>Câu hỏi <span>{questions.length}</span></h3><p>Mỗi câu có từ 2 đến 5 lựa chọn và một đáp án đúng.</p></div><Button type="button" variant="outline" disabled={questions.length >= 50} onClick={addQuestion}>＋ Thêm câu hỏi</Button></div>
        {questions.map((question, index) => <article className="quiz-question-editor" key={question.key}>
          <div className="quiz-question-editor-heading"><strong>Câu {index + 1}</strong>{questions.length > 1 ? <button type="button" onClick={() => setQuestions((items) => items.filter((item) => item.key !== question.key))}>Xóa câu</button> : null}</div>
          <label>Nội dung câu hỏi<textarea className="input" required maxLength={2000} rows={2} value={question.prompt} onChange={(event) => updateQuestion(question.key, { prompt: event.target.value })} placeholder="Nhập câu hỏi" /></label>
          <div className="quiz-option-editor-list">{question.options.map((option, optionIndex) => <label className="quiz-option-editor" key={`${question.key}-${optionIndex}`}><input type="radio" name={`correct-${question.key}`} checked={question.correct === optionIndex} onChange={() => updateQuestion(question.key, { correct: optionIndex })} aria-label={`Chọn lựa chọn ${optionIndex + 1} làm đáp án đúng`} /><input className="input" required maxLength={300} value={option} onChange={(event) => updateQuestion(question.key, { options: question.options.map((value, i) => i === optionIndex ? event.target.value : value) })} placeholder={`Lựa chọn ${optionIndex + 1}`} /><span>{question.correct === optionIndex ? "Đáp án đúng" : "Chọn làm đáp án đúng"}</span>{question.options.length > 2 ? <button type="button" aria-label={`Xóa lựa chọn ${optionIndex + 1}`} onClick={() => updateQuestion(question.key, { options: question.options.filter((_, i) => i !== optionIndex), correct: question.correct === optionIndex ? 0 : question.correct > optionIndex ? question.correct - 1 : question.correct })}>×</button> : null}</label>)}</div>
          {question.options.length < 5 ? <button className="quiz-add-option" type="button" onClick={() => updateQuestion(question.key, { options: [...question.options, ""] })}>＋ Thêm lựa chọn</button> : null}
        </article>)}
        {error ? <p className="field-error" role="alert">{error}</p> : null}
        <footer><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Hủy</Button><Button type="submit" disabled={busy}>{busy ? "Đang lưu…" : "Tạo bài kiểm tra"}</Button></footer>
      </form>
    </section>
  </div>;
}

export function QuizDetailPage({ programId, quizId }: { programId: number; quizId: number }) {
  const actor = useActor();
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const load = useCallback(async () => {
    const [program, response] = await Promise.all([
      getProgram(programId),
      workspaceRead<{ data: QuizDetail }>(`/quizzes/${quizId}`),
    ]);
    if (response.data.program_id !== programId) throw new Error("Bài kiểm tra không thuộc chương trình này.");
    return { program, quiz: response.data };
  }, [programId, quizId]);
  const result = useAsyncData(load);
  const d = result.data;
  const owner = actor?.role === "tutor";
  const completed = !owner && !!d?.quiz.attempt;
  const studentCompleted = !!d?.quiz.attempt;
  const expired = !!d?.quiz.due_at && new Date(d.quiz.due_at).getTime() < Date.now();
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!d || busy || completed) return;
    if (Object.keys(answers).length !== d.quiz.questions.length) {
      setError("Hãy chọn một đáp án cho mỗi câu hỏi trước khi nộp.");
      return;
    }
    setBusy(true);
    setError("");
    try {
      await workspaceWrite(`/quizzes/${quizId}/attempts`, "POST", {
        answers: Object.entries(answers).map(([questionId, optionIndex]) => ({ question_id: Number(questionId), option_index: optionIndex })),
      }, crypto.randomUUID());
      result.reload();
    } catch (reason) {
      setError(describeApiError(reason));
    } finally {
      setBusy(false);
    }
  }
  return <section className="content-stack workspace-page quiz-detail-page">
    <Link className="task-workspace-back" href={`/programs/${programId}/quizzes`}><span aria-hidden="true">←</span> Quay lại bài kiểm tra</Link>
    <ApiState {...result} />
    {d ? <>
      <header className="quiz-detail-heading"><div><p className="task-detail-eyebrow">BÀI KIỂM TRA · {d.program.title}</p><h1>{d.quiz.title}</h1>{d.quiz.description ? <p>{d.quiz.description}</p> : null}</div>{owner ? <Badge tone={studentCompleted ? "success" : "brand"}>{studentCompleted ? "Học viên đã hoàn thành" : "Học viên chưa làm"}</Badge> : completed ? <Badge tone="success">Đã hoàn thành</Badge> : <Badge tone={expired ? "danger" : "brand"}>{expired ? "Đã hết hạn" : "Chưa làm"}</Badge>}</header>
      {d.quiz.attempt ? <Card className="quiz-result-banner"><span>{owner ? "Kết quả học viên" : "Kết quả của bạn"}</span><strong>{d.quiz.attempt.score}<small> / {d.quiz.attempt.question_count}</small></strong><p>Đã tự động chấm · {dateLabel(d.quiz.attempt.submitted_at)}</p></Card> : null}
      <form className="quiz-detail-form" onSubmit={(event) => void submit(event)}>
        {d.quiz.questions.map((question) => {
          const answer = d.quiz.attempt?.results.find((item) => item.question_id === question.id);
          return <Card className="quiz-detail-question" key={question.id}><div className="quiz-detail-question-title"><span>Câu {question.position}</span>{answer ? <Badge tone={answer.is_correct ? "success" : "danger"}>{answer.is_correct ? "Đúng" : "Chưa đúng"}</Badge> : null}</div><h2>{question.prompt}</h2><div className="quiz-answer-list">{question.options.map((option, optionIndex) => {
            const isCorrect = question.correct_option === optionIndex;
            const isSelected = studentCompleted ? answer?.selected_option === optionIndex : !owner && answers[question.id] === optionIndex;
            const showAnswerKey = owner || completed;
            return <label className={`quiz-answer-option${showAnswerKey && isCorrect ? " is-correct" : ""}${studentCompleted && isSelected && !isCorrect ? " is-incorrect" : ""}`} key={`${question.id}-${optionIndex}`}><input type="radio" name={`question-${question.id}`} checked={isSelected} disabled={completed || owner || expired || busy} onChange={() => setAnswers((current) => ({ ...current, [question.id]: optionIndex }))} /><span>{option}</span>{showAnswerKey && isCorrect ? <strong>Đáp án đúng</strong> : null}{studentCompleted && isSelected && !isCorrect ? <strong>Bạn đã chọn</strong> : null}</label>;
          })}</div></Card>;
        })}
        {!owner && !completed && !expired ? <><p className="quiz-submit-hint">Bạn chỉ có thể nộp bài một lần. Hệ thống sẽ chấm ngay sau khi nộp.</p>{error ? <p className="field-error" role="alert">{error}</p> : null}<Button type="submit" disabled={busy}>{busy ? "Đang nộp…" : "Nộp bài và xem kết quả"}</Button></> : null}
        {owner ? <p className="quiz-answer-key-note">Đáp án đúng được đánh dấu màu xanh để gia sư tiện xem lại.</p> : null}
        {expired && !owner && !completed ? <p className="field-error">Đã quá hạn làm bài kiểm tra.</p> : null}
      </form>
    </> : null}
  </section>;
}
