<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
     *     description="Kullanıcı reklam izledikten sonra +1 coin kazanır",
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
     *             @OA\Property(property="message", type="string", example="Reklam izleme ödülü başarıyla verildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="coins_earned", type="integer", example=1),
     *                 @OA\Property(property="balance_before", type="integer", example=10),
     *                 @OA\Property(property="balance_after", type="integer", example=11),
     *                 @OA\Property(property="user_coins", type="integer", example=11)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata - Zaten bugün reklam izlenmiş olabilir veya başka bir hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reklam izleme ödülü verilemedi.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
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
                    'message' => 'Kullanıcı bulunamadı.'
                ], 401);
            }

            DB::beginTransaction();

            // Kullanıcının mevcut coin bakiyesini al
            $balanceBefore = $user->coins;

            // +1 coin ekle
            $coinReward = 1;
            $user->increment('coins', $coinReward);

            // Güncel bakiyeyi al
            $balanceAfter = $user->fresh()->coins;

            // Coin geçmişini coin_history tablosuna kaydet
            $user->coinHistory()->create([
                'coin_amount' => $coinReward,
                'transaction_type' => 'earned',
                'status' => 'completed',
                'description' => 'Reklam izleme ödülü',
                'metadata' => [
                    'ad_id' => $request->input('ad_id'),
                    'ad_type' => $request->input('ad_type'),
                    'reward_type' => 'ad_watch',
                    'coins_earned' => $coinReward
                ],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reklam izleme ödülü başarıyla verildi.',
                'data' => [
                    'coins_earned' => $coinReward,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'user_coins' => $balanceAfter
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ad watch reward error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Reklam izleme ödülü verilemedi.'
            ], 400);
        }
    }
}
