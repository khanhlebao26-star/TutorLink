import { QuizDetailPage } from "@/components/programs/program-quizzes";
import { notFound } from "next/navigation";

export default async function Page({ params }: { params: Promise<{ id: string; quizId: string }> }) {
  const { id, quizId } = await params;
  if (![id, quizId].every((value) => /^\d+$/.test(value) && Number.isSafeInteger(Number(value)) && Number(value) > 0)) notFound();
  return <QuizDetailPage programId={Number(id)} quizId={Number(quizId)} />;
}
