<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\ServiceListing;
use App\Models\Specialization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
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
                'tutorProfile.user',
                'specialization.subcategory.category',
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
            'price_asc' => $query->orderBy('price_from_minor'),
            'price_desc' => $query->orderByDesc('price_from_minor'),
            default => $query->latest('id'),
        };

        $perPage = $filters['per_page'] ?? 20;
        $listings = $query->paginate($perPage);

        return response()->json([
            'data' => ListingResource::collection($listings->items()),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
            ],
        ]);
    }

    public function show(string $slug): ListingResource
    {
        $listing = ServiceListing::query()
            ->with(['tutorProfile.user', 'specialization.subcategory.category'])
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
