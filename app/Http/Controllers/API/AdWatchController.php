<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\UserAdView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdWatchController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/ad-watch/reward",
     *     summary="Reklam izleme ödülü",
     *     description="Görsel veya video reklam izlendikten sonra reklamın reward_coins kadar jeton verir (ad_id sayısal ve kayıtlıysa) ve 24 saatlik 3 hak kotasından 1 düşer. ad_id yoksa varsayılan 1 jeton.",
     *     tags={"Ads"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="ad_id", type="integer", description="GET /api/ads/next içindeki data.id (sayısal)", example=5),
     *             @OA\Property(property="ad_type", type="string", description="İstemci tipi: image | video | interstitial", example="video")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı - jeton eklendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="allowed", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reklam izleme ödülü başarıyla verildi."),
     *             @OA\Property(property="count", type="integer", example=1),
     *             @OA\Property(property="max", type="integer", example=3),
     *             @OA\Property(property="remaining", type="integer", example=2),
     *             @OA\Property(property="resets_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="coins_earned", type="integer", example=5),
     *                 @OA\Property(property="reward_coins", type="integer", example=5),
     *                 @OA\Property(property="ad_id", type="integer", nullable=true, example=5),
     *                 @OA\Property(property="balance_before", type="integer", example=10),
     *                 @OA\Property(property="balance_after", type="integer", example=15),
     *                 @OA\Property(property="user_coins", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hak yok veya hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="allowed", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reklam hakkınız doldu. İlk izlemeden 24 saat sonra yenilenir."),
     *             @OA\Property(property="count", type="integer", example=3),
     *             @OA\Property(property="max", type="integer", example=3),
     *             @OA\Property(property="remaining", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Kimlik doğrulama gerekli",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function reward(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı.',
                ], 401);
            }

            return DB::transaction(function () use ($user, $request) {
                UserAdView::forUser($user->id);
                $view = UserAdView::where('user_id', $user->id)->lockForUpdate()->first();
                $view->resetWindowIfExpired();

                if ($view->isExhausted()) {
                    return response()->json(array_merge([
                        'success' => false,
                        'allowed' => false,
                        'message' => 'Reklam hakkınız doldu. İlk izlemeden 24 saat sonra yenilenir.',
                    ], $view->statusPayload()), 400);
                }

                $view->consumeOne();

                $adIdRaw = $request->input('ad_id');
                $ad = null;
                if ($adIdRaw !== null && $adIdRaw !== '' && is_numeric($adIdRaw)) {
                    $ad = Ad::query()->find((int) $adIdRaw);
                }

                $coinReward = $ad
                    ? $ad->resolvedRewardCoins()
                    : Ad::DEFAULT_REWARD_COINS;

                $balanceBefore = (int) $user->coins;
                $user->increment('coins', $coinReward);
                $balanceAfter = (int) $user->fresh()->coins;

                $user->coinHistory()->create([
                    'coin_amount' => $coinReward,
                    'transaction_type' => 'earned',
                    'status' => 'completed',
                    'description' => 'Reklam izleme ödülü',
                    'metadata' => [
                        'ad_id' => $ad?->id ?? $adIdRaw,
                        'ad_type' => $request->input('ad_type'),
                        'reward_type' => 'ad_watch',
                        'coins_earned' => $coinReward,
                    ],
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ]);

                $view->refresh();

                return response()->json(array_merge([
                    'success' => true,
                    'allowed' => true,
                    'message' => 'Reklam izleme ödülü başarıyla verildi.',
                    'data' => [
                        'coins_earned' => $coinReward,
                        'reward_coins' => $coinReward,
                        'ad_id' => $ad?->id,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'user_coins' => $balanceAfter,
                    ],
                ], $view->statusPayload()));
            });
        } catch (\Exception $e) {
            Log::error('Ad watch reward error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Reklam izleme ödülü verilemedi.',
            ], 400);
        }
    }
}
