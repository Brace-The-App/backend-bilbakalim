<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\FriendInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Friend Invites",
 *     description="Arkadaş davet işlemleri"
 * )
 */
class FriendInviteController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/friend-invites/create",
     *     summary="Davet linki oluştur",
     *     description="Yeni bir arkadaş davet linki oluşturur",
     *     tags={"Friend Invites"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="phone_number", type="string", description="Davet edilecek telefon numarası"),
     *                 @OA\Property(property="email", type="string", description="Davet edilecek email adresi"),
     *                 @OA\Property(property="reward_coins", type="integer", minimum=0, description="Ödül jeton miktarı"),
     *                 @OA\Property(property="bonus_coins", type="integer", minimum=0, description="Bonus jeton miktarı"),
     *                 @OA\Property(property="expires_in_hours", type="integer", minimum=1, maximum=168, description="Süre (saat)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Davet linki oluşturuldu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Davet linki başarıyla oluşturuldu"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="invite_code", type="string", example="ABC12345"),
     *                 @OA\Property(property="invite_link", type="string", example="https://app.com/invite/ABC12345"),
     *                 @OA\Property(property="expires_at", type="string", example="2024-01-01 23:59:59"),
     *                 @OA\Property(property="reward_coins", type="integer", example=100)
     *             )
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'reward_coins' => 'integer|min:0|max:1000',
            'bonus_coins' => 'integer|min:0|max:500',
            'expires_in_hours' => 'integer|min:1|max:168'
        ]);

        if (!$request->phone_number && !$request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Telefon numarası veya email adresi gerekli.'
            ], 400);
        }

        try {
            $inviteCode = FriendInvite::generateInviteCode();
            $inviteLink = FriendInvite::generateInviteLink($inviteCode);
            $expiresAt = now()->addHours($request->expires_in_hours ?? 24);

            $invite = FriendInvite::create([
                'inviter_id' => Auth::id(),
                'invite_code' => $inviteCode,
                'invite_link' => $inviteLink,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'reward_coins' => $request->reward_coins ?? 50,
                'bonus_coins' => $request->bonus_coins ?? 25,
                'expires_at' => $expiresAt,
                'metadata' => [
                    'created_via' => 'mobile_app',
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Davet linki başarıyla oluşturuldu.',
                'data' => [
                    'invite_code' => $invite->invite_code,
                    'invite_link' => $invite->invite_link,
                    'expires_at' => $invite->expires_at->format('Y-m-d H:i:s'),
                    'reward_coins' => $invite->reward_coins,
                    'bonus_coins' => $invite->bonus_coins
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Davet linki oluşturulurken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myInvites(Request $request): JsonResponse
    {
        $query = FriendInvite::byInviter(Auth::id())->with('invited');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $invites = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $invites
        ]);
    }

    public function accept($inviteCode): JsonResponse
    {
        $invite = FriendInvite::byCode($inviteCode)->first();

        if (!$invite || !$invite->is_valid) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz veya süresi dolmuş davet kodu.'
            ], 400);
        }

        if ($invite->inviter_id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kendi davetinizi kabul edemezsiniz.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $invite->accept(Auth::id());

            $user = Auth::user();
            $totalCoins = $invite->reward_coins + $invite->bonus_coins;
            
            $user->increment('total_coins', $totalCoins);

            $inviter = $invite->inviter;
            $inviter->increment('total_coins', $invite->reward_coins);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Davet başarıyla kabul edildi.',
                'data' => [
                    'reward_coins' => $invite->reward_coins,
                    'bonus_coins' => $invite->bonus_coins,
                    'total_coins_earned' => $totalCoins
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Davet kabul edilirken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        $userId = Auth::id();
        
        $totalInvites = FriendInvite::byInviter($userId)->count();
        $acceptedInvites = FriendInvite::byInviter($userId)->accepted()->count();
        $pendingInvites = FriendInvite::byInviter($userId)->pending()->count();
        
        $totalCoinsEarned = FriendInvite::byInviter($userId)
            ->accepted()
            ->sum('reward_coins');
        
        $successRate = $totalInvites > 0 ? ($acceptedInvites / $totalInvites) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_invites' => $totalInvites,
                'accepted_invites' => $acceptedInvites,
                'pending_invites' => $pendingInvites,
                'total_coins_earned' => $totalCoinsEarned,
                'success_rate' => round($successRate, 2)
            ]
        ]);
    }
}