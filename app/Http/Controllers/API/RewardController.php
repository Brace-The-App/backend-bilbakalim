<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoinHistory;
use App\Models\Duel;
use App\Models\GiftCardStore;
use App\Models\IndividualGame;
use App\Models\RewardRequest;
use App\Models\Tournament;
use App\Models\TournamentUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RewardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/reward/check-eligibility",
     *     summary="Ödül talebinde bulunabilir mi kontrolü",
     *     description="Kullanıcının günlük, haftalık veya turnuva kazananı olup olmadığını ve daha önce ödül talebinde bulunup bulunmadığını kontrol eder",
     *     tags={"Reward"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Ödül tipi: daily, weekly, tournament",
     *         required=true,
     *         @OA\Schema(type="string", enum={"daily", "weekly", "tournament"})
     *     ),
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="query",
     *         description="Turnuva ID (type=tournament için gerekli)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="eligible", type="boolean", example=true),
     *             @OA\Property(property="rank", type="integer", example=1),
     *             @OA\Property(property="coins_earned", type="integer", example=1645),
     *             @OA\Property(property="message", type="string", example="Ödül talebinde bulunabilirsiniz.")
     *         )
     *     )
     * )
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $user = Auth::user();
        $type = $request->input('type'); // daily, weekly, tournament

        if (!in_array($type, ['daily', 'weekly', 'tournament'])) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz ödül tipi.'
            ], 400);
        }

        $claimGate = $this->validateGiftClaimRequirements($user);
        if (!$claimGate['eligible']) {
            return response()->json([
                'success' => true,
                'eligible' => false,
                'rank' => null,
                'coins_earned' => 0,
                'current_coins' => $claimGate['current_coins'],
                'duel_earned_coins' => $claimGate['duel_earned_coins'] ?? 0,
                'games_played' => $claimGate['games_played'],
                'gift_claim_min_coins' => $claimGate['min_coins'],
                'gift_claim_min_games' => $claimGate['min_games'],
                'gift_claim_daily_limit' => $claimGate['daily_limit'] ?? 1,
                'claims_today' => $claimGate['claims_today'] ?? 0,
                'can_claim_today' => (bool) ($claimGate['can_claim_today'] ?? false),
                'error_code' => $claimGate['error_code'] ?? null,
                'message' => $claimGate['message'],
            ]);
        }

        $eligible = false;
        $rank = null;
        $coinsEarned = 0;
        $rewardDate = null;
        $foundTournamentId = null;

        if ($type === 'daily') {
            $today = Carbon::today();
            $tomorrow = Carbon::tomorrow();

            // Bugün kazanılan coin'leri hesapla ve sırala
            $dailyRanking = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
                ->where('coin_amount', '>', 0)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$today, $tomorrow])
                ->whereNotIn('user_id', User::query()->bots()->select('id'))
                ->groupBy('user_id')
                ->orderBy('total_coins', 'desc')
                ->get();

            $userRank = $dailyRanking->search(function ($item) use ($user) {
                return $item->user_id === $user->id;
            });

            if ($userRank !== false) {
                $rank = $userRank + 1;
                $userCoins = $dailyRanking[$userRank]->total_coins;
                $coinsEarned = (int) $userCoins;
                $rewardDate = $today->format('Y-m-d');

                // 1. sırada mı kontrol et
                if ($rank === 1) {
                    // Bu gün için daha önce ödül talebinde bulunmuş mu kontrol et
                    $existingRequest = RewardRequest::where('user_id', $user->id)
                        ->where('reward_type', 'daily')
                        ->where('reward_date', $today->format('Y-m-d'))
                        ->first();

                    if (!$existingRequest) {
                        $eligible = true;
                    }
                }
            }
        } elseif ($type === 'weekly') {
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            // Bu hafta kazanılan coin'leri hesapla ve sırala
            $weeklyRanking = CoinHistory::select('user_id', DB::raw('SUM(coin_amount) as total_coins'))
                ->where('coin_amount', '>', 0)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereNotIn('user_id', User::query()->bots()->select('id'))
                ->groupBy('user_id')
                ->orderBy('total_coins', 'desc')
                ->get();

            $userRank = $weeklyRanking->search(function ($item) use ($user) {
                return $item->user_id === $user->id;
            });

            if ($userRank !== false) {
                $rank = $userRank + 1;
                $userCoins = $weeklyRanking[$userRank]->total_coins;
                $coinsEarned = (int) $userCoins;
                $rewardDate = $weekStart->format('Y-m-d');

                // 1. sırada mı kontrol et
                if ($rank === 1) {
                    // Bu hafta için daha önce ödül talebinde bulunmuş mu kontrol et
                    $existingRequest = RewardRequest::where('user_id', $user->id)
                        ->where('reward_type', 'weekly')
                        ->where('reward_date', $weekStart->format('Y-m-d'))
                        ->first();

                    if (!$existingRequest) {
                        $eligible = true;
                    }
                }
            }
        } elseif ($type === 'tournament') {
            $tournamentId = $request->input('tournament_id');

            // Turnuva ID opsiyonel, 0 veya null gelebilir
            if ($tournamentId && $tournamentId != 0) {
                $tournament = Tournament::find($tournamentId);
                if (!$tournament) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Turnuva bulunamadı.'
                    ], 404);
                }

                // Belirli bir turnuva için kontrol
                $tournamentUsers = TournamentUser::where('tournament_id', $tournamentId)
                    ->where('status', 'completed')
                    ->orderBy('score', 'desc')
                    ->orderBy('correct_answers', 'desc')
                    ->orderBy('total_time_seconds', 'asc')
                    ->get();

                $userRank = $tournamentUsers->search(function ($item) use ($user) {
                    return $item->user_id === $user->id;
                });

                if ($userRank !== false) {
                    $rank = $userRank + 1;
                    $tournamentUser = $tournamentUsers[$userRank];
                    $coinsEarned = $tournamentUser->score ?? 0;
                    $rewardDate = $tournament->end_date ? Carbon::parse($tournament->end_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
                    $foundTournamentId = $tournamentId;

                    // 1. sırada mı kontrol et
                    if ($rank === 1) {
                        // Bu turnuva için daha önce ödül talebinde bulunmuş mu kontrol et
                        $existingRequest = RewardRequest::where('user_id', $user->id)
                            ->where('reward_type', 'tournament')
                            ->where('reward_date', $rewardDate)
                            ->get()
                            ->filter(function ($request) use ($tournamentId) {
                                return isset($request->metadata['tournament_id']) &&
                                    $request->metadata['tournament_id'] == $tournamentId;
                            })
                            ->first();

                        if (!$existingRequest) {
                            $eligible = true;
                        }
                    }
                }
            } else {
                // Turnuva ID 0 veya null ise, dinamik olarak son 1 günde/son 1 haftada bitmiş turnuvaları bul
                // Son 1 günde bitmiş turnuvaları kontrol et
                $oneDayAgo = Carbon::now()->subDay();
                $recentTournaments = Tournament::where('status', 'completed')
                    ->where('end_date', '>=', $oneDayAgo)
                    ->orderBy('end_date', 'desc')
                    ->get();

                $foundEligibleTournament = false;
                $foundTournamentId = null;
                $foundCoinsEarned = 0;
                $foundRewardDate = null;

                foreach ($recentTournaments as $tournament) {
                    $tournamentUsers = TournamentUser::where('tournament_id', $tournament->id)
                        ->where('status', 'completed')
                        ->orderBy('score', 'desc')
                        ->orderBy('correct_answers', 'desc')
                        ->orderBy('total_time_seconds', 'asc')
                        ->get();

                    $userRank = $tournamentUsers->search(function ($item) use ($user) {
                        return $item->user_id === $user->id;
                    });

                    if ($userRank !== false && $userRank === 0) {
                        // Kullanıcı 1. sırada
                        $tournamentUser = $tournamentUsers[$userRank];
                        $tempCoinsEarned = $tournamentUser->score ?? 0;
                        $tempRewardDate = $tournament->end_date ? Carbon::parse($tournament->end_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

                        // Bu turnuva için daha önce ödül talebinde bulunmuş mu kontrol et
                        $existingRequest = RewardRequest::where('user_id', $user->id)
                            ->where('reward_type', 'tournament')
                            ->where('reward_date', $tempRewardDate)
                            ->get()
                            ->filter(function ($request) use ($tournament) {
                                return isset($request->metadata['tournament_id']) &&
                                    $request->metadata['tournament_id'] == $tournament->id;
                            })
                            ->first();

                        if (!$existingRequest) {
                            $foundEligibleTournament = true;
                            $foundTournamentId = $tournament->id;
                            $foundCoinsEarned = $tempCoinsEarned;
                            $foundRewardDate = $tempRewardDate;
                            $rank = 1;
                            $coinsEarned = $foundCoinsEarned;
                            $rewardDate = $foundRewardDate;
                            $eligible = true;
                            break; // İlk uygun turnuvayı bulduk, döngüden çık
                        }
                    }
                }

                if (!$foundEligibleTournament) {
                    // Son 1 haftada bitmiş turnuvaları kontrol et
                    $oneWeekAgo = Carbon::now()->subWeek();
                    $weeklyTournaments = Tournament::where('status', 'completed')
                        ->where('end_date', '>=', $oneWeekAgo)
                        ->where('end_date', '<', $oneDayAgo)
                        ->orderBy('end_date', 'desc')
                        ->get();

                    foreach ($weeklyTournaments as $tournament) {
                        $tournamentUsers = TournamentUser::where('tournament_id', $tournament->id)
                            ->where('status', 'completed')
                            ->orderBy('score', 'desc')
                            ->orderBy('correct_answers', 'desc')
                            ->orderBy('total_time_seconds', 'asc')
                            ->get();

                        $userRank = $tournamentUsers->search(function ($item) use ($user) {
                            return $item->user_id === $user->id;
                        });

                        if ($userRank !== false && $userRank === 0) {
                            // Kullanıcı 1. sırada
                            $tournamentUser = $tournamentUsers[$userRank];
                            $tempCoinsEarned = $tournamentUser->score ?? 0;
                            $tempRewardDate = $tournament->end_date ? Carbon::parse($tournament->end_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

                            // Bu turnuva için daha önce ödül talebinde bulunmuş mu kontrol et
                            $existingRequest = RewardRequest::where('user_id', $user->id)
                                ->where('reward_type', 'tournament')
                                ->where('reward_date', $tempRewardDate)
                                ->get()
                                ->filter(function ($request) use ($tournament) {
                                    return isset($request->metadata['tournament_id']) &&
                                        $request->metadata['tournament_id'] == $tournament->id;
                                })
                                ->first();

                            if (!$existingRequest) {
                                $foundEligibleTournament = true;
                                $foundTournamentId = $tournament->id;
                                $foundCoinsEarned = $tempCoinsEarned;
                                $foundRewardDate = $tempRewardDate;
                                $rank = 1;
                                $coinsEarned = $foundCoinsEarned;
                                $rewardDate = $foundRewardDate;
                                $eligible = true;
                                break; // İlk uygun turnuvayı bulduk, döngüden çık
                            }
                        }
                    }
                }
            }
        }

        $message = $eligible
            ? 'Ödül talebinde bulunabilirsiniz.'
            : ($rank === 1
                ? 'Bu ödül için daha önce talepte bulunmuşsunuz.'
                : ($rank !== null
                    ? "Sıralamanız: {$rank}. Sadece 1. sıradaki kullanıcılar ödül talebinde bulunabilir."
                    : 'Bu ödül için uygun değilsiniz.'));

        $response = [
            'success' => true,
            'eligible' => $eligible,
            'rank' => $rank,
            'coins_earned' => $coinsEarned,
            'current_coins' => $claimGate['current_coins'],
            'duel_earned_coins' => $claimGate['duel_earned_coins'] ?? 0,
            'games_played' => $claimGate['games_played'],
            'gift_claim_min_coins' => $claimGate['min_coins'],
            'gift_claim_min_games' => $claimGate['min_games'],
            'gift_claim_daily_limit' => $claimGate['daily_limit'] ?? 1,
            'claims_today' => $claimGate['claims_today'] ?? 0,
            'can_claim_today' => (bool) ($claimGate['can_claim_today'] ?? false),
            'message' => $message
        ];

        // Turnuva tipi için tournament_id ekle
        if ($type === 'tournament' && $foundTournamentId) {
            $response['tournament_id'] = $foundTournamentId;
        }

        return response()->json($response);
    }

    /**
     * @OA\Post(
     *     path="/api/reward/claim",
     *     summary="Ödül talep et",
     *     description="Meydan okuma ile jeton biriktiren kullanıcı hediye talep eder. Zorunlu alan yalnızca gift_card_store_id. Şart: min jeton (varsayılan 250) + min oyun + günde en fazla 1 talep. type opsiyoneldir; gönderilmezse duel kaydedilir. Turnuva zorunlu değildir.",
     *     tags={"Reward"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"gift_card_store_id"},
     *             @OA\Property(property="gift_card_store_id", type="integer", example=10, description="GET /api/gift-card-stores listesinden seçilen marka id"),
     *             @OA\Property(property="store_id", type="integer", example=10, description="gift_card_store_id alias (opsiyonel)"),
     *             @OA\Property(property="type", type="string", enum={"duel", "daily", "weekly", "tournament"}, example="duel", description="Opsiyonel. Gönderilmezse duel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ödül talebi başarıyla oluşturuldu."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="gift_card_store_id", type="integer", example=10),
     *                 @OA\Property(property="gift_card_store", type="object",
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="type", type="string", example="mağaza"),
     *                     @OA\Property(property="image_url", type="string", example="https://bil-bakalim.com/storage/gift-card-stores/store.png")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Geçersiz istek / şartlar karşılanmıyor"),
     *     @OA\Response(response=404, description="Seçilen marka bulunamadı")
     * )
     */
    public function claim(Request $request): JsonResponse
    {
        $user = Auth::user();
        $storeId = $request->input('gift_card_store_id', $request->input('store_id'));

        // type artık zorunlu değil — varsayılan meydan okuma (duel)
        $type = $request->input('type', 'duel');
        if ($type === null || $type === '' || $type === '0') {
            $type = 'duel';
        }

        if (!in_array($type, ['daily', 'weekly', 'tournament', 'duel'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz ödül tipi.',
            ], 400);
        }

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'Çek kullanılacak marka seçilmelidir (gift_card_store_id).',
                'required' => ['gift_card_store_id'],
            ], 400);
        }

        $store = GiftCardStore::active()->find($storeId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Seçilen marka bulunamadı veya aktif değil.',
            ], 404);
        }

        $claimGate = $this->validateGiftClaimRequirements($user);
        if (!$claimGate['eligible']) {
            return response()->json([
                'success' => false,
                'message' => $claimGate['message'],
                'error_code' => $claimGate['error_code'] ?? 'gift_claim_not_eligible',
                'current_coins' => $claimGate['current_coins'],
                'duel_earned_coins' => $claimGate['duel_earned_coins'] ?? 0,
                'games_played' => $claimGate['games_played'],
                'gift_claim_min_coins' => $claimGate['min_coins'],
                'gift_claim_min_games' => $claimGate['min_games'],
                'gift_claim_daily_limit' => $claimGate['daily_limit'] ?? 1,
                'claims_today' => $claimGate['claims_today'] ?? 0,
                'can_claim_today' => false,
            ], 400);
        }

        $claimAmount = \App\Services\FinanceService::giftClaimMinCoins();
        $rewardDate = Carbon::today()->format('Y-m-d');
        $baseMetadata = [
            'gift_card_store_id' => (int) $store->id,
            'gift_card_store_type' => $store->type,
            'gift_card_store_image_url' => $store->image_url,
            'source' => $type === 'duel' ? 'duel' : $type,
        ];

        if ($type === 'weekly') {
            $rewardDate = Carbon::now()->startOfWeek()->format('Y-m-d');
        } elseif ($type === 'tournament') {
            $tournamentId = $request->input('tournament_id');
            if ($tournamentId && $tournamentId != 0) {
                $tournament = Tournament::find($tournamentId);
                if ($tournament) {
                    $rewardDate = $tournament->end_date
                        ? Carbon::parse($tournament->end_date)->format('Y-m-d')
                        : Carbon::today()->format('Y-m-d');
                    $baseMetadata['tournament_id'] = (int) $tournamentId;
                }
            }
        }

        try {
            $rewardRequest = DB::transaction(function () use ($user, $type, $rewardDate, $baseMetadata, $store, $claimAmount) {
                /** @var User $lockedUser */
                $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->firstOrFail();

                $dailyLimit = max(1, (int) config('app.gift_claim_daily_limit', 1));
                $claimsToday = $this->countClaimsToday((int) $lockedUser->id);
                if ($claimsToday >= $dailyLimit) {
                    throw new \RuntimeException('DAILY_CLAIM_LIMIT');
                }

                // Günlük / haftalık / turnuva: aynı gün tek talep (eski davranış)
                if ($type !== 'duel') {
                    $existing = RewardRequest::query()
                        ->where('user_id', $lockedUser->id)
                        ->where('reward_type', $type)
                        ->where('reward_date', $rewardDate)
                        ->whereIn('status', ['pending', 'approved'])
                        ->orderByDesc('id')
                        ->first();

                    if ($existing) {
                        throw new \RuntimeException('DAILY_CLAIM_LIMIT');
                    }

                    return RewardRequest::create([
                        'user_id' => $lockedUser->id,
                        'reward_type' => $type,
                        'coins_earned' => (int) ($lockedUser->coins ?? 0),
                        'reward_date' => $rewardDate,
                        'status' => 'pending',
                        'requested_at' => now(),
                        'metadata' => array_merge($baseMetadata, [
                            'wallet_coins_at_claim' => (int) ($lockedUser->coins ?? 0),
                        ]),
                    ]);
                }

                // Meydan okuma hediyesi:
                // - Eşik kadar duel_earned düşülür, talep panele düşer
                // - Günde en fazla 1 talep
                // - Onayda ekstra sıfırlama yok; redde iade
                $duelBefore = max(0, (int) ($lockedUser->duel_earned_coins ?? 0));
                if ($duelBefore < $claimAmount) {
                    throw new \RuntimeException('INSUFFICIENT_DUEL_EARNED');
                }

                $duelAfter = $duelBefore - $claimAmount;
                $lockedUser->duel_earned_coins = $duelAfter;
                $lockedUser->save();

                return RewardRequest::create([
                    'user_id' => $lockedUser->id,
                    'reward_type' => $type,
                    'coins_earned' => $claimAmount,
                    'reward_date' => $rewardDate,
                    'status' => 'pending',
                    'requested_at' => now(),
                    'metadata' => array_merge($baseMetadata, [
                        'claimed_amount' => $claimAmount,
                        'duel_earned_at_claim_before' => $duelBefore,
                        'duel_earned_at_claim_after' => $duelAfter,
                        'wallet_coins_at_claim' => (int) ($lockedUser->coins ?? 0),
                        'completed_games_at_claim' => (int) ($lockedUser->total_completed_games ?? 0),
                    ]),
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'DAILY_CLAIM_LIMIT') {
                $dailyLimit = max(1, (int) config('app.gift_claim_daily_limit', 1));
                $claimsToday = $this->countClaimsToday((int) $user->id);

                return response()->json([
                    'success' => false,
                    'message' => 'Bugün ödül talebinde bulundunuz. Günde en fazla 1 kez talep edebilirsiniz. Yarın tekrar deneyin.',
                    'error_code' => 'gift_claim_daily_limit',
                    'gift_claim_daily_limit' => $dailyLimit,
                    'claims_today' => $claimsToday,
                    'can_claim_today' => false,
                    'gift_claim_min_coins' => $claimAmount,
                    'duel_earned_coins' => (int) ($user->fresh()->duel_earned_coins ?? 0),
                ], 400);
            }

            if ($e->getMessage() === 'INSUFFICIENT_DUEL_EARNED') {
                return response()->json([
                    'success' => false,
                    'message' => "Hediye talep etmek için meydan okumadan net en az {$claimAmount} jeton kazanmış olmalısınız.",
                    'error_code' => 'gift_claim_insufficient_coins',
                    'duel_earned_coins' => (int) ($user->fresh()->duel_earned_coins ?? 0),
                    'gift_claim_min_coins' => $claimAmount,
                    'gift_claim_daily_limit' => (int) config('app.gift_claim_daily_limit', 1),
                    'can_claim_today' => true,
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Ödül talebi oluşturulamadı. Lütfen tekrar deneyin.',
                'error_code' => 'gift_claim_failed',
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ödül talebi oluşturulamadı. Lütfen tekrar deneyin.',
                'error_code' => 'gift_claim_failed',
            ], 500);
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi başarıyla oluşturuldu.',
            'data' => [
                'id' => $rewardRequest->id,
                'claimed_amount' => (int) ($rewardRequest->coins_earned ?? $claimAmount),
                'duel_earned_coins' => (int) ($user->duel_earned_coins ?? 0),
                'gift_claim_min_coins' => $claimAmount,
                'gift_claim_daily_limit' => (int) config('app.gift_claim_daily_limit', 1),
                'claims_today' => $this->countClaimsToday((int) $user->id),
                'can_claim_today' => false,
                'gift_card_store_id' => (int) $store->id,
                'gift_card_store' => [
                    'id' => (int) $store->id,
                    'type' => $store->type,
                    'image_url' => $store->image_url,
                ],
            ],
        ]);
    }

    /**
     * Hediye / ödül talep şartları:
     * - Meydan okumadan net en az N jeton (duel_earned_coins)
     * - En az M tamamlanmış oyun
     * - Günde en fazla 1 talep
     */
    private function validateGiftClaimRequirements(User $user): array
    {
        $minCoins = \App\Services\FinanceService::giftClaimMinCoins();
        $minGames = (int) config('app.gift_claim_min_games', 3);
        $dailyLimit = max(1, (int) config('app.gift_claim_daily_limit', 1));
        $duelEarnedCoins = (int) ($user->duel_earned_coins ?? 0);
        $currentCoins = (int) $user->coins;
        $gamesPlayed = $this->countCompletedGames($user->id);
        $claimsToday = $this->countClaimsToday((int) $user->id);

        $base = [
            'current_coins' => $currentCoins,
            'duel_earned_coins' => $duelEarnedCoins,
            'games_played' => $gamesPlayed,
            'min_coins' => $minCoins,
            'min_games' => $minGames,
            'daily_limit' => $dailyLimit,
            'claims_today' => $claimsToday,
            'can_claim_today' => $claimsToday < $dailyLimit,
        ];

        if ($claimsToday >= $dailyLimit) {
            return array_merge($base, [
                'eligible' => false,
                'error_code' => 'gift_claim_daily_limit',
                'message' => 'Bugün ödül talebinde bulundunuz. Günde en fazla 1 kez talep edebilirsiniz. Yarın tekrar deneyin.',
            ]);
        }

        if ($duelEarnedCoins < $minCoins) {
            return array_merge($base, [
                'eligible' => false,
                'error_code' => 'gift_claim_insufficient_coins',
                'message' => "Hediye talep etmek için meydan okumadan net en az {$minCoins} jeton kazanmış olmalısınız. Net düello kazancınız: {$duelEarnedCoins}.",
            ]);
        }

        if ($gamesPlayed < $minGames) {
            return array_merge($base, [
                'eligible' => false,
                'error_code' => 'gift_claim_insufficient_games',
                'message' => "Hediye talep etmek için en az {$minGames} oyun oynamış olmanız gerekir. Oynanan oyun: {$gamesPlayed}.",
            ]);
        }

        return array_merge($base, [
            'eligible' => true,
            'error_code' => null,
            'message' => 'Hediye talep şartları karşılanıyor.',
        ]);
    }

    private function countClaimsToday(int $userId): int
    {
        return (int) RewardRequest::query()
            ->where('user_id', $userId)
            ->whereDate('requested_at', Carbon::today())
            ->count();
    }

    private function countCompletedGames(int $userId): int
    {
        $individualGames = IndividualGame::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        $tournamentGames = TournamentUser::where('user_id', $userId)
            ->whereIn('status', ['completed', 'eliminated'])
            ->count();

        $duelGames = Duel::where(function ($query) use ($userId) {
            $query->where('challenger_id', $userId)
                ->orWhere('opponent_id', $userId);
        })
            ->where('status', 'finished')
            ->count();

        return $individualGames + $tournamentGames + $duelGames;
    }
}
