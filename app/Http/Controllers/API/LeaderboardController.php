<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoinHistory;
use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/leaderboard/daily",
     *     summary="Günün kazananları",
     *     description="Bugün en çok coin kazanan 5 kişiyi listeler",
     *     tags={"Leaderboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="rank", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Samet Çakır"),
     *                     @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                     @OA\Property(property="coins_earned", type="integer", example=1645),
     *                     @OA\Property(property="date", type="string", example="2025-01-06")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function daily(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // Bugün kazanılan coin'leri hesapla
        $dailyWinners = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
            ->where('coin_amount', '>', 0)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today, $tomorrow])
            ->groupBy('user_id')
            ->orderBy('total_coins', 'desc')
            ->limit(5)
            ->get();

        $result = $dailyWinners->map(function ($item, $index) use ($today) {
            $user = User::with('avatarModel')->find($item->user_id);
            $avatarUrl = $this->getUserAvatarUrl($user);
            return [
                'rank' => $index + 1,
                'user_id' => $item->user_id,
                'name' => $user ? trim($user->name . ' ' . $user->surname) : 'Bilinmeyen',
                'avatar' => $avatarUrl,
                'coins_earned' => (int) $item->total_coins,
                'date' => $today->format('Y-m-d')
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/leaderboard/weekly",
     *     summary="Haftanın kazananları",
     *     description="Bu hafta en çok coin kazanan 5 kişiyi listeler",
     *     tags={"Leaderboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="rank", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Samet Çakır"),
     *                     @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                     @OA\Property(property="coins_earned", type="integer", example=1645),
     *                     @OA\Property(property="week_start", type="string", example="2025-01-06"),
     *                     @OA\Property(property="week_end", type="string", example="2025-01-12")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function weekly(Request $request): JsonResponse
    {
        // Bu haftanın başlangıcı (Pazartesi)
        $weekStart = Carbon::now()->startOfWeek();
        // Bu haftanın sonu (Pazar)
        $weekEnd = Carbon::now()->endOfWeek();

        // Bu hafta kazanılan coin'leri hesapla
        $weeklyWinners = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
            ->where('coin_amount', '>', 0)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('user_id')
            ->orderBy('total_coins', 'desc')
            ->limit(5)
            ->get();

        $result = $weeklyWinners->map(function ($item, $index) use ($weekStart, $weekEnd) {
            $user = User::with('avatarModel')->find($item->user_id);
            $avatarUrl = $this->getUserAvatarUrl($user);
            return [
                'rank' => $index + 1,
                'user_id' => $item->user_id,
                'name' => $user ? trim($user->name . ' ' . $user->surname) : 'Bilinmeyen',
                'avatar' => $avatarUrl,
                'coins_earned' => (int) $item->total_coins,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d')
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/leaderboard/all-time",
     *     operationId="leaderboardAllTime",
     *     summary="Tüm zamanlar — en yüksek jeton",
     *     description="Tüm zaman boyunca en yüksek coin bakiyesine sahip kullanıcıları listeler (yüksekten düşüğe). Response modeli daily/weekly ile aynıdır. limit varsayılan 10.",
     *     tags={"Leaderboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Kaç kişi döneceği (varsayılan 10)",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı — data içinde sıralı liste",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="rank", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=15),
     *                     @OA\Property(property="name", type="string", example="muhammed kayacan"),
     *                     @OA\Property(property="avatar", type="string", nullable=true, example="https://bil-bakalim.com/storage/profile_images/avatars/example.png"),
     *                     @OA\Property(property="coins_earned", type="integer", example=535000, description="Kullanıcının mevcut coin bakiyesi")
     *                 ),
     *                 example={
     *                     {"rank": 1, "user_id": 15, "name": "muhammed kayacan", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/velVtDSIIzT5twBoln5fntTWTeZ4Wea8N1MpTOtu.png", "coins_earned": 535000},
     *                     {"rank": 2, "user_id": 48, "name": "Ahmet Özberk", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/rmovT36XGOBjVbgqyNKtlXS7PNm7Q9EbataBamzQ.png", "coins_earned": 148849},
     *                     {"rank": 3, "user_id": 18, "name": "John", "avatar": "https://bil-bakalim.com/storage/profile_images/ZeBwT6QW6Yd2rvfClGxAYAbdPge4TEwM7pSScPN7.jpg", "coins_earned": 123215},
     *                     {"rank": 4, "user_id": 17, "name": "John", "avatar": null, "coins_earned": 122876},
     *                     {"rank": 5, "user_id": 91, "name": "Afife", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/LMP33kM95G0ORXDrVe0sqJbmRODLEcQxBawtslAR.png", "coins_earned": 42085},
     *                     {"rank": 6, "user_id": 36, "name": "Nagi", "avatar": "https://bil-bakalim.com/storage/profile_images/RgBJadkcENmNQ9u5iPoow24yPUPjb69Ii8cjxJvc.jpg", "coins_earned": 36621},
     *                     {"rank": 7, "user_id": 38, "name": "Ali K", "avatar": null, "coins_earned": 20975},
     *                     {"rank": 8, "user_id": 50, "name": "Furkan ŞAHİN", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/bhPNn3UhDaVCkAugNOQHFL8xIgLTYdN2zZlHjiBM.png", "coins_earned": 5077},
     *                     {"rank": 9, "user_id": 1, "name": "Admin", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/4TE74Yln6EvfDwC7zlWk021MxeOhCHP3FgVMsaFY.png", "coins_earned": 4035},
     *                     {"rank": 10, "user_id": 93, "name": "aslı", "avatar": null, "coins_earned": 3700}
     *                 }
     *             ),
     *             example={
     *                 "success": true,
     *                 "data": {
     *                     {"rank": 1, "user_id": 15, "name": "muhammed kayacan", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/velVtDSIIzT5twBoln5fntTWTeZ4Wea8N1MpTOtu.png", "coins_earned": 535000},
     *                     {"rank": 2, "user_id": 48, "name": "Ahmet Özberk", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/rmovT36XGOBjVbgqyNKtlXS7PNm7Q9EbataBamzQ.png", "coins_earned": 148849},
     *                     {"rank": 3, "user_id": 18, "name": "John", "avatar": "https://bil-bakalim.com/storage/profile_images/ZeBwT6QW6Yd2rvfClGxAYAbdPge4TEwM7pSScPN7.jpg", "coins_earned": 123215},
     *                     {"rank": 4, "user_id": 17, "name": "John", "avatar": null, "coins_earned": 122876},
     *                     {"rank": 5, "user_id": 91, "name": "Afife", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/LMP33kM95G0ORXDrVe0sqJbmRODLEcQxBawtslAR.png", "coins_earned": 42085},
     *                     {"rank": 6, "user_id": 36, "name": "Nagi", "avatar": "https://bil-bakalim.com/storage/profile_images/RgBJadkcENmNQ9u5iPoow24yPUPjb69Ii8cjxJvc.jpg", "coins_earned": 36621},
     *                     {"rank": 7, "user_id": 38, "name": "Ali K", "avatar": null, "coins_earned": 20975},
     *                     {"rank": 8, "user_id": 50, "name": "Furkan ŞAHİN", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/bhPNn3UhDaVCkAugNOQHFL8xIgLTYdN2zZlHjiBM.png", "coins_earned": 5077},
     *                     {"rank": 9, "user_id": 1, "name": "Admin", "avatar": "https://bil-bakalim.com/storage/profile_images/avatars/4TE74Yln6EvfDwC7zlWk021MxeOhCHP3FgVMsaFY.png", "coins_earned": 4035},
     *                     {"rank": 10, "user_id": 93, "name": "aslı", "avatar": null, "coins_earned": 3700}
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function allTime(Request $request): JsonResponse
    {
        $limit = max(1, (int) $request->input('limit', 10));

        $users = User::with('avatarModel')
            ->orderByDesc('coins')
            ->limit($limit)
            ->get();

        $result = $users->values()->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'name' => trim($user->name . ' ' . $user->surname) ?: 'Bilinmeyen',
                'avatar' => $this->getUserAvatarUrl($user),
                'coins_earned' => (int) $user->coins,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/leaderboard",
     *     summary="Tüm leaderboard'lar",
     *     description="Günün, haftanın ve son turnuvanın kazananlarını tek bir response'da döndürür",
     *     tags={"Leaderboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="daily",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="rank", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Samet Çakır"),
     *                         @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                         @OA\Property(property="coins_earned", type="integer", example=1645),
     *                         @OA\Property(property="date", type="string", example="2025-01-06")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="weekly",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="rank", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Samet Çakır"),
     *                         @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                         @OA\Property(property="coins_earned", type="integer", example=1645),
     *                         @OA\Property(property="week_start", type="string", example="2025-01-06"),
     *                         @OA\Property(property="week_end", type="string", example="2025-01-12")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="tournament",
     *                     type="object",
     *                     @OA\Property(property="tournament_id", type="integer", example=1),
     *                     @OA\Property(property="tournament_title", type="string", example="Haftalık Turnuva"),
     *                     @OA\Property(property="tournament_end_date", type="string", example="2025-01-06 20:00:00"),
     *                     @OA\Property(
     *                         property="winners",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="rank", type="integer", example=1),
     *                             @OA\Property(property="user_id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samet Çakır"),
     *                             @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                             @OA\Property(property="score", type="integer", example=8500),
     *                             @OA\Property(property="correct_answers", type="integer", example=20)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function all(Request $request): JsonResponse
    {
        // Daily leaderboard
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $dailyWinners = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
            ->where('coin_amount', '>', 0)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today, $tomorrow])
            ->groupBy('user_id')
            ->orderBy('total_coins', 'desc')
            ->limit(5)
            ->get();

        $dailyResult = $dailyWinners->map(function ($item, $index) use ($today) {
            $user = User::with('avatarModel')->find($item->user_id);
            $avatarUrl = $this->getUserAvatarUrl($user);
            return [
                'rank' => $index + 1,
                'user_id' => $item->user_id,
                'name' => $user ? trim($user->name . ' ' . $user->surname) : 'Bilinmeyen',
                'avatar' => $avatarUrl,
                'coins_earned' => (int) $item->total_coins,
                'date' => $today->format('Y-m-d')
            ];
        })->values();

        // Weekly leaderboard
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $weeklyWinners = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
            ->where('coin_amount', '>', 0)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('user_id')
            ->orderBy('total_coins', 'desc')
            ->limit(5)
            ->get();

        $weeklyResult = $weeklyWinners->map(function ($item, $index) use ($weekStart, $weekEnd) {
            $user = User::with('avatarModel')->find($item->user_id);
            $avatarUrl = $this->getUserAvatarUrl($user);
            return [
                'rank' => $index + 1,
                'user_id' => $item->user_id,
                'name' => $user ? trim($user->name . ' ' . $user->surname) : 'Bilinmeyen',
                'avatar' => $avatarUrl,
                'coins_earned' => (int) $item->total_coins,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d')
            ];
        })->values();

        // Last tournament leaderboard
        $lastTournament = Tournament::where('status', 'completed')
            ->whereNotNull('end_time')
            ->orderBy('end_time', 'desc')
            ->first();

        $tournamentResult = null;
        if ($lastTournament) {
            $tournamentWinners = TournamentUser::where('tournament_id', $lastTournament->id)
                ->where('status', 'completed')
                ->with('user.avatarModel')
                ->orderBy('rank', 'asc')
                ->limit(5)
                ->get();

            $tournamentWinnersList = $tournamentWinners->map(function ($participant, $index) {
                $user = $participant->user;
                $avatarUrl = $this->getUserAvatarUrl($user);
                return [
                    'rank' => $participant->rank ?? ($index + 1),
                    'user_id' => $participant->user_id,
                    'name' => $user ? trim($user->name . ' ' . $user->surname) : 'Bilinmeyen',
                    'avatar' => $avatarUrl,
                    'score' => (int) $participant->score,
                    'correct_answers' => (int) $participant->correct_answers
                ];
            })->values();

            $tournamentResult = [
                'tournament_id' => $lastTournament->id,
                'tournament_title' => $lastTournament->title,
                'tournament_end_date' => $lastTournament->end_time ? $lastTournament->end_time->format('Y-m-d H:i:s') : null,
                'winners' => $tournamentWinnersList
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $dailyResult,
                'weekly' => $weeklyResult,
                'tournament' => $tournamentResult
            ]
        ]);
    }

    /**
     * Kullanıcının avatar URL'ini al (avatar varsa avatar, yoksa profile_image)
     */
    private function getUserAvatarUrl(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        // Önce avatar kontrol et
        if ($user->avatarModel && $user->avatarModel->image_url) {
            return $user->avatarModel->image_url;
        }

        // Avatar yoksa profile_image kontrol et
        if (!empty($user->profile_image)) {
            $baseUrl = config('app.url', 'https://bilbakalim.online');
            // Eğer zaten tam URL ise, olduğu gibi döndür
            if (filter_var($user->profile_image, FILTER_VALIDATE_URL)) {
                return $user->profile_image;
            }
            // Eğer storage/profile_images/ ile başlıyorsa, sadece profile_images/ kısmını al
            $imagePath = $user->profile_image;
            if (strpos($imagePath, 'storage/profile_images/') !== false) {
                $imagePath = str_replace('storage/profile_images/', 'profile_images/', $imagePath);
            }
            // Eğer profile_images/ ile başlamıyorsa, ekle
            if (strpos($imagePath, 'profile_images/') !== 0) {
                $imagePath = 'profile_images/' . ltrim($imagePath, '/');
            }
            return rtrim($baseUrl, '/') . '/storage/' . $imagePath;
        }

        return null;
    }
}
