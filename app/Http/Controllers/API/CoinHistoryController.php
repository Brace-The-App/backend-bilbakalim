<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoinHistory;
use App\Models\IndividualGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CoinHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/coin-history",
     *     summary="Jeton geçmişi",
     *     description="Kullanıcının kazandığı ve kaybettiği jetonları listeler. Son 30 işlem döner. Son 5, 15, 30 gün filtreleme yapılabilir.",
     *     tags={"Coin History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="İşlem tipi: earned (kazanç), spent (kayıp), all (hepsi)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"earned", "spent", "all"}, default="all")
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Son kaç gün: 5, 15, 30",
     *         required=false,
     *         @OA\Schema(type="integer", enum={5, 15, 30})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="earned",
     *                     type="array",
     *                     description="Kazanılan jetonlar",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="coin_amount", type="integer", example=50),
     *                         @OA\Property(property="description", type="string", example="Reklam izleme ödülü"),
     *                         @OA\Property(property="transaction_type", type="string", example="earned"),
     *                         @OA\Property(property="balance_before", type="integer", example=100),
     *                         @OA\Property(property="balance_after", type="integer", example=150),
     *                         @OA\Property(property="date", type="string", example="2025-01-06 14:30:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="spent",
     *                     type="array",
     *                     description="Kaybedilen jetonlar",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="coin_amount", type="integer", example=-50),
     *                         @OA\Property(property="description", type="string", example="Günlük Quiz"),
     *                         @OA\Property(property="transaction_type", type="string", example="spent"),
     *                         @OA\Property(property="balance_before", type="integer", example=300),
     *                         @OA\Property(property="balance_after", type="integer", example=250),
     *                         @OA\Property(property="date", type="string", example="2025-01-06 15:00:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="game_transactions",
     *                     type="array",
     *                     description="Oyun başlangıç ve bitiş işlemleri",
     *                     @OA\Items(
     *                         @OA\Property(property="game_id", type="integer", example=1),
     *                         @OA\Property(property="game_type", type="string", example="normal"),
     *                         @OA\Property(property="start_balance", type="integer", example=300),
     *                         @OA\Property(property="end_balance", type="integer", example=250),
     *                         @OA\Property(property="coin_change", type="integer", example=-50),
     *                         @OA\Property(property="description", type="string", example="Günlük Quiz"),
     *                         @OA\Property(property="started_at", type="string", example="2025-01-06 14:00:00"),
     *                         @OA\Property(property="completed_at", type="string", example="2025-01-06 15:00:00")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı bulunamadı.'
            ], 401);
        }

        $type = $request->input('type', 'all'); // earned, spent, all
        $days = $request->input('days'); // 5, 15, 30
        $limit = 30;

        // Tarih filtresi
        $query = CoinHistory::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        if ($days && in_array((int) $days, [5, 15, 30], true)) {
            $query->where('created_at', '>=', Carbon::now()->subDays((int) $days));
        }

        // Tip filtresi
        if ($type === 'earned') {
            $query->where('coin_amount', '>', 0);
        } elseif ($type === 'spent') {
            $query->where('coin_amount', '<', 0);
        }

        $coinHistory = $query->limit($limit)->get();

        // Kazançlar ve kayıpları ayır
        $earned = $coinHistory->where('coin_amount', '>', 0)->map(function ($item) {
            return [
                'id' => $item->id,
                'coin_amount' => $item->coin_amount,
                'description' => $this->formatDescription($item),
                'transaction_type' => $item->transaction_type,
                'balance_before' => $item->balance_before,
                'balance_after' => $item->balance_after,
                'date' => $item->created_at->format('Y-m-d H:i:s')
            ];
        })->values();

        $spent = $coinHistory->where('coin_amount', '<', 0)->map(function ($item) {
            return [
                'id' => $item->id,
                'coin_amount' => $item->coin_amount,
                'description' => $this->formatDescription($item),
                'transaction_type' => $item->transaction_type,
                'balance_before' => $item->balance_before,
                'balance_after' => $item->balance_after,
                'date' => $item->created_at->format('Y-m-d H:i:s')
            ];
        })->values();

        // Oyun başlangıç ve bitiş işlemlerini hesapla
        $gameTransactions = $this->getGameTransactions($user->id, $days);

        return response()->json([
            'success' => true,
            'data' => [
                'earned' => $earned,
                'spent' => $spent,
                'game_transactions' => $gameTransactions
            ]
        ]);
    }

    /**
     * Açıklamayı formatla
     */
    private function formatDescription(CoinHistory $item): string
    {
        $description = $item->description ?? '';

        // Metadata'dan oyun tipini al
        $metadata = $item->metadata ?? [];

        if (isset($metadata['game_type'])) {
            $gameType = $metadata['game_type'];
            $gameTypeNames = [
                'normal' => 'Günlük Quiz',
                'premium' => 'Premium Quiz',
                'tournament' => 'Turnuva',
                'daily_challenge' => 'Günlük Mücadele'
            ];
            return $gameTypeNames[$gameType] ?? $description;
        }

        // Düello kayıtlarında spesifik açıklama korunur
        if ($item->transaction_type === 'duel' && $description !== '') {
            return $description;
        }

        // Transaction type'a göre açıklama
        $typeDescriptions = [
            'earned' => 'Jeton Kazancı',
            'spent' => 'Jeton Harcaması',
            'bonus' => 'Bonus Jeton',
            'tournament_prize' => 'Turnuva Ödülü',
            'daily_reward' => 'Günlük Ödül',
            'purchase' => 'Jeton Satın Alma',
            'ad_watch' => 'Reklam İzleme Ödülü',
            'duel' => 'Düello',
        ];

        if (isset($metadata['reward_type']) && $metadata['reward_type'] === 'ad_watch') {
            return 'Reklam İzleme Ödülü';
        }

        if (isset($metadata['reward_type']) && $metadata['reward_type'] === 'registration_bonus') {
            return 'Kayıt Bonusu';
        }

        return $typeDescriptions[$item->transaction_type] ?? $description;
    }

    /**
     * Oyun başlangıç ve bitiş işlemlerini hesapla
     */
    private function getGameTransactions(int $userId, ?int $days = null): array
    {
        $query = IndividualGame::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at', 'desc');

        if ($days && in_array((int) $days, [5, 15, 30], true)) {
            $query->where('completed_at', '>=', Carbon::now()->subDays((int) $days));
        }

        $games = $query->limit(30)->get();

        $transactions = [];

        foreach ($games as $game) {
            // Oyun başlangıcından önceki son coin history kaydını bul
            $startCoinHistory = CoinHistory::where('user_id', $userId)
                ->where('created_at', '<=', $game->started_at)
                ->orderBy('created_at', 'desc')
                ->first();

            // Oyun bitişinden sonraki ilk coin history kaydını bul
            $endCoinHistory = CoinHistory::where('user_id', $userId)
                ->where('created_at', '>=', $game->completed_at)
                ->orderBy('created_at', 'asc')
                ->first();

            // Başlangıç bakiyesi: Oyun başlangıcından önceki son bakiyeyi al
            $startBalance = $startCoinHistory ? $startCoinHistory->balance_after : 0;

            // Bitiş bakiyesi: Oyun bitişinden sonraki ilk bakiyeyi al, yoksa oyun bitişinden önceki son bakiyeyi al
            if ($endCoinHistory) {
                $endBalance = $endCoinHistory->balance_before;
            } else {
                $endBalanceHistory = CoinHistory::where('user_id', $userId)
                    ->where('created_at', '<=', $game->completed_at)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $endBalance = $endBalanceHistory ? $endBalanceHistory->balance_after : $startBalance;
            }

            // Coin değişikliği
            $coinChange = $endBalance - $startBalance;

            // Oyun tipine göre açıklama
            $gameTypeNames = [
                'normal' => 'Günlük Quiz',
                'premium' => 'Premium Quiz',
                'tournament' => 'Turnuva',
                'daily_challenge' => 'Günlük Mücadele'
            ];

            $description = $gameTypeNames[$game->game_type] ?? 'Oyun';

            // Coin değişikliği olan oyunları ekle
            if ($coinChange != 0) {
                $transactions[] = [
                    'game_id' => $game->id,
                    'game_type' => $game->game_type,
                    'start_balance' => $startBalance,
                    'end_balance' => $endBalance,
                    'coin_change' => $coinChange,
                    'description' => $description,
                    'started_at' => $game->started_at ? $game->started_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $game->completed_at ? $game->completed_at->format('Y-m-d H:i:s') : null
                ];
            }
        }

        return $transactions;
    }
}
