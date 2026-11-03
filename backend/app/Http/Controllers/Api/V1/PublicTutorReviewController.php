<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TutorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PublicTutorReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $profile = TutorProfile::query()->select('id')
            ->where('public_slug', $slug)
            ->visiblePublic()
            ->firstOrFail();

        // Historical membership remains valid after a customer leaves the Program.
        $visible = DB::table('reviews')
            ->join('programs', 'programs.id', '=', 'reviews.program_id')
            ->join('users as authors', 'authors.id', '=', 'reviews.customer_id')
            ->where('reviews.visibility', 'visible')
            ->where('reviews.tutor_profile_id', $profile->id)
            ->whereColumn('programs.owner_tutor_profile_id', 'reviews.tutor_profile_id')
            ->where('programs.status', 'completed')
            ->whereBetween('reviews.rating', [1, 5])
            ->where('authors.role', 'customer')
            ->whereNull('authors.deleted_at')
            ->whereExists(function ($members): void {
                $members->selectRaw('1')->from('program_members')
                    ->whereColumn('program_members.program_id', 'reviews.program_id')
                    ->whereColumn('program_members.user_id', 'reviews.customer_id')
                    ->where('program_members.member_role', 'customer');
            });

        $ratings = (clone $visible)->selectRaw('reviews.rating, COUNT(*) AS total')
            ->groupBy('reviews.rating')->pluck('total', 'rating');
        $histogram = [];
        $count = 0;
        $sum = 0;
        foreach (range(1, 5) as $rating) {
            $histogram[(string) $rating] = (int) ($ratings[$rating] ?? 0);
            $count += $histogram[(string) $rating];
            $sum += $rating * $histogram[(string) $rating];
        }

        $reviews = $visible->select([
            'reviews.id',

            'reviews.rating',
            'reviews.comment',
            'reviews.created_at',
        ])->orderByDesc('reviews.created_at')->orderByDesc('reviews.id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => collect($reviews->items())->map(fn ($review) => [
                'id' => (int) $review->id,
                'author_name' => 'Customer đã xác minh',
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at === null ? null : Carbon::parse($review->created_at)->toISOString(),
            ]),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'aggregate' => [
                'average' => $count > 0 ? round($sum / $count, 2) : null,
                'count' => $count,
                'histogram' => (object) $histogram,
            ],
        ]);
    }
}
