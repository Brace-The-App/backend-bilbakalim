<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DiamondPackage;
use App\Models\JokerPackage;
use App\Models\PremiumPackage;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Store Packages",
 *     description="Mağaza paketleri (premium, joker, elmas)"
 * )
 */
class StorePackageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/store-packages/premium",
     *     summary="Premium paketleri listele",
     *     tags={"Store Packages"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Premium paket listesi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function premiumPackages(): JsonResponse
    {
        $items = PremiumPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/store-packages/joker",
     *     summary="Joker paketlerini listele",
     *     tags={"Store Packages"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Joker paket listesi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function jokerPackages(): JsonResponse
    {
        $items = JokerPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/store-packages/diamond",
     *     summary="Elmas paketlerini listele",
     *     tags={"Store Packages"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Elmas paket listesi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function diamondPackages(): JsonResponse
    {
        $items = DiamondPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
