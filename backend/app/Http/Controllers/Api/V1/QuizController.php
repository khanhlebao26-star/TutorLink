<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use App\Support\BeD04Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class QuizController extends Controller
{
    public function index(Request $request, int $program): JsonResponse
    {
        $paging = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);
        $record = $this->visibleProgram($request, $program);
        $isOwner = $this->isOwner($record, $request->user());
        $paginator = DB::table('quizzes')->where('program_id', $program)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($paging['per_page'] ?? 20, ['*'], 'page', $paging['page'] ?? 1);
        $items = $paginator->getCollection()->map(function (stdClass $quiz) use ($request, $isOwner, $record): array {
            $item = [
                'id' => (int) $quiz->id,
                'program_id' => (int) $quiz->program_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'due_at' => $quiz->due_at,
                'question_count' => (int) DB::table('quiz_questions')->where('quiz_id', $quiz->id)->count(),
                'created_at' => $quiz->created_at,
            ];
            if ($isOwner) {
                $item['attempt_count'] = (int) DB::table('quiz_attempts')->where('quiz_id', $quiz->id)->count();
            }
            $attempt = DB::table('quiz_attempts')->where('quiz_id', $quiz->id)
                ->where('customer_id', $isOwner ? $record->customer_id : $request->user()->id)->first();
            $item['attempt'] = $attempt === null ? null : [
                'score' => (int) $attempt->score,
                'question_count' => (int) $attempt->question_count,
                'submitted_at' => $attempt->submitted_at,
            ];

            return $item;
        })->values();

        return response()->json(['data' => $items, 'meta' => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]]);
    }

    public function store(Request $request, int $program): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:10000',
            'due_at' => 'nullable|date',
            'questions' => 'required|array|min:1|max:50',
            'questions.*.prompt' => 'required|string|max:2000',
            'questions.*.options' => 'required|array|min:2|max:5',
            'questions.*.options.*' => 'required|string|max:300|distinct',
            'questions.*.correct_option' => 'required|integer|min:0|max:4',
        ]);
        foreach ($data['questions'] as $index => $question) {
            if ($question['correct_option'] >= count($question['options'])) {
                throw ValidationException::withMessages([
                    "questions.{$index}.correct_option" => ['Chọn một đáp án có trong câu hỏi.'],
                ]);
            }
        }

        $requestId = (string) $request->attributes->get('request_id');
        $quizId = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $request->user(),
            $program,
            $requestId,
            function (Program $record, User $owner, ?object $member) use ($data): int {
                if ($record->status !== 'active') {
                    abort(409, 'Chỉ có thể tạo bài kiểm tra trong chương trình đang hoạt động.');
                }
                $now = now('UTC');
                $id = DB::table('quizzes')->insertGetId([
                    'program_id' => $record->id,
                    'created_by' => $owner->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'due_at' => $data['due_at'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach ($data['questions'] as $position => $question) {
                    DB::table('quiz_questions')->insert([
                        'quiz_id' => $id,
                        'position' => $position + 1,
                        'prompt' => $question['prompt'],
                        'options' => json_encode(array_values($question['options']), JSON_THROW_ON_ERROR),
                        'correct_option' => $question['correct_option'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                return $id;
            },
        );

        return response()->json(['data' => $this->quizData($quizId, true)], 201);
    }

    public function show(Request $request, int $quiz): JsonResponse
    {
        [$record, $program, $isOwner] = $this->visibleQuiz($request, $quiz);
        $attempt = DB::table('quiz_attempts')->where('quiz_id', $record->id)
            ->where('customer_id', $isOwner ? $program->customer_id : $request->user()->id)->first();

        return response()->json(['data' => $this->quizData((int) $record->id, $isOwner, $attempt)]);
    }

    public function submit(Request $request, int $quiz): JsonResponse
    {
        $data = $request->validate([
            'answers' => 'required|array|min:1|max:50',
            'answers.*.question_id' => 'required|integer|distinct',
            'answers.*.option_index' => 'required|integer|min:0|max:4',
        ]);
        [$record, $program, $isOwner] = $this->visibleQuiz($request, $quiz);
        abort_if($isOwner || (int) $program->customer_id !== (int) $request->user()->id, 403);
        abort_if($program->status !== 'active', 409, 'Chương trình không còn hoạt động.');
        if ($record->due_at !== null && CarbonImmutable::parse($record->due_at)->isPast()) {
            abort(409, 'Đã quá hạn làm bài kiểm tra.');
        }

        $result = DB::transaction(function () use ($request, $record, $data): array {
            $quiz = DB::table('quizzes')->where('id', $record->id)->lockForUpdate()->first();
            abort_unless($quiz !== null, 404);
            if (DB::table('quiz_attempts')->where('quiz_id', $quiz->id)
                ->where('customer_id', $request->user()->id)->exists()) {
                abort(409, 'Bài kiểm tra này đã được nộp.');
            }
            $questions = DB::table('quiz_questions')->where('quiz_id', $quiz->id)->orderBy('position')->get();
            $answerMap = collect($data['answers'])->keyBy(fn (array $answer): int => (int) $answer['question_id']);
            if ($answerMap->count() !== $questions->count()) {
                throw ValidationException::withMessages(['answers' => ['Hãy chọn đáp án cho tất cả câu hỏi.']]);
            }
            $score = 0;
            $answers = [];
            $results = [];
            foreach ($questions as $question) {
                $answer = $answerMap->get((int) $question->id);
                if ($answer === null) {
                    throw ValidationException::withMessages(['answers' => ['Câu trả lời không khớp với bài kiểm tra này.']]);
                }
                $options = $this->decodeJson($question->options);
                $selected = (int) $answer['option_index'];
                if (! array_key_exists($selected, $options)) {
                    throw ValidationException::withMessages(['answers' => ['Đáp án đã chọn không hợp lệ.']]);
                }
                $correct = $selected === (int) $question->correct_option;
                $score += $correct ? 1 : 0;
                $answers[] = ['question_id' => (int) $question->id, 'option_index' => $selected];
                $results[] = [
                    'question_id' => (int) $question->id,
                    'selected_option' => $selected,
                    'correct_option' => (int) $question->correct_option,
                    'is_correct' => $correct,
                ];
            }
            $now = now('UTC');
            DB::table('quiz_attempts')->insert([
                'quiz_id' => $quiz->id,
                'customer_id' => $request->user()->id,
                'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
                'score' => $score,
                'question_count' => $questions->count(),
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'score' => $score,
                'question_count' => $questions->count(),
                'results' => $results,
                'submitted_at' => $now->toISOString(),
            ];
        }, attempts: 3);

        return response()->json(['data' => $result], 201);
    }

    /** @return array{0: stdClass, 1: Program, 2: bool} */
    private function visibleQuiz(Request $request, int $quiz): array
    {
        $record = DB::table('quizzes')->where('id', $quiz)->first();
        abort_unless($record !== null, 404);
        $program = $this->visibleProgram($request, (int) $record->program_id);

        return [$record, $program, $this->isOwner($program, $request->user())];
    }

    private function visibleProgram(Request $request, int $program): Program
    {
        return app(BeD04Workspace::class)->visible(
            $request->user(),
            $program,
            (string) $request->attributes->get('request_id'),
        );
    }

    private function isOwner(Program $program, User $actor): bool
    {
        return $actor->role === 'tutor'
            && $program->ownerTutorProfile()->where('user_id', $actor->id)->exists();
    }

    private function quizData(int $quizId, bool $includeAnswers, ?stdClass $attempt = null): array
    {
        $quiz = DB::table('quizzes')->find($quizId);
        abort_unless($quiz !== null, 404);
        $showAnswers = $includeAnswers || $attempt !== null;
        $questions = DB::table('quiz_questions')->where('quiz_id', $quizId)->orderBy('position')->get()
            ->map(function (stdClass $question) use ($showAnswers): array {
                $item = [
                    'id' => (int) $question->id,
                    'position' => (int) $question->position,
                    'prompt' => $question->prompt,
                    'options' => $this->decodeJson($question->options),
                ];
                if ($showAnswers) {
                    $item['correct_option'] = (int) $question->correct_option;
                }

                return $item;
            })->values();
        $data = [
            'id' => (int) $quiz->id,
            'program_id' => (int) $quiz->program_id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'due_at' => $quiz->due_at,
            'created_at' => $quiz->created_at,
            'questions' => $questions,
        ];
        if ($attempt !== null) {
            $answerMap = collect($this->decodeJson($attempt->answers))->keyBy('question_id');
            $data['attempt'] = [
                'score' => (int) $attempt->score,
                'question_count' => (int) $attempt->question_count,
                'submitted_at' => $attempt->submitted_at,
                'results' => $questions->map(fn (array $question): array => [
                    'question_id' => $question['id'],
                    'selected_option' => (int) ($answerMap->get($question['id'])['option_index'] ?? -1),
                    'correct_option' => $question['correct_option'],
                    'is_correct' => (int) ($answerMap->get($question['id'])['option_index'] ?? -1) === $question['correct_option'],
                ])->values(),
            ];
        }

        return $data;
    }

    /** @return array<mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
