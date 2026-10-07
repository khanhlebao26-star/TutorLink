<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Specialization;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->where('status', 'active')
                ->with(['subcategories' => fn ($query) => $query
                    ->where('status', 'active')
                    ->with(['specializations' => fn ($specializations) => $specializations
                        ->where('status', 'active')
                        ->orderBy('name')])
                    ->orderBy('name')])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function specializations(): JsonResponse
    {
        return response()->json([
            'data' => Specialization::query()
                ->where('status', 'active')
                ->with('subcategory.category')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
