<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\CoinPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Coin Packages",
 *     description="Jeton paket işlemleri"
 * )
 */
class CoinPackageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/coin-packages",
     *     summary="Jeton paketlerini listele",
     *     description="Mevcut jeton paketlerini listeler",
     *     tags={"Coin Packages"},
     *     @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         description="Para birimi",
     *         required=false,
     *         @OA\Schema(type="string", example="TRY")
     *     ),
     *     @OA\Parameter(
     *         name="show_inactive",
     *         in="query",
     *         description="Pasif paketleri göster",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jeton paketleri listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $currency = $request->get('currency', 'TRY');
        $showInactive = $request->get('show_inactive', false);

        $query = CoinPackage::query();

        if (!$showInactive) {
            $query->active();
        }

        $query->byCurrency($currency)->ordered();

        $packages = $query->get();

        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }

    /**
     * Jeton paketi detayı
     */
    public function show(CoinPackage $coinPackage): JsonResponse
    {
        if (!$coinPackage->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Jeton paketi bulunamadı.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $coinPackage
        ]);
    }

    /**
     * Popüler jeton paketleri
     */
    public function popular(Request $request): JsonResponse
    {
        $currency = $request->get('currency', 'TRY');

        $packages = CoinPackage::active()
            ->popular()
            ->byCurrency($currency)
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }

    /**
     * Jeton paketi oluştur (Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'coin_amount' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'bonus_coins' => 'integer|min:0',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'color_code' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0'
        ]);

        $package = CoinPackage::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Jeton paketi oluşturuldu.',
            'data' => $package
        ], 201);
    }

    /**
     * Jeton paketi güncelle (Admin)
     */
    public function update(Request $request, CoinPackage $coinPackage): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'coin_amount' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'bonus_coins' => 'integer|min:0',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'color_code' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0'
        ]);

        $coinPackage->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Jeton paketi güncellendi.',
            'data' => $coinPackage
        ]);
    }

    /**
     * Jeton paketi sil (Admin)
     */
    public function destroy(CoinPackage $coinPackage): JsonResponse
    {
        // Aktif satın almalar var mı kontrol et
        if ($coinPackage->coinPurchases()->where('status', 'completed')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu paket daha önce satın alındığı için silinemez.'
            ], 400);
        }

        $coinPackage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jeton paketi silindi.'
        ]);
    }

    /**
     * Jeton paketi istatistikleri (Admin)
     */
    public function stats(CoinPackage $coinPackage): JsonResponse
    {
        $stats = [
            'total_purchases' => $coinPackage->coinPurchases()->count(),
            'completed_purchases' => $coinPackage->coinPurchases()->completed()->count(),
            'total_revenue' => $coinPackage->coinPurchases()->completed()->sum('price'),
            'total_coins_sold' => $coinPackage->coinPurchases()->completed()->sum('coin_amount'),
            'total_bonus_coins_given' => $coinPackage->coinPurchases()->completed()->sum('bonus_coins'),
            'average_purchase_value' => $coinPackage->coinPurchases()->completed()->avg('price'),
            'purchase_trend' => $coinPackage->coinPurchases()
                ->completed()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}