<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RevealsCorrectAnswerWhenWrong;
use App\Http\Services\DuelQuestionForeignKeyFixer;
use App\Models\Duel;
use App\Models\DuelAnswer;
use App\Models\Question;
use App\Models\User;
use App\Models\CoinHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DuelController extends Controller
{
    use RevealsCorrectAnswerWhenWrong;
    /**
     * @OA\Post(
     *     path="/api/duel/question-multiplier/{duel_id}",
     *     summary="Soru Bazlı 2x/3x Teklif Et",
     *     description="Aktif düellodaki mevcut soru için 2x/3x coin çarpanı teklifi gönderir. Teklif rakibe socket üzerinden iletilir.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="question_id", type="integer", example=123, description="Mevcut sorunun ID'si"),
     *                 @OA\Property(property="multiplier", type="integer", enum={2,4,6,8}, example=2, description="Soru bazlı çarpan değeri (2x, 3x, 4x veya 8x)")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="multiplier", type="integer", enum={2,4,6,8}, example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Teklif oluşturuldu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Soru için çarpan teklifi gönderildi."),
     *             @OA\Property(
     *                 property="bet",
     *                 type="object",
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="initiator_id", type="integer", example=1),
     *                 @OA\Property(property="opponent_id", type="integer", example=2),
     *                 @OA\Property(property="multiplier", type="integer", example=2),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu soru için zaten bekleyen bir teklif var.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Yetki yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düelloya erişim yetkiniz yok.")
     *         )
     *     )
     * )
     */
    public function offerQuestionMultiplier(Request $request, $duel_id): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|integer',
            'multiplier' => 'required|integer|in:2,4,6,8',
        ]);

        $user = Auth::user();
        $duel = Duel::with('currentQuestion')->findOrFail($duel_id);

        if ($duel->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Düello aktif değil.'
            ], 400);
        }

        if ($duel->challenger_id !== $user->id && $duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloya erişim yetkiniz yok.'
            ], 403);
        }

        if (!$duel->current_question_id || $duel->current_question_id != $request->question_id) {
            return response()->json([
                'success' => false,
                'message' => 'Sadece mevcut soru için çarpan teklifi yapabilirsiniz.'
            ], 400);
        }

        $settings = $duel->settings ?? [];
        $currentBet = $settings['current_bet'] ?? null;

        if ($currentBet && ($currentBet['status'] ?? null) === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Bu soru için zaten bekleyen bir teklif var.'
            ], 400);
        }

        $opponentId = $duel->challenger_id === $user->id ? $duel->opponent_id : $duel->challenger_id;

        $settings['current_bet'] = [
            'question_id' => $duel->current_question_id,
            'initiator_id' => $user->id,
            'opponent_id' => $opponentId,
            'multiplier' => (int) $request->multiplier,
            'status' => 'pending',
            'offered_at' => now()->toISOString(),
        ];

        $duel->update(['settings' => $settings]);

        $this->sendDuelQuestionBetRequestedWebhook($duel, $settings['current_bet']);

        return response()->json([
            'success' => true,
            'message' => 'Soru için çarpan teklifi gönderildi.',
            'bet' => $settings['current_bet'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/duel/question-multiplier/respond/{duel_id}",
     *     summary="Soru Bazlı 2x/3x Teklifine Cevap Ver",
     *     description="Rakipten gelen soru bazlı 2x/3x teklifini kabul eder veya reddeder. Kabul edilirse sadece o soru için çarpan uygulanır, reddedilirse teklifi yapan düelloyu kazanır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="question_id", type="integer", example=123, description="Teklif yapılan sorunun ID'si"),
     *                 @OA\Property(property="accept", type="boolean", example=true, description="true: kabul, false: reddet")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="accept", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Kabul: bet ve message döner. Reddet: bet, message ve winner_id döner.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Teklif kabul edildi. Bu soru için çarpan uygulandı."),
     *             @OA\Property(
     *                 property="bet",
     *                 type="object",
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="initiator_id", type="integer", example=1),
     *                 @OA\Property(property="opponent_id", type="integer", example=2),
     *                 @OA\Property(property="multiplier", type="integer", example=2),
     *                 @OA\Property(property="status", type="string", example="accepted")
     *             ),
     *             @OA\Property(property="winner_id", type="integer", example=1, description="Sadece accept=false (red) durumunda döner")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu soru için bekleyen bir teklif bulunmuyor.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Yetki yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düelloya erişim yetkiniz yok.")
     *         )
     *     )
     * )
     */
    public function respondQuestionMultiplier(Request $request, $duel_id): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|integer',
            'accept' => 'required|boolean',
        ]);

        $user = Auth::user();
        $duel = Duel::with('currentQuestion')->findOrFail($duel_id);

        if ($duel->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Düello aktif değil.'
            ], 400);
        }

        if ($duel->challenger_id !== $user->id && $duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloya erişim yetkiniz yok.'
            ], 403);
        }

        $settings = $duel->settings ?? [];
        $currentBet = $settings['current_bet'] ?? null;

        if (!$currentBet || ($currentBet['status'] ?? null) !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Bu soru için bekleyen bir teklif bulunmuyor.'
            ], 400);
        }

        if ($currentBet['question_id'] != $request->question_id) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz soru için işlem yapılmaya çalışılıyor.'
            ], 400);
        }

        // Teklife cevap verebilecek kişi sadece rakip oyuncu
        if ($currentBet['opponent_id'] != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu teklife cevap verme yetkiniz yok.'
            ], 403);
        }

        $accept = (bool) $request->accept;

        if ($accept) {
            // Kabul edildi: soru bazlı çarpanı ayarla
            $settings['current_bet']['status'] = 'accepted';
            $settings['current_bet']['responded_at'] = now()->toISOString();
            $settings['current_question_multiplier'] = (int) $currentBet['multiplier'];

            $duel->update(['settings' => $settings]);

            $this->sendDuelQuestionBetRespondedWebhook($duel, $settings['current_bet']);

            return response()->json([
                'success' => true,
                'message' => 'Teklif kabul edildi. Bu sorudan itibaren çarpan uygulanır.',
                'bet' => $settings['current_bet'],
            ]);
        }

        // Reddedildi: reddeden elenir, teklifi yapan düelloyu kazanır
        $settings['current_bet']['status'] = 'rejected';
        $settings['current_bet']['responded_at'] = now()->toISOString();
        $duel->update(['settings' => $settings]);

        $winnerId = (int) $currentBet['initiator_id'];

        $this->finishDuel($duel, $winnerId);
        $this->sendDuelFinishedWebhook($duel);

        $this->sendDuelQuestionBetRespondedWebhook($duel, $settings['current_bet']);

        return response()->json([
            'success' => true,
            'message' => 'Teklif reddedildi. Düelloyu teklif eden kazandı.',
            'bet' => $settings['current_bet'],
            'winner_id' => $winnerId,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/duel/create",
     *     summary="Düello Oluştur",
     *     description="Yeni bir düello oluşturur. X1 otomatik başlar, X2/X4/X8 karşı tarafa istek gönderir.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="multiplier", type="string", enum={"x1", "x2", "x4", "x8"}, example="x2", description="Çarpan değeri")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="multiplier", type="string", enum={"x1", "x2", "x4", "x8"}, example="x2")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düello başarıyla oluşturuldu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Düello başladı!"),
     *             @OA\Property(property="duel", type="object"),
     *             @OA\Property(property="question", type="object", nullable=true),
     *             @OA\Property(property="auto_started", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Düello için yeterli coin gereklidir.")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        // Match ekranı düello oluşturmaz. Mobil sadece socket `duel-ready` emit eder;
        // eşleştirme + duel create socket/backend tarafında yapılır.
        $request->validate([
            'multiplier' => 'required|in:x1,x2,x4,x8',
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Eşleşme için socket üzerinden duel-ready kullanın. Match ekranında duel/create çağrılmamalı.',
            'use_socket' => true,
            'events' => [
                'emit' => 'duel-ready',
                'listen' => 'duel-matched',
                'cancel' => 'duel-cancel-ready',
            ],
            'payload_example' => [
                'userId' => 48,
                'multiplier' => 'x1',
            ],
        ], 400);
    }

    /**
     * Socket eşleşme kuyruğundan gelen match isteği.
     * İki hazır kullanıcıyı tek düelloda birleştirir.
     */
    public function socketMatch(Request $request): JsonResponse
    {
        $secret = $request->header('X-Socket-Secret') ?: $request->input('secret');
        if (!hash_equals((string) config('app.socket_internal_secret'), (string) $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'challenger_id' => 'required|integer|exists:users,id',
            'opponent_id' => 'required|integer|different:challenger_id|exists:users,id',
            'multiplier' => 'required|in:x1,x2,x4,x8',
        ]);

        // questions_old FK kalıntısını otomatik düzelt (sıra bağımsız eşleşme için kritik)
        $this->ensureDuelQuestionForeignKeys();

        try {
            return $this->createSocketMatchedDuel($validated);
        } catch (\Throwable $e) {
            if ($this->isQuestionForeignKeyError($e)) {
                $this->ensureDuelQuestionForeignKeys(force: true);

                try {
                    return $this->createSocketMatchedDuel($validated);
                } catch (\Throwable $retryError) {
                    Log::error('Socket duel match retry error', [
                        'error' => $retryError->getMessage(),
                        'challenger_id' => $validated['challenger_id'] ?? null,
                        'opponent_id' => $validated['opponent_id'] ?? null,
                    ]);
                }
            }

            Log::error('Socket duel match error', [
                'error' => $e->getMessage(),
                'challenger_id' => $validated['challenger_id'] ?? null,
                'opponent_id' => $validated['opponent_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eşleşme oluşturulamadı.',
            ], 500);
        }
    }

    /**
     * Socket kuyruğundan gelen iki kullanıcı için tek active düello oluştur.
     */
    private function createSocketMatchedDuel(array $validated): JsonResponse
    {
        $challenger = User::query()->find($validated['challenger_id']);
        $opponent = User::query()->find($validated['opponent_id']);

        if (!$challenger || !$opponent) {
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı bulunamadı.',
            ], 404);
        }

        if ((int) $challenger->coins <= 0 || (int) $opponent->coins <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Oyuncuların yeterli coini yok.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Her iki kullanıcının açık/yarım kalmış düellolarını kapat (sıra bağımsız rematch)
            Duel::whereIn('status', ['waiting', 'active'])
                ->where(function ($q) use ($challenger, $opponent) {
                    $q->where('challenger_id', $challenger->id)
                        ->orWhere('opponent_id', $challenger->id)
                        ->orWhere('challenger_id', $opponent->id)
                        ->orWhere('opponent_id', $opponent->id);
                })
                ->lockForUpdate()
                ->get()
                ->each(function (Duel $old) {
                    $old->update([
                        'status' => 'finished',
                        'finished_at' => now(),
                    ]);
                });

            $firstQuestion = Question::query()
                ->where('is_active', true)
                ->inRandomOrder()
                ->first();

            if (!$firstQuestion) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aktif soru bulunamadı.',
                ], 400);
            }

            $duel = Duel::create([
                'challenger_id' => $challenger->id,
                'opponent_id' => $opponent->id,
                'multiplier' => $validated['multiplier'],
                'status' => 'active',
                'started_at' => now(),
                'challenger_coins_before' => (int) $challenger->coins,
                'opponent_coins_before' => (int) $opponent->coins,
                'current_question_id' => $firstQuestion->id,
                'current_question_number' => 1,
            ]);

            $duel->load(['challenger', 'opponent', 'currentQuestion']);

            DB::commit();

            $this->sendDuelStartedWebhook($duel, $firstQuestion);

            return response()->json([
                'success' => true,
                'message' => 'Eşleşme tamamlandı.',
                'duel' => [
                    'duelId' => $duel->id,
                    'challengerId' => $duel->challenger_id,
                    'opponentId' => $duel->opponent_id,
                    'multiplier' => $duel->multiplier,
                    'status' => 'matched',
                    'db_status' => $duel->status,
                ],
                'question' => $this->formatQuestionMultilingual($firstQuestion),
                'challenger' => [
                    'id' => $duel->challenger->id,
                    'name' => $duel->challenger->name,
                    'avatar' => $duel->challenger->avatar,
                ],
                'opponent' => [
                    'id' => $duel->opponent->id,
                    'name' => $duel->opponent->name,
                    'avatar' => $duel->opponent->avatar,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function isQuestionForeignKeyError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '1452')
            || str_contains($message, 'questions_old')
            || str_contains($message, 'duels_current_question_id_foreign')
            || str_contains($message, 'Integrity constraint violation');
    }

    /**
     * FK fixer opsiyonel: sınıf yoksa eşleşmeyi bozma.
     */
    private function ensureDuelQuestionForeignKeys(bool $force = false): void
    {
        if (!class_exists(DuelQuestionForeignKeyFixer::class)) {
            Log::warning('DuelQuestionForeignKeyFixer missing; skipping FK ensure');
            return;
        }

        try {
            /** @var DuelQuestionForeignKeyFixer $fixer */
            $fixer = app(DuelQuestionForeignKeyFixer::class);
            if ($force) {
                $fixer->forceClearCache();
            }
            $fixer->ensure();
        } catch (\Throwable $e) {
            Log::warning('DuelQuestionForeignKeyFixer failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/duel/status/{duel_id}",
     *     summary="Düello Durumu",
     *     description="Düello durumunu, mevcut soruyu ve oyuncu bilgilerini getirir.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düello durumu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="duel", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Erişim yetkisi yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düelloya erişim yetkiniz yok.")
     *         )
     *     )
     * )
     */
    public function status($duel_id): JsonResponse
    {
        $user = Auth::user();
        $duel = Duel::with(['challenger', 'opponent', 'currentQuestion'])
            ->findOrFail($duel_id);

        // Kullanıcı kontrolü
        if ($duel->challenger_id !== $user->id && $duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloya erişim yetkiniz yok.'
            ], 403);
        }

        $currentQuestion = null;
        if ($duel->current_question_id) {
            $currentQuestion = $this->formatQuestionMultilingual($duel->currentQuestion);
        }

        return response()->json([
            'success' => true,
            'duel' => [
                'id' => $duel->id,
                'multiplier' => $duel->multiplier,
                'status' => $duel->status,
                'current_question_number' => $duel->current_question_number,
                'question_value' => $duel->question_value,
                'challenger' => [
                    'id' => $duel->challenger->id,
                    'name' => $duel->challenger->name,
                    'avatar' => $duel->challenger->avatar,
                    'coins_before' => $duel->challenger_coins_before,
                    'coins_after' => $duel->challenger_coins_after,
                    'current_coins' => (int) ($duel->challenger->coins ?? 0),
                ],
                'opponent' => $duel->opponent ? [
                    'id' => $duel->opponent->id,
                    'name' => $duel->opponent->name,
                    'avatar' => $duel->opponent->avatar,
                    'coins_before' => $duel->opponent_coins_before,
                    'coins_after' => $duel->opponent_coins_after,
                    'current_coins' => (int) ($duel->opponent->coins ?? 0),
                ] : null,
                'current_question' => $currentQuestion,
                'winner_id' => $duel->winner_id,
                'started_at' => $duel->started_at,
                'finished_at' => $duel->finished_at,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/duel/list",
     *     summary="Aktif Düello Listesi",
     *     description="Katılımcı bekleyen ve kullanıcının içinde bulunduğu aktif düelloları listeler.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Düello listesi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="waiting_duels", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="active_duels", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function list(): JsonResponse
    {
        $user = Auth::user();

        // Katılımcı bekleyen (opponent_id null) düellolar
        $waitingDuels = Duel::with(['challenger'])
            ->where('status', 'waiting')
            ->whereNull('opponent_id')
            ->orderByDesc('id')
            ->get()
            ->map(function (Duel $duel) {
                return [
                    'id' => $duel->id,
                    'multiplier' => $duel->multiplier,
                    'status' => $duel->status,
                    'challenger' => [
                        'id' => $duel->challenger->id,
                        'name' => $duel->challenger->name,
                        'avatar' => $duel->challenger->avatar,
                    ],
                    'created_at' => $duel->created_at,
                ];
            });

        // Kullanıcının içinde bulunduğu aktif düellolar (bilgi amaçlı)
        $activeDuels = Duel::with(['challenger', 'opponent'])
            ->where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('challenger_id', $user->id)
                    ->orWhere('opponent_id', $user->id);
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (Duel $duel) {
                return [
                    'id' => $duel->id,
                    'multiplier' => $duel->multiplier,
                    'status' => $duel->status,
                    'challenger' => [
                        'id' => $duel->challenger->id,
                        'name' => $duel->challenger->name,
                        'avatar' => $duel->challenger->avatar,
                    ],
                    'opponent' => $duel->opponent ? [
                        'id' => $duel->opponent->id,
                        'name' => $duel->opponent->name,
                        'avatar' => $duel->opponent->avatar,
                    ] : null,
                    'started_at' => $duel->started_at,
                ];
            });

        return response()->json([
            'success' => true,
            'waiting_duels' => $waitingDuels,
            'active_duels' => $activeDuels,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/duel/accept/{duel_id}",
     *     summary="Düelloyu Kabul Et",
     *     description="X2/X4/X8 düello isteğini kabul eder ve düelloyu başlatır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düello başladı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Düello başladı!"),
     *             @OA\Property(property="duel", type="object"),
     *             @OA\Property(property="question", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Yetki yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düelloyu kabul etme yetkiniz yok.")
     *         )
     *     )
     * )
     */
    public function accept($duel_id): JsonResponse
    {
        $user = Auth::user();
        $duel = Duel::findOrFail($duel_id);

        // Rakip kontrolü
        if ($duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloyu kabul etme yetkiniz yok.'
            ], 403);
        }

        if ($duel->status !== 'waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Bu düello zaten başlamış veya bitmiş.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Düello durumunu aktif yap
            $duel->update([
                'status' => 'active',
                'started_at' => now(),
            ]);

            // İlk soruyu getir ve başlat
            $firstQuestion = $this->getNextQuestion($duel);
            if ($firstQuestion) {
                $duel->update([
                    'current_question_id' => $firstQuestion->id,
                    'current_question_number' => 1,
                ]);
            }

            // Socket bildirimi gönder
            $this->sendDuelStartedWebhook($duel, $firstQuestion);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Düello başladı!',
                'duel' => $duel->load(['challenger', 'opponent', 'currentQuestion']),
                'question' => $firstQuestion ? $this->formatQuestionMultilingual($firstQuestion) : null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel accept error', [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Düello başlatılırken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/duel/join/{duel_id}",
     *     summary="Açık Düelloya Katıl",
     *     description="Katılımcı bekleyen (opponentsiz) bir düelloya katılır ve düelloyu başlatır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düello başladı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Düello başladı!"),
     *             @OA\Property(property="duel", type="object"),
     *             @OA\Property(property="question", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düelloya katılamazsınız.")
     *         )
     *     )
     * )
     */
    public function join($duel_id): JsonResponse
    {
        $user = Auth::user();
        $duel = Duel::findOrFail($duel_id);

        // Yalnızca opponentsiz ve waiting durumundaki düellolara katılınabilir
        if ($duel->status !== 'waiting' || $duel->opponent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düello katılıma açık değil.'
            ], 400);
        }

        // Kullanıcı kendi açtığı düelloya join atamaz
        if ($duel->challenger_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Kendi oluşturduğunuz düelloya katılamazsınız.'
            ], 400);
        }

        // Kullanıcının aktif düellosu olmamalı
        $activeDuel = Duel::where(function($query) use ($user) {
                $query->where('challenger_id', $user->id)
                    ->orWhere('opponent_id', $user->id);
            })
            ->whereIn('status', ['waiting', 'active'])
            ->first();

        if ($activeDuel) {
            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir düellonuz var.'
            ], 400);
        }

        // Kullanıcının coin bakiyesi kontrolü
        if ((int) $user->coins <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Düelloya katılmak için yeterli coin gereklidir.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Opponent bilgilerini güncelle
            $duel->opponent_id = $user->id;
            $duel->opponent_coins_before = (int) $user->coins;
            $duel->status = 'active';
            $duel->started_at = now();
            $duel->save();

            // İlk soruyu getir ve başlat
            $firstQuestion = $this->getNextQuestion($duel);
            if ($firstQuestion) {
                $duel->update([
                    'current_question_id' => $firstQuestion->id,
                    'current_question_number' => 1,
                ]);
            }

            // Socket bildirimi gönder
            $this->sendDuelStartedWebhook($duel, $firstQuestion);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Düello başladı!',
                'duel' => $duel->load(['challenger', 'opponent', 'currentQuestion']),
                'question' => $firstQuestion ? $this->formatQuestionMultilingual($firstQuestion) : null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel join error', [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Düelloya katılırken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/duel/reject/{duel_id}",
     *     summary="Düelloyu Reddet",
     *     description="X2/X4/X8 düello isteğini reddeder. Reddedilirse isteği gönderen otomatik kazanır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düello reddedildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Düello reddedildi. İsteği gönderen kazandı."),
     *             @OA\Property(property="duel", type="object"),
     *             @OA\Property(property="winner_id", type="integer", example=1),
     *             @OA\Property(property="coins_transferred", type="integer", example=18)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Normal düello reddedilemez.")
     *         )
     *     )
     * )
     */
    public function reject($duel_id): JsonResponse
    {
        $user = Auth::user();
        $duel = Duel::findOrFail($duel_id);

        // Rakip kontrolü
        if ($duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloyu reddetme yetkiniz yok.'
            ], 403);
        }

        if ($duel->status !== 'waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Bu düello zaten başlamış veya bitmiş.'
            ], 400);
        }

        // X1 düelloları reddedilemez (otomatik başlar)
        if ($duel->multiplier === 'x1') {
            return response()->json([
                'success' => false,
                'message' => 'Normal düello reddedilemez.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $challenger = User::findOrFail($duel->challenger_id);
            $opponent = User::findOrFail($duel->opponent_id);

            // Soru değeri (multiplier ile çarpılmış)
            $questionValue = $duel->question_value; // 1 * multiplier

            // Reddeden (opponent) kaybeder, isteği gönderen (challenger) kazanır (tam tutar; %10 maç sonunda)
            $opponentLoss = $this->transferCoins($opponent, $challenger, $questionValue, $duel);

            // Düello bitir - challenger kazandı (+ maç sonu %10)
            $endCommission = $this->finishDuel($duel, $duel->challenger_id);

            // Socket bildirimi gönder
            $this->sendDuelFinishedWebhook($duel);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Düello reddedildi. İsteği gönderen kazandı.',
                'duel' => $duel->load(['challenger', 'opponent', 'winner']),
                'winner_id' => $duel->challenger_id,
                'coins_transferred' => $opponentLoss['received'],
                'coins_taken' => $opponentLoss['taken'],
                'commission' => $endCommission,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel reject error', [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Düello reddedilirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/duel/leave/{duel_id}",
     *     summary="Düellodan Çekil",
     *     description="Aktif düellodan çekilir. Rakip otomatik kazanır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Düellodan çekildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Düellodan çekildiniz."),
     *             @OA\Property(property="duel", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu düello zaten bitmiş.")
     *         )
     *     )
     * )
     */
    public function leave($duel_id): JsonResponse
    {
        $user = Auth::user();
        $duel = Duel::findOrFail($duel_id);

        // Kullanıcı kontrolü
        if ($duel->challenger_id !== $user->id && $duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düellodan çekilme yetkiniz yok.'
            ], 403);
        }

        if (!in_array($duel->status, ['waiting', 'active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düello zaten bitmiş.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Kazanan belirle (çekilenin rakibi). Açık düelloda rakip yoksa winner null olur
            $winnerId = $duel->challenger_id === $user->id
                ? $duel->opponent_id
                : $duel->challenger_id;

            // Düello bitir (winner null ise sadece iptal/kapat, komisyon yok)
            $this->finishDuel($duel, $winnerId);

            // Socket bildirimi gönder
            $this->sendDuelFinishedWebhook($duel);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Düellodan çekildiniz.',
                'duel' => $duel->load(['challenger', 'opponent', 'winner'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel leave error', [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Düellodan çekilirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/duel/answer/{duel_id}",
     *     summary="Cevap Gönder",
     *     description="Düello sorusuna cevap gönderir. Her iki oyuncu da cevap verdiğinde coin transferi yapılır.",
     *     tags={"Duel"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duel_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Düello ID'si"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="selected_answer", type="string", enum={"1", "2", "3", "4"}, example="2", description="Seçilen cevap")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="selected_answer", type="string", enum={"1", "2", "3", "4"}, example="2")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cevap gönderildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="correct_answer", type="string", example="2"),
     *             @OA\Property(property="both_answered", type="boolean", example=false),
     *             @OA\Property(property="waiting_for_opponent", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu soruya zaten cevap verdiniz.")
     *         )
     *     )
     * )
     */
    public function submitAnswer(Request $request, $duel_id): JsonResponse
    {
        $request->validate([
            'selected_answer' => 'required|in:0,1,2,3,4',
        ]);

        $this->ensureDuelQuestionForeignKeys();

        $user = Auth::user();
        $duel = Duel::with('currentQuestion')->findOrFail($duel_id);

        // Kullanıcı kontrolü
        if ($duel->challenger_id !== $user->id && $duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu düelloya cevap verme yetkiniz yok.'
            ], 403);
        }

        if ($duel->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Düello aktif değil.'
            ], 400);
        }

        if (!$duel->currentQuestion) {
            return response()->json([
                'success' => false,
                'message' => 'Mevcut soru bulunamadı.'
            ], 404);
        }

        // Kullanıcı bu soruya zaten cevap vermiş mi?
        $existingAnswer = DuelAnswer::where('duel_id', $duel->id)
            ->where('user_id', $user->id)
            ->where('question_id', $duel->current_question_id)
            ->first();

        if ($existingAnswer) {
            return response()->json([
                'success' => false,
                'message' => 'Bu soruya zaten cevap verdiniz.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $question = $duel->currentQuestion;
            // Cevap 0 gelirse (süre bitti / cevap verilmedi) yanlış kabul et - quiz ve turnuva ile aynı mantık
            $selectedAnswer = $request->selected_answer;
            $isCorrect = ($selectedAnswer !== '0' && $selectedAnswer !== 0 && (string) $selectedAnswer === (string) $question->correct_answer);

            // Soru bazlı çarpan (2x/3x vb) - varsayılan 1
            $settings = $duel->settings ?? [];
            $currentMultiplier = isset($settings['current_question_multiplier'])
                ? max(1, (int) $settings['current_question_multiplier'])
                : 1;

            // Temel soru değeri (1 * duel multiplier) ve soru bazlı çarpan ile çarpılmış nihai değer
            $baseQuestionValue = $duel->question_value;
            $questionValue = $baseQuestionValue * $currentMultiplier;

            // Cevap kaydı oluştur (henüz coin transferi yapma)
            $answer = DuelAnswer::create([
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'question_id' => $question->id,
                'selected_answer' => $request->selected_answer,
                'is_correct' => $isCorrect,
                'question_value' => $questionValue,
                'coins_before' => (int) $user->coins,
                'answered_at' => now(),
            ]);

            // Her iki oyuncu da cevap verdi mi kontrol et
            $challengerAnswer = DuelAnswer::where('duel_id', $duel->id)
                ->where('user_id', $duel->challenger_id)
                ->where('question_id', $duel->current_question_id)
                ->first();

            $opponentAnswer = DuelAnswer::where('duel_id', $duel->id)
                ->where('user_id', $duel->opponent_id)
                ->where('question_id', $duel->current_question_id)
                ->first();

            $bothAnswered = $challengerAnswer && $opponentAnswer;

            if ($bothAnswered) {
                // Her iki oyuncu da cevap verdi, coin transferi yap
                $this->processAnswers($duel, $challengerAnswer, $opponentAnswer, $question, $questionValue);

                // Sonraki soruya geç veya düello bitir
                $this->moveToNextQuestion($duel);
            }

            DB::commit();

            // Socket bildirimi gönder
            $this->sendDuelAnswerWebhook($duel, $user, $isCorrect, $bothAnswered);

            return response()->json(array_merge([
                'success' => true,
                'is_correct' => $isCorrect,
                'both_answered' => $bothAnswered,
                'waiting_for_opponent' => !$bothAnswered,
            ], $this->correctAnswerRevealForQuestion($question, $isCorrect)));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel answer error', [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cevap gönderilirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * Cevapları işle ve coin transferi yap
     */
    private function processAnswers(Duel $duel, DuelAnswer $challengerAnswer, DuelAnswer $opponentAnswer, Question $question, int $questionValue): void
    {
        $challenger = User::findOrFail($duel->challenger_id);
        $opponent = User::findOrFail($duel->opponent_id);

        $challengerCorrect = $challengerAnswer->is_correct;
        $opponentCorrect = $opponentAnswer->is_correct;

        // Senaryo 1: Her ikisi de doğru → ikisi de o anki soru değeri (x) kadar kazanır
        if ($challengerCorrect && $opponentCorrect) {
            $this->addCoins($challenger, $questionValue, $duel, 'Düello: iki taraf da doğru');
            $this->addCoins($opponent, $questionValue, $duel, 'Düello: iki taraf da doğru');

            $challengerAnswer->update([
                'coins_change' => $questionValue,
                'coins_after' => (int) $challenger->coins,
            ]);
            $opponentAnswer->update([
                'coins_change' => $questionValue,
                'coins_after' => (int) $opponent->coins,
            ]);

            return;
        }

        // Senaryo 2: Her ikisi de yanlış → Bakiyeler düşer (uygulama komisyonuna yazılmaz)
        if (!$challengerCorrect && !$opponentCorrect) {
            $challengerLoss = $this->subtractCoins($challenger, $questionValue, $duel, 'Düello: iki taraf da yanlış');
            $opponentLoss = $this->subtractCoins($opponent, $questionValue, $duel, 'Düello: iki taraf da yanlış');

            $challengerAnswer->update([
                'coins_change' => -$challengerLoss,
                'coins_after' => (int) $challenger->coins,
            ]);
            $opponentAnswer->update([
                'coins_change' => -$opponentLoss,
                'coins_after' => (int) $opponent->coins,
            ]);
            return;
        }

        // Senaryo 3: Biri doğru, diğeri yanlış → Maç içinde tam tutar transfer (komisyon maç sonunda)
        if ($challengerCorrect && !$opponentCorrect) {
            $result = $this->transferCoins($opponent, $challenger, $questionValue, $duel);
            $challengerAnswer->update([
                'coins_change' => $result['received'],
                'coins_after' => (int) $challenger->coins,
            ]);
            $opponentAnswer->update([
                'coins_change' => -$result['taken'],
                'coins_after' => (int) $opponent->coins,
            ]);
        } elseif (!$challengerCorrect && $opponentCorrect) {
            $result = $this->transferCoins($challenger, $opponent, $questionValue, $duel);
            $challengerAnswer->update([
                'coins_change' => -$result['taken'],
                'coins_after' => (int) $challenger->coins,
            ]);
            $opponentAnswer->update([
                'coins_change' => $result['received'],
                'coins_after' => (int) $opponent->coins,
            ]);
        }
    }

    /**
     * Sonraki soruya geç veya düello bitir
     */
    private function moveToNextQuestion(Duel $duel): void
    {
        $challenger = User::query()->findOrFail($duel->challenger_id);
        $opponent = User::query()->findOrFail($duel->opponent_id);

        if ((int) $challenger->coins <= 0 || (int) $opponent->coins <= 0) {
            $winnerId = (int) $challenger->coins > 0 ? $duel->challenger_id : $duel->opponent_id;
            $this->finishDuel($duel, $winnerId);
            return;
        }

        $nextQuestion = $this->getNextQuestion($duel);
        if ($nextQuestion) {
            $settings = $duel->settings ?? [];
            // Kabul edilen soru çarpanı sonraki sorularda da devam eder; sadece bekleyen teklifi temizle
            unset($settings['current_bet']);
            // Eski davranış (bug): her soruda çarpan 1'e düşüyordu
            // unset($settings['current_question_multiplier'], $settings['current_bet']);

            try {
                $duel->update([
                    'current_question_id' => $nextQuestion->id,
                    'current_question_number' => $duel->current_question_number + 1,
                    'settings' => $settings,
                ]);
            } catch (\Throwable $e) {
                if ($this->isQuestionForeignKeyError($e)) {
                    $this->ensureDuelQuestionForeignKeys(force: true);
                    $duel->update([
                        'current_question_id' => $nextQuestion->id,
                        'current_question_number' => $duel->current_question_number + 1,
                        'settings' => $settings,
                    ]);
                } else {
                    throw $e;
                }
            }

            $this->sendDuelNextQuestionWebhook($duel, $nextQuestion);
        } else {
            $winnerId = (int) $challenger->coins >= (int) $opponent->coins
                ? $duel->challenger_id
                : $duel->opponent_id;
            $this->finishDuel($duel, $winnerId);
        }
    }

    /**
     * Düello bitir.
     * Kazanan yoksa (açık düello iptal) sadece kapatılır.
     * Kazanan varsa: maç boyunca net coin kazancının %10'u kesilir → duels.app_commission.
     *
     * @return int Kesilen komisyon tutarı
     */
    private function finishDuel(Duel $duel, ?int $winnerId): int
    {
        $challenger = User::query()->find($duel->challenger_id);
        $opponent = $duel->opponent_id ? User::query()->find($duel->opponent_id) : null;

        $commission = 0;

        if ($winnerId && ($challenger || $opponent)) {
            $winner = (int) $winnerId === (int) $duel->challenger_id ? $challenger : $opponent;
            $coinsBefore = (int) $winnerId === (int) $duel->challenger_id
                ? (int) $duel->challenger_coins_before
                : (int) $duel->opponent_coins_before;

            if ($winner) {
                $coinsNow = (int) $winner->fresh()->coins;
                $netGain = max(0, $coinsNow - $coinsBefore);
                $commission = (int) floor($netGain * 0.1);

                if ($commission > 0) {
                    $this->subtractCoins($winner, $commission, $duel, 'Düello: maç sonu %10 komisyon');
                    $duel->increment('app_commission', $commission);
                }
            }
        }

        $duel->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
            'finished_at' => now(),
            'challenger_coins_after' => (int) ($challenger?->fresh()->coins ?? 0),
            'opponent_coins_after' => (int) ($opponent?->fresh()->coins ?? $duel->opponent_coins_after ?? 0),
        ]);

        return $commission;
    }

    /**
     * Sonraki soruyu getir (karışık kategori ve seviye)
     */
    private function getNextQuestion(Duel $duel): ?Question
    {
        // Kullanılan soru ID'lerini al
        $usedQuestionIds = DuelAnswer::where('duel_id', $duel->id)
            ->pluck('question_id')
            ->toArray();

        // Karışık kategori ve seviyeden soru seç
        $question = Question::where('is_active', true)
            ->whereNotIn('id', $usedQuestionIds)
            ->inRandomOrder()
            ->first();

        return $question;
    }

    /**
     * Soruyu çoklu dil formatında formatla
     */
    private function formatQuestionMultilingual(?Question $question): ?array
    {
        if (!$question) {
            return null;
        }

        $question->loadMissing('category');

        $imageUrl = $question->image;
        if (!empty($imageUrl)) {
            $imageUrl = $this->formatImageUrl($imageUrl);
        }

        // Çoklu dil desteği - tr ve en
        $questionTr = $question->getTranslation('question', 'tr');
        $questionEn = $question->getTranslation('question', 'en');

        $choicesTr = [
            '1' => $question->getTranslation('one_choice', 'tr'),
            '2' => $question->getTranslation('two_choice', 'tr'),
            '3' => $question->getTranslation('three_choice', 'tr'),
            '4' => $question->getTranslation('four_choice', 'tr'),
        ];

        $choicesEn = [
            '1' => $question->getTranslation('one_choice', 'en'),
            '2' => $question->getTranslation('two_choice', 'en'),
            '3' => $question->getTranslation('three_choice', 'en'),
            '4' => $question->getTranslation('four_choice', 'en'),
        ];

        $categoryNameTr = null;
        $categoryNameEn = null;
        if ($question->category) {
            $categoryNameTr = $question->category->getTranslation('name', 'tr');
            $categoryNameEn = $question->category->getTranslation('name', 'en');
        }

        return [
            'id' => $question->id,
            'question' => [
                'tr' => $questionTr,
                'en' => $questionEn,
            ],
            'choices' => [
                'tr' => $choicesTr,
                'en' => $choicesEn,
            ],
            'question_level' => $question->question_level,
            'coin_value' => $question->coin_value,
            'image' => $imageUrl,
            'category' => $question->category ? [
                'id' => $question->category->id,
                'name' => [
                    'tr' => $categoryNameTr,
                    'en' => $categoryNameEn,
                ],
            ] : null,
        ];
    }

    /**
     * Görsel URL'ini tam URL'e çevir
     */
    private function formatImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        if (strpos($imagePath, 'storage/questions/') !== false) {
            $imagePath = str_replace('storage/questions/', 'questions/', $imagePath);
        }

        if (strpos($imagePath, 'questions/') !== 0) {
            $imagePath = 'questions/' . ltrim($imagePath, '/');
        }

        $baseUrl = config('app.url', 'https://bilbakalim.online');
        return rtrim($baseUrl, '/') . '/storage/' . $imagePath;
    }

    /**
     * Socket webhook'ları
     */
    private function sendDuelCreatedWebhook(Duel $duel): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-created", [
                'duel_id' => $duel->id,
                'challenger_id' => $duel->challenger_id,
                'opponent_id' => $duel->opponent_id,
                'multiplier' => $duel->multiplier,
                'question_value' => $duel->question_value,
                'requires_acceptance' => $duel->multiplier !== 'x1',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send duel created webhook', ['error' => $e->getMessage()]);
        }
    }

    private function sendDuelStartedWebhook(Duel $duel, ?Question $question): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-started", [
                'duel_id' => $duel->id,
                'challenger_id' => $duel->challenger_id,
                'opponent_id' => $duel->opponent_id,
                'question' => $question ? $this->formatQuestionMultilingual($question) : null,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send duel started webhook', ['error' => $e->getMessage()]);
        }
    }

    private function sendDuelAnswerWebhook(Duel $duel, User $user, bool $isCorrect, bool $bothAnswered): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-answer", array_merge([
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'challenger_id' => $duel->challenger_id,
                'opponent_id' => $duel->opponent_id,
                'is_correct' => $isCorrect,
                'both_answered' => $bothAnswered,
                'timestamp' => now()->toISOString()
            ], $this->correctAnswerRevealForQuestion($duel->currentQuestion, $isCorrect)));
        } catch (\Exception $e) {
            Log::error('Failed to send duel answer webhook', ['error' => $e->getMessage()]);
        }
    }

    private function sendDuelNextQuestionWebhook(Duel $duel, Question $question): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-next-question", [
                'duel_id' => $duel->id,
                'challenger_id' => $duel->challenger_id,
                'opponent_id' => $duel->opponent_id,
                'question' => $this->formatQuestionMultilingual($question),
                'question_number' => $duel->current_question_number,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send duel next question webhook', ['error' => $e->getMessage()]);
        }
    }

    private function sendDuelFinishedWebhook(Duel $duel): void
    {
        try {
            $duel->refresh()->load(['challenger', 'opponent', 'answers']);
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-finished", array_merge(
                $this->buildDuelFinishedPayload($duel),
                ['timestamp' => now()->toISOString()]
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send duel finished webhook', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Düello bitiş popup'ı için özet istatistikler (duel_answers + duels).
     */
    private function buildDuelFinishedPayload(Duel $duel): array
    {
        $answers = $duel->answers;

        $challengerStats = $this->buildDuelPlayerStats(
            $duel->challenger_id,
            $answers,
            (int) $duel->challenger_coins_before,
            (int) $duel->challenger_coins_after
        );

        $opponentStats = $duel->opponent_id
            ? $this->buildDuelPlayerStats(
                $duel->opponent_id,
                $answers,
                (int) $duel->opponent_coins_before,
                (int) $duel->opponent_coins_after
            )
            : null;

        $players = [$challengerStats];
        if ($opponentStats) {
            $players[] = $opponentStats;
        }

        return [
            'duel_id' => $duel->id,
            'duelId' => $duel->id,
            'winner_id' => $duel->winner_id,
            'winnerId' => $duel->winner_id,
            'challenger_id' => $duel->challenger_id,
            'challengerId' => $duel->challenger_id,
            'opponent_id' => $duel->opponent_id,
            'opponentId' => $duel->opponent_id,
            'multiplier' => $duel->multiplier,
            'total_questions' => (int) $duel->current_question_number,
            'totalQuestions' => (int) $duel->current_question_number,
            'challenger' => array_merge(
                $this->formatDuelPlayerIdentity($duel->challenger),
                $challengerStats
            ),
            'opponent' => $duel->opponent
                ? array_merge(
                    $this->formatDuelPlayerIdentity($duel->opponent),
                    $opponentStats
                )
                : null,
            'players' => collect($players)->keyBy('userId')->all(),
        ];
    }

    private function formatDuelPlayerIdentity(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ];
    }

    private function buildDuelPlayerStats(
        int $userId,
        $answers,
        int $coinsBefore,
        int $coinsAfter
    ): array {
        $userAnswers = $answers->where('user_id', $userId);
        $totalAnswered = $userAnswers->count();
        $correctCount = $userAnswers->where('is_correct', true)->count();
        $wrongCount = $totalAnswered - $correctCount;

        $coinsGained = (int) $userAnswers->where('coins_change', '>', 0)->sum('coins_change');
        $coinsLost = (int) abs($userAnswers->where('coins_change', '<', 0)->sum('coins_change'));
        $netCoinsChange = (int) $userAnswers->sum('coins_change');

        return [
            'userId' => $userId,
            'user_id' => $userId,
            'total_answered' => $totalAnswered,
            'totalAnswered' => $totalAnswered,
            'correct_count' => $correctCount,
            'correctCount' => $correctCount,
            'wrong_count' => $wrongCount,
            'wrongCount' => $wrongCount,
            'coins_gained' => $coinsGained,
            'coinsGained' => $coinsGained,
            'coins_lost' => $coinsLost,
            'coinsLost' => $coinsLost,
            'net_coins_change' => $netCoinsChange,
            'netCoinsChange' => $netCoinsChange,
            'coins_before' => $coinsBefore,
            'coinsBefore' => $coinsBefore,
            'coins_after' => $coinsAfter,
            'coinsAfter' => $coinsAfter,
        ];
    }

    /**
     * Soru bazlı çarpan teklifi webhook'u
     */
    private function sendDuelQuestionBetRequestedWebhook(Duel $duel, array $bet): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-question-bet-requested", [
                'duel_id' => $duel->id,
                'question_id' => $bet['question_id'] ?? null,
                'initiator_id' => $bet['initiator_id'] ?? null,
                'opponent_id' => $bet['opponent_id'] ?? null,
                'multiplier' => $bet['multiplier'] ?? null,
                'status' => $bet['status'] ?? 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('Duel question bet requested webhook error', [
                'duel_id' => $duel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Soru bazlı çarpan teklifi cevabı webhook'u
     */
    private function sendDuelQuestionBetRespondedWebhook(Duel $duel, array $bet): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-question-bet-responded", [
                'duel_id' => $duel->id,
                'question_id' => $bet['question_id'] ?? null,
                'initiator_id' => $bet['initiator_id'] ?? null,
                'opponent_id' => $bet['opponent_id'] ?? null,
                'multiplier' => $bet['multiplier'] ?? null,
                'status' => $bet['status'] ?? null,
                'accepted' => ($bet['status'] ?? null) === 'accepted',
            ]);
        } catch (\Throwable $e) {
            Log::error('Duel question bet responded webhook error', [
                'duel_id' => $duel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kaybedenden kazanana coin aktar (maç içi tam tutar).
     * %10 uygulama komisyonu maç sonunda finishDuel içinde kesilir.
     *
     * @return array{taken:int, received:int, commission:int}
     */
    private function transferCoins(User $from, User $to, int $amount, ?Duel $duel = null): array
    {
        $taken = $this->subtractCoins($from, $amount, $duel, 'Düello: soru kaybı (rakibe transfer)');
        if ($taken <= 0) {
            return ['taken' => 0, 'received' => 0, 'commission' => 0];
        }

        $this->addCoins($to, $taken, $duel, 'Düello: soru kazancı (rakipten transfer)');

        return [
            'taken' => $taken,
            'received' => $taken,
            'commission' => 0,
        ];
    }

    private function addCoins(User $user, int $amount, ?Duel $duel = null, string $description = ''): void
    {
        if ($amount <= 0) {
            return;
        }

        $balanceBefore = (int) $user->coins;
        $user->increment('coins', $amount);

        if ($duel) {
            $user->increment('duel_earned_coins', $amount);
            CoinHistory::create([
                'user_id' => $user->id,
                'coin_amount' => $amount,
                'transaction_type' => 'duel',
                'status' => 'completed',
                'description' => $description !== '' ? $description : 'Düello coin kazancı',
                'metadata' => [
                    'duel_id' => $duel->id,
                    'multiplier' => $duel->multiplier ?? null,
                    'question_multiplier' => ($duel->settings['current_question_multiplier'] ?? 1),
                    'source' => 'duel',
                ],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $amount,
            ]);
        }

        $user->refresh();
    }

    private function subtractCoins(User $user, int $amount, ?Duel $duel = null, string $description = ''): int
    {
        $amount = min(max(0, $amount), (int) $user->coins);
        if ($amount <= 0) {
            return 0;
        }

        $balanceBefore = (int) $user->coins;
        $user->decrement('coins', $amount);

        if ($duel) {
            CoinHistory::create([
                'user_id' => $user->id,
                'coin_amount' => -$amount,
                'transaction_type' => 'duel',
                'status' => 'completed',
                'description' => $description !== '' ? $description : 'Düello coin kaybı',
                'metadata' => [
                    'duel_id' => $duel->id,
                    'multiplier' => $duel->multiplier ?? null,
                    'question_multiplier' => ($duel->settings['current_question_multiplier'] ?? 1),
                    'source' => 'duel',
                ],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $amount,
            ]);
        }

        $user->refresh();

        return $amount;
    }

    /**
     * Aynı çarpanda bekleyen en eski açık düelloya katıl (FIFO kuyruk).
     */
    private function joinOldestWaitingDuel(User $user, string $multiplier): ?JsonResponse
    {
        $waitingDuel = Duel::where('status', 'waiting')
            ->whereNull('opponent_id')
            ->where('multiplier', $multiplier)
            ->where('challenger_id', '!=', $user->id)
            ->whereHas('challenger', function ($query) {
                $query->where('coins', '>', 0);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$waitingDuel) {
            return null;
        }

        $waitingDuel->opponent_id = $user->id;
        $waitingDuel->opponent_coins_before = (int) $user->coins;
        $waitingDuel->status = 'active';
        $waitingDuel->started_at = now();
        $waitingDuel->save();

        $firstQuestion = $this->getNextQuestion($waitingDuel);
        if ($firstQuestion) {
            $waitingDuel->update([
                'current_question_id' => $firstQuestion->id,
                'current_question_number' => 1,
            ]);
        }

        $this->sendDuelStartedWebhook($waitingDuel, $firstQuestion);

        return response()->json([
            'success' => true,
            'message' => 'Düello başladı!',
            'duel' => $waitingDuel->load(['challenger', 'opponent', 'currentQuestion']),
            'question' => $firstQuestion ? $this->formatQuestionMultilingual($firstQuestion) : null,
            'auto_started' => true,
            'matched_from_queue' => true,
        ]);
    }

    /**
     * Socket'e bağlı, aktif düellosu olmayan rastgele rakip bul.
     * Son 5 rakibi tekrar seçmemeye çalışır.
     */
    private function findOnlineOpponent(User $user): ?User
    {
        $recentOpponentIds = Duel::where('status', 'finished')
            ->where(function ($query) use ($user) {
                $query->where('challenger_id', $user->id)
                    ->orWhere('opponent_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Duel $duel) use ($user) {
                return $duel->challenger_id === $user->id
                    ? $duel->opponent_id
                    : $duel->challenger_id;
            })
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $candidates = User::where('id', '!=', $user->id)
            ->where('coins', '>', 0)
            ->whereDoesntHave('duelsAsChallenger', function ($query) {
                $query->whereIn('status', ['waiting', 'active']);
            })
            ->whereDoesntHave('duelsAsOpponent', function ($query) {
                $query->whereIn('status', ['waiting', 'active']);
            })
            ->when(!empty($recentOpponentIds), function ($query) use ($recentOpponentIds) {
                $query->whereNotIn('id', $recentOpponentIds);
            })
            ->inRandomOrder()
            ->limit(40)
            ->get();

        // Recent filter sonrası aday kalmadıysa recent hariç tutmadan tekrar dene
        if ($candidates->isEmpty() && !empty($recentOpponentIds)) {
            $candidates = User::where('id', '!=', $user->id)
                ->where('coins', '>', 0)
                ->whereDoesntHave('duelsAsChallenger', function ($query) {
                    $query->whereIn('status', ['waiting', 'active']);
                })
                ->whereDoesntHave('duelsAsOpponent', function ($query) {
                    $query->whereIn('status', ['waiting', 'active']);
                })
                ->inRandomOrder()
                ->limit(40)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $webhookService = app(\App\Http\Services\WebhookService::class);
        $connectionStatus = $webhookService->checkUsersConnection($candidates->pluck('id')->all());

        $onlineCandidates = $candidates->filter(function (User $candidate) use ($connectionStatus) {
            $status = $connectionStatus[$candidate->id]
                ?? $connectionStatus[(string) $candidate->id]
                ?? null;

            return ($status['isConnected'] ?? false) === true;
        })->values();

        if ($onlineCandidates->isEmpty()) {
            return null;
        }

        return $onlineCandidates->random();
    }
}
