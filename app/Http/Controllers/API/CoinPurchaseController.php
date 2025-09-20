<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\CoinPurchase;
use App\Models\CoinPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Coin Purchases",
 *     description="Jeton satın alma işlemleri"
 * )
 */
class CoinPurchaseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/coin-purchases",
     *     summary="Jeton satın alma geçmişi",
     *     description="Kullanıcının jeton satın alma geçmişini listeler",
     *     tags={"Coin Purchases"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Satın alma durumu",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","completed","failed","cancelled","refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Sayfa başına kayıt sayısı",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Satın alma geçmişi listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');

        $query = CoinPurchase::where('user_id', Auth::id())
            ->with(['coinPackage', 'payment']);

        if ($status) {
            $query->where('status', $status);
        }

        $purchases = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/coin-purchases/{coinPurchase}",
     *     summary="Jeton satın alma detayı",
     *     description="Belirli bir jeton satın alma işleminin detayını getirir",
     *     tags={"Coin Purchases"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="coinPurchase",
     *         in="path",
     *         description="Satın alma ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Satın alma detayı getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Bu satın alma kaydına erişim yetkiniz yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu satın alma kaydına erişim yetkiniz yok")
     *         )
     *     )
     * )
     */
    public function show(CoinPurchase $coinPurchase): JsonResponse
    {
        if ($coinPurchase->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu satın alma kaydına erişim yetkiniz yok.'
            ], 403);
        }

        $coinPurchase->load(['coinPackage', 'payment']);

        return response()->json([
            'success' => true,
            'data' => $coinPurchase
        ]);
    }

    /**
     * Toplam satın alınan jetonlar
     */
    public function totalPurchased(): JsonResponse
    {
        $totalCoins = CoinPurchase::where('user_id', Auth::id())
            ->completed()
            ->sum('coin_amount');

        $totalBonusCoins = CoinPurchase::where('user_id', Auth::id())
            ->completed()
            ->sum('bonus_coins');

        $totalSpent = CoinPurchase::where('user_id', Auth::id())
            ->completed()
            ->sum('price');

        $totalPurchases = CoinPurchase::where('user_id', Auth::id())
            ->completed()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_coins' => $totalCoins,
                'total_bonus_coins' => $totalBonusCoins,
                'total_coins_with_bonus' => $totalCoins + $totalBonusCoins,
                'total_spent' => $totalSpent,
                'total_purchases' => $totalPurchases
            ]
        ]);
    }

    /**
     * Aylık satın alma istatistikleri
     */
    public function monthlyStats(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('n'));

        $purchases = CoinPurchase::where('user_id', Auth::id())
            ->completed()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('coinPackage')
            ->get();

        $stats = [
            'month' => $month,
            'year' => $year,
            'total_purchases' => $purchases->count(),
            'total_coins' => $purchases->sum('coin_amount'),
            'total_bonus_coins' => $purchases->sum('bonus_coins'),
            'total_spent' => $purchases->sum('price'),
            'average_purchase_value' => $purchases->avg('price'),
            'most_popular_package' => $purchases->groupBy('coin_package_id')
                ->map->count()
                ->sortDesc()
                ->first(),
            'purchases_by_day' => $purchases->groupBy(function($purchase) {
                return $purchase->created_at->format('Y-m-d');
            })->map->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Satın alma iade talebi
     */
    public function requestRefund(Request $request, CoinPurchase $coinPurchase): JsonResponse
    {
        if ($coinPurchase->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu satın alma kaydına erişim yetkiniz yok.'
            ], 403);
        }

        if ($coinPurchase->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Sadece tamamlanmış satın almalar iade edilebilir.'
            ], 400);
        }

        // 24 saat içinde mi kontrol et
        if ($coinPurchase->completed_at->diffInHours(now()) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'İade talebi 24 saat içinde yapılmalıdır.'
            ], 400);
        }

        // İade işlemi burada implement edilecek
        // Şimdilik sadece status güncelleniyor
        $coinPurchase->update([
            'status' => 'refund_requested'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'İade talebi alındı. En kısa sürede işleme alınacaktır.',
            'data' => $coinPurchase
        ]);
    }

    /**
     * Satın alma iptal et (sadece pending durumunda)
     */
    public function cancel(CoinPurchase $coinPurchase): JsonResponse
    {
        if ($coinPurchase->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu satın alma kaydına erişim yetkiniz yok.'
            ], 403);
        }

        if ($coinPurchase->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Sadece bekleyen satın almalar iptal edilebilir.'
            ], 400);
        }

        $coinPurchase->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Satın alma iptal edildi.',
            'data' => $coinPurchase
        ]);
    }
}