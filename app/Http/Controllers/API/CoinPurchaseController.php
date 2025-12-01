<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\CoinPurchase;
use App\Models\CoinPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            ->with(['coinPackage']);

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

        $coinPurchase->load(['coinPackage']);

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

    /**
     * @OA\Post(
     *     path="/api/coin-purchases/purchase",
     *     summary="Jeton Satın Al",
     *     description="Mobil ödeme yapıldıktan sonra jeton satın alma işlemini tamamlar.",
     *     tags={"Coin Purchases"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="package_id", type="integer", example=1, description="Paket ID'si"),
     *                 @OA\Property(property="payment_id", type="string", example="payment_123", description="Mobil ödeme ID'si"),
     *                 @OA\Property(property="payment_provider", type="string", enum={"google", "apple"}, example="google", description="Ödeme sağlayıcısı")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="package_id", type="integer", example=1),
     *             @OA\Property(property="payment_id", type="string", example="payment_123"),
     *             @OA\Property(property="payment_provider", type="string", enum={"google", "apple"}, example="google")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jeton başarıyla yüklendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Jeton başarıyla yüklendi."),
     *             @OA\Property(property="coin_amount", type="integer", example=100),
     *             @OA\Property(property="bonus_coins", type="integer", example=10),
     *             @OA\Property(property="total_coins", type="integer", example=110),
     *             @OA\Property(property="new_balance", type="integer", example=1110),
     *             @OA\Property(property="purchase", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu paket aktif değil.")
     *         )
     *     )
     * )
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|exists:coin_packages,id',
            'payment_id' => 'required|string',
            'payment_provider' => 'required|in:google,apple',
        ]);

        $user = Auth::user();
        $package = CoinPackage::findOrFail($request->package_id);

        if (!$package->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bu paket aktif değil.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $totalCoins = $package->coin_amount + ($package->bonus_coins ?? 0);
            
            // CoinPurchase kaydı oluştur
            $coinPurchase = CoinPurchase::create([
                'user_id' => $user->id,
                'coin_package_id' => $package->id,
                'payment_id' => $request->payment_id,
                'coin_amount' => $package->coin_amount,
                'bonus_coins' => $package->bonus_coins ?? 0,
                'price' => $package->price,
                'currency' => $package->currency ?? 'TRY',
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Kullanıcının coin bakiyesine ekle
            $user->increment('coins', $totalCoins);
            $user->increment('total_coins', $totalCoins);

            Log::info('Coin purchase completed', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'coin_purchase_id' => $coinPurchase->id,
                'coin_amount' => $package->coin_amount,
                'bonus_coins' => $package->bonus_coins ?? 0,
                'total_coins' => $totalCoins,
                'payment_id' => $request->payment_id,
                'payment_provider' => $request->payment_provider,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Jeton başarıyla yüklendi.',
                'coin_amount' => $package->coin_amount,
                'bonus_coins' => $package->bonus_coins ?? 0,
                'total_coins' => $totalCoins,
                'new_balance' => $user->fresh()->coins,
                'purchase' => $coinPurchase
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Coin purchase error', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Jeton yüklenirken bir hata oluştu.'
            ], 500);
        }
    }
}