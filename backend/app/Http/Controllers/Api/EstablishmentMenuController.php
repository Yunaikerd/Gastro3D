<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstablishmentMenuResource;
use App\Http\Resources\MenuProductDetailResource;
use App\Models\Establishment;
use App\Models\MenuProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EstablishmentMenuController extends Controller
{
    /**
     * Retrieve the full digital menu for a public establishment by slug.
     */
    public function menu(Request $request, string $slug): JsonResponse
    {
        $normalizedSlug = strtolower(trim($slug));

        $establishment = Establishment::where('slug', $normalizedSlug)
            ->where('is_active', true)
            ->first();

        if (!$establishment) {
            return response()->json([
                'success' => false,
                'message' => 'Establecimiento no encontrado o no disponible.',
            ], 404);
        }

        $establishment->load([
            'activeCategories' => function ($categoryQuery) {
                $categoryQuery->orderBy('sort_order', 'asc');
            },
            'activeCategories.availableProducts' => function ($productQuery) {
                $productQuery->orderBy('sort_order', 'asc')->with('dishModel');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => new EstablishmentMenuResource($establishment),
        ], 200);
    }

    /**
     * Retrieve detailed product information for 3D/AR modal viewing.
     */
    public function productDetail(Request $request, string $slug, string $productSlug): JsonResponse
    {
        $normalizedSlug = strtolower(trim($slug));

        $establishment = Establishment::where('slug', $normalizedSlug)
            ->where('is_active', true)
            ->first();

        if (!$establishment) {
            return response()->json([
                'success' => false,
                'message' => 'Establecimiento no encontrado o no disponible.',
            ], 404);
        }

        $isUuid = Str::isUuid($productSlug);

        $baseQuery = MenuProduct::where('establishment_id', $establishment->id)
            ->where('is_available', true)
            ->with(['category', 'dishModel', 'establishment']);

        if ($isUuid) {
            $product = $baseQuery->where('id', $productSlug)->first();
        } else {
            $normalizedProductSlug = strtolower(trim($productSlug));
            $product = (clone $baseQuery)->where('slug', $normalizedProductSlug)->first();

            // Fallback for legacy meta->slug if different
            if (!$product) {
                $product = (clone $baseQuery)->where('meta->slug', $normalizedProductSlug)->first();
            }
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado o no disponible.',
            ], 404);
        }

        // Multi-tenant check: if product's category is inactive, do not expose it
        if ($product->category && !$product->category->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no disponible actualmente.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new MenuProductDetailResource($product),
        ], 200);
    }
}
