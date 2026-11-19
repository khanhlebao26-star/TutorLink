<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\ServiceListing;
use App\Models\Specialization;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    private const DEEP_PAGE_MIN_OFFSET = 20000;

    #[QueryParameter('include', 'Optional tutor_card: compact public headline, experience and visible review aggregate for each listing.', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'include' => ['sometimes', Rule::in(['tutor_card'])],
            'q' => ['sometimes', 'string', 'max:120'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['sometimes', 'integer', 'exists:subcategories,id'],
            'specialization_id' => [
                'sometimes',
                'integer',
                'exists:specializations,id',
            ],
            'delivery_mode' => [
                'sometimes',
                Rule::in(['online', 'offline', 'hybrid']),
            ],
            'city' => ['sometimes', 'string', 'max:120'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0'],
            'price_unit' => [
                'sometimes',
                Rule::in(['session', 'hour', 'course']),
            ],
            'sort' => [
                'sometimes',
                Rule::in(['newest', 'price_asc', 'price_desc']),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = ServiceListing::query()
            ->with([
                'tutorProfile:id,user_id,public_slug,headline,experience_years',
                'tutorProfile.user:id,full_name',
                'specialization:id,subcategory_id,name',
                'specialization.subcategory:id,category_id,name',
                'specialization.subcategory.category:id,name',
            ])
            ->visiblePublic();

        $query
            ->when(! empty($filters['q']), function ($query) use ($filters): void {
                $term = $filters['q'];

                $query->where(function ($query) use ($term): void {
                    $query
                        ->whereLike('title', "%{$term}%")
                        ->orWhereLike('description', "%{$term}%");
                });
            })
            ->when(isset($filters['category_id']), fn ($query) => $query->whereHas(
                'specialization.subcategory.category',
                fn ($category) => $category->whereKey(
                    $filters['category_id'],
                ),
            ))
            ->when(isset($filters['subcategory_id']), fn ($query) => $query->whereHas(
                'specialization.subcategory',
                fn ($subcategory) => $subcategory->whereKey(
                    $filters['subcategory_id'],
                ),
            ))
            ->when(isset($filters['specialization_id']), fn ($query) => $query->where(
                'specialization_id',
                $filters['specialization_id'],
            ))
            ->when(isset($filters['delivery_mode']), fn ($query) => $query->where(
                'delivery_mode',
                $filters['delivery_mode'],
            ))
            ->when(isset($filters['city']), fn ($query) => $query->where('city', $filters['city']));

        $sort = $filters['sort'] ?? 'newest';
        $priceUnit = $filters['price_unit'] ?? null;

        $usingPriceFilter = isset($filters['min_price'])
            || isset($filters['max_price']);

        $usingPriceSort = in_array(
            $sort,
            ['price_asc', 'price_desc'],
            true,
        );

        if ($usingPriceFilter || $usingPriceSort) {
            abort_unless(
                $priceUnit !== null,
                422,
                'price_unit is required for price filters and sorting.',
            );
        }

        if (
            isset($filters['min_price'], $filters['max_price'])
            && $filters['max_price'] < $filters['min_price']
        ) {
            abort(
                422,
                'max_price must be greater than or equal to min_price.',
            );
        }

        if ($priceUnit !== null) {
            $query->where('price_unit', $priceUnit);
        }

        if (isset($filters['min_price'])) {
            $query->where(
                'price_from_minor',
                '>=',
                $filters['min_price'],
            );
        }

        if (isset($filters['max_price'])) {
            $query->where(
                'price_from_minor',
                '<=',
                $filters['max_price'],
            );
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price_from_minor')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_from_minor')->orderByDesc('id'),
            default => $query->latest('id'),
        };

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = Paginator::resolveCurrentPage();
        $offset = min($page - 1, intdiv(PHP_INT_MAX, $perPage)) * $perPage;

        if ($offset >= self::DEEP_PAGE_MIN_OFFSET) {
            [$listings, $total] = $this->deepPage($query, $offset, $perPage, $sort);
        } else {
            $result = $query->paginate($perPage, page: $page);
            $listings = $result->getCollection();
            $total = $result->total();
        }

        if (($filters['include'] ?? null) === 'tutor_card') {
            $ratings = PublicTutorReviewController::visibleReviews($listings->pluck('tutor_profile_id')->unique()->all())
                ->selectRaw('reviews.tutor_profile_id, COUNT(*) AS total, AVG(reviews.rating) AS average')
                ->groupBy('reviews.tutor_profile_id')->get()->keyBy('tutor_profile_id');
            foreach ($listings as $listing) {
                $rating = $ratings->get($listing->tutor_profile_id);
                $listing->setAttribute('tutor_card', [
                    'headline' => $listing->tutorProfile?->headline,
                    'experience_years' => (int) ($listing->tutorProfile?->experience_years ?? 0),
                    'rating_count' => (int) ($rating->total ?? 0),
                    'rating_average' => $rating ? round((float) $rating->average, 2) : null,
                ]);
            }
        }

        return response()->json([
            'data' => ListingResource::collection($listings),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * @param  Builder<ServiceListing>  $query
     * @return array{Collection<int, ServiceListing>, int}
     */
    private function deepPage(Builder $query, int $offset, int $perPage, string $sort): array
    {
        $matching = (clone $query)->reorder()->select('service_listings.id');
        if ($sort !== 'newest') {
            $matching->addSelect('service_listings.price_from_minor');
        }

        $order = match ($sort) {
            'price_asc' => 'page.price_from_minor ASC, page.id ASC',
            'price_desc' => 'page.price_from_minor DESC, page.id DESC',
            default => 'page.id DESC',
        };
        $matchingSql = $matching->toSql();
        // The count and the page share a narrow scan. The count row also covers empty pages.
        $rows = $matching->getQuery()->getConnection()->select(<<<SQL
            WITH matching AS MATERIALIZED ({$matchingSql}),
            page AS (
                SELECT * FROM matching AS page ORDER BY {$order} LIMIT ? OFFSET ?
            )
            SELECT total.match_count, service_listings.*
            FROM (SELECT count(*) AS match_count FROM matching) AS total
            LEFT JOIN page ON true
            LEFT JOIN service_listings ON service_listings.id = page.id
            ORDER BY {$order}
            SQL, [...$matching->getBindings(), $perPage, $offset]);

        $total = (int) $rows[0]->match_count;
        $attributes = [];
        foreach ($rows as $row) {
            if ($row->id !== null) {
                unset($row->match_count);
                $attributes[] = (array) $row;
            }
        }
        $listings = $query->hydrate($attributes);
        $listings->load($query->getEagerLoads());

        return [$listings, $total];
    }

    #[OpenApiResponse(status: 404, description: 'The Listing is not found or no longer publicly visible.', type: 'array{code:string,message:string,request_id:string,errors:array<string,mixed>}')]
    public function show(string $slug): ListingResource
    {
        $listing = ServiceListing::query()
            ->with(['tutorProfile:id,user_id,public_slug', 'tutorProfile.user:id,full_name', 'specialization:id,subcategory_id,name', 'specialization.subcategory:id,category_id,name', 'specialization.subcategory.category:id,name'])
            ->visiblePublic()
            ->where('slug', $slug)
            ->firstOrFail();

        return new ListingResource($listing);
    }

    public function store(CreateListingRequest $request): JsonResponse
    {
        $profile = $request->user()->tutorProfile;

        $listing = DB::transaction(function () use ($request, $profile): ServiceListing {
            $data = $request->validated();

            $listing = ServiceListing::create([
                ...$data,
                'tutor_profile_id' => $profile->id,
                'status' => 'draft',
                'currency' => strtoupper($data['currency']),
                'slug' => 'pending-'.Str::uuid(),
            ]);

            $listing->update([
                'slug' => Str::slug($listing->title).'-'.$listing->id,
            ]);

            return $listing;
        });

        $listing->load([
            'tutorProfile.user',
            'specialization.subcategory.category',
        ]);

        return (new ListingResource($listing))
            ->response()
            ->setStatusCode(201);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'draft', 'active', 'inactive', 'cancelled'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $owned = ServiceListing::query()->where('tutor_profile_id', $profile->id);
        $counts = (clone $owned)->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');
        $statusCounts = ['all' => (int) $counts->sum()];
        foreach (['draft', 'active', 'inactive', 'cancelled'] as $status) {
            $statusCounts[$status] = (int) ($counts[$status] ?? 0);
        }

        $listings = $owned
            ->with([
                'tutorProfile.user',
                'specialization.subcategory.category',
            ])
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => ListingResource::collection($listings->items()),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'status_counts' => $statusCounts,
                'allowed_actions' => ['create'],
            ],
        ]);
    }

    public function myShow(Request $request, ServiceListing $listing): ListingResource
    {
        abort_unless(
            $listing->tutor_profile_id === $this->profile($request)->id,
            404,
        );

        $listing->load([
            'tutorProfile.user',
            'specialization.subcategory.category',
        ]);

        return new ListingResource($listing);
    }

    public function update(
        UpdateListingRequest $request,
        ServiceListing $listing,
    ): ListingResource {
        abort_unless($listing->tutor_profile_id === $this->profile($request)->id, 404);

        if ($listing->status === 'cancelled') {
            abort(409, 'Cancelled listings cannot be edited.');
        }

        $data = $request->validated();
        $requestedStatus = $data['status'] ?? null;
        unset($data['status']);

        abort_if(
            $requestedStatus === 'inactive' && ! in_array($listing->status, ['active', 'inactive'], true),
            409,
            'Only active listings can be paused.',
        );

        $specializationId = $data['specialization_id']
            ?? $listing->specialization_id;

        if ($requestedStatus === 'active') {
            abort_unless(
                $request->user()->tutorProfile?->status === 'approved'
                && ! $request->user()->tutorProfile?->suspended_at,
                403,
                'Tutor is not eligible to publish listings.',
            );

            $validSpecialization = Specialization::query()
                ->whereKey($specializationId)
                ->where('status', 'active')
                ->whereHas(
                    'subcategory',
                    fn ($subcategory) => $subcategory->where('status', 'active')
                        ->whereHas(
                            'category',
                            fn ($category) => $category->where('status', 'active')
                        )
                )
                ->exists();

            abort_unless(
                $validSpecialization,
                422,
                'Cannot publish listing because its specialization, subcategory, or category is inactive or invalid.',
            );
        }

        if ($requestedStatus !== null) {
            $listing->status = $requestedStatus;
        }

        $listing->fill($data);
        $listing->save();

        $listing->refresh()->load([
            'tutorProfile.user',
            'specialization.subcategory.category',
        ]);

        return new ListingResource($listing);

    }

    public function destroy(Request $request, ServiceListing $listing): JsonResponse
    {
        abort_unless($listing->tutor_profile_id === $this->profile($request)->id, 404);
        abort_if($listing->status === 'cancelled', 409, 'Listing is already cancelled.');

        $listing->update(['status' => 'cancelled']);

        return response()->json(status: 204);
    }

    private function profile(Request $request)
    {
        $profile = $request->user()->tutorProfile;

        abort_unless(
            $request->user()->role === 'tutor'
            && $request->user()->status === 'active'
            && $request->user()->hasVerifiedEmail()
            && $profile
            && $profile->status === 'approved'
            && ! $profile->suspended_at,
            403,
            'Tutor is not eligible to manage listings.',
        );

        return $profile;
    }
}
