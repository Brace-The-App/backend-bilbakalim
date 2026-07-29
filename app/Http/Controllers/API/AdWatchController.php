<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
     *     description="Reklam izlendikten sonra +1 coin verir ve 24 saatlik 3 hak kotasından 1 düşer.",
     *     tags={"Ad Watch"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="ad_id", type="string", description="Reklam ID (opsiyonel)", example="ad_12345"),
     *             @OA\Property(property="ad_type", type="string", description="Reklam tipi (opsiyonel)", example="interstitial")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı - Coin eklendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reklam izleme ödülü başarıyla verildi.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hak yok veya hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reklam hakkınız doldu.")
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

                $balanceBefore = (int) $user->coins;
                $coinReward = 1;
                $user->increment('coins', $coinReward);
                $balanceAfter = (int) $user->fresh()->coins;

                $user->coinHistory()->create([
                    'coin_amount' => $coinReward,
                    'transaction_type' => 'earned',
                    'status' => 'completed',
                    'description' => 'Reklam izleme ödülü',
                    'metadata' => [
                        'ad_id' => $request->input('ad_id'),
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
