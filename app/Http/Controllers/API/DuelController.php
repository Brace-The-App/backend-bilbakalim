<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Duel;
use App\Models\DuelAnswer;
use App\Models\Diamond;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DuelController extends Controller
{
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
     *             @OA\Property(property="message", type="string", example="Düello için elmas paketi gereklidir.")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'multiplier' => 'required|in:x1,x2,x4,x8',
        ]);

        $user = Auth::user();

        // Elmas paketi kontrolü
        $diamond = Diamond::where('user_id', $user->id)->first();
        if (!$diamond || $diamond->balance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Düello için elmas paketi gereklidir. Lütfen elmas satın alın.'
            ], 400);
        }

        // Aktif düello kontrolü
        $activeDuel = Duel::where(function($query) use ($user) {
            $query->where('challenger_id', $user->id)
                  ->orWhere('opponent_id', $user->id);
        })
        ->whereIn('status', ['waiting', 'active'])
        ->first();

        if ($activeDuel) {
            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir düellonuz var.',
                'duel' => $activeDuel
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Rastgele rakip seç (elmas bakiyesi olan kullanıcılar arasından)
            $opponent = User::where('id', '!=', $user->id)
                ->whereHas('diamond', function($query) {
                    $query->where('balance', '>', 0);
                })
                ->inRandomOrder()
                ->first();

            if (!$opponent) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Uygun rakip bulunamadı. Lütfen daha sonra tekrar deneyin.'
                ], 404);
            }

            $opponentDiamond = Diamond::where('user_id', $opponent->id)->first();

            // Düello oluştur
            $duel = Duel::create([
                'challenger_id' => $user->id,
                'opponent_id' => $opponent->id,
                'multiplier' => $request->multiplier,
                'status' => 'waiting',
                'challenger_diamonds_before' => $diamond->balance,
                'opponent_diamonds_before' => $opponentDiamond->balance,
            ]);

            // X1 ise otomatik başlat, X2/X4/X8 ise karşı tarafa istek gönder
            if ($request->multiplier === 'x1') {
                // X1: Otomatik başlat
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

                // Socket bildirimi gönder (başladı)
                $this->sendDuelStartedWebhook($duel, $firstQuestion);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Düello başladı!',
                    'duel' => $duel->load(['challenger', 'opponent', 'currentQuestion']),
                    'question' => $firstQuestion ? $this->formatQuestionMultilingual($firstQuestion) : null,
                    'auto_started' => true
                ]);
            } else {
                // X2/X4/X8: Karşı tarafa istek gönder (waiting durumunda kal)
                $this->sendDuelCreatedWebhook($duel);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Düello isteği gönderildi. Rakip onay bekleniyor...',
                    'duel' => $duel->load(['challenger', 'opponent']),
                    'opponent' => [
                        'id' => $opponent->id,
                        'name' => $opponent->name,
                        'avatar' => $opponent->avatar,
                    ],
                    'requires_acceptance' => true
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Duel creation error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Düello oluşturulurken bir hata oluştu.'
            ], 500);
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

        $challengerDiamond = Diamond::where('user_id', $duel->challenger_id)->first();
        $opponentDiamond = Diamond::where('user_id', $duel->opponent_id)->first();

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
                    'diamonds_before' => $duel->challenger_diamonds_before,
                    'diamonds_after' => $duel->challenger_diamonds_after,
                    'current_diamonds' => $challengerDiamond->balance ?? 0,
                ],
                'opponent' => [
                    'id' => $duel->opponent->id,
                    'name' => $duel->opponent->name,
                    'avatar' => $duel->opponent->avatar,
                    'diamonds_before' => $duel->opponent_diamonds_before,
                    'diamonds_after' => $duel->opponent_diamonds_after,
                    'current_diamonds' => $opponentDiamond->balance ?? 0,
                ],
                'current_question' => $currentQuestion,
                'winner_id' => $duel->winner_id,
                'started_at' => $duel->started_at,
                'finished_at' => $duel->finished_at,
            ]
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
     *             @OA\Property(property="diamonds_transferred", type="integer", example=18)
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
            $challengerDiamond = Diamond::where('user_id', $duel->challenger_id)->first();
            $opponentDiamond = Diamond::where('user_id', $duel->opponent_id)->first();

            // Soru değeri (multiplier ile çarpılmış)
            $questionValue = $duel->question_value; // 10 * multiplier

            // Reddeden (opponent) kaybeder, isteği gönderen (challenger) kazanır
            $opponentLoss = min($questionValue, $opponentDiamond->balance);
            
            if ($opponentLoss > 0) {
                $opponentDiamond->subtract($opponentLoss);
                $challengerDiamond->add($opponentLoss);
            }

            // Düello bitir - challenger kazandı
            // Kazananın elmaslarından %10 komisyon kes (finishDuel metodunda)
            $this->finishDuel($duel, $duel->challenger_id);

            // Socket bildirimi gönder
            $this->sendDuelFinishedWebhook($duel);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Düello reddedildi. İsteği gönderen kazandı.',
                'duel' => $duel->load(['challenger', 'opponent', 'winner']),
                'winner_id' => $duel->challenger_id,
                'diamonds_transferred' => $opponentLoss - $commission
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
            // Kazanan belirle (çekilenin rakibi)
            $winnerId = $duel->challenger_id === $user->id 
                ? $duel->opponent_id 
                : $duel->challenger_id;

            // Düello bitir - Kazananın elmaslarından %10 komisyon kes
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
     *     description="Düello sorusuna cevap gönderir. Her iki oyuncu da cevap verdiğinde elmas transferi yapılır.",
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
            'selected_answer' => 'required|in:1,2,3,4',
        ]);

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
            $isCorrect = $request->selected_answer === $question->correct_answer;
            $questionValue = $duel->question_value; // 10 * multiplier

            $userDiamond = Diamond::where('user_id', $user->id)->first();
            $diamondsBefore = $userDiamond->balance;

            // Cevap kaydı oluştur (henüz elmas transferi yapma)
            $answer = DuelAnswer::create([
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'question_id' => $question->id,
                'selected_answer' => $request->selected_answer,
                'is_correct' => $isCorrect,
                'question_value' => $questionValue,
                'diamonds_before' => $diamondsBefore,
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
                // Her iki oyuncu da cevap verdi, elmas transferi yap
                $this->processAnswers($duel, $challengerAnswer, $opponentAnswer, $question, $questionValue);

                // Sonraki soruya geç veya düello bitir
                $this->moveToNextQuestion($duel);
            }

            DB::commit();

            // Socket bildirimi gönder
            $this->sendDuelAnswerWebhook($duel, $user, $isCorrect, $bothAnswered);

            return response()->json([
                'success' => true,
                'is_correct' => $isCorrect,
                'correct_answer' => $question->correct_answer,
                'both_answered' => $bothAnswered,
                'waiting_for_opponent' => !$bothAnswered,
            ]);

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
     * Cevapları işle ve elmas transferi yap
     */
    private function processAnswers(Duel $duel, DuelAnswer $challengerAnswer, DuelAnswer $opponentAnswer, Question $question, int $questionValue): void
    {
        $challengerDiamond = Diamond::where('user_id', $duel->challenger_id)->first();
        $opponentDiamond = Diamond::where('user_id', $duel->opponent_id)->first();

        $challengerCorrect = $challengerAnswer->is_correct;
        $opponentCorrect = $opponentAnswer->is_correct;

        // Senaryo 1: Her ikisi de doğru → Bakiyeler değişmez
        if ($challengerCorrect && $opponentCorrect) {
            $challengerAnswer->update(['diamonds_change' => 0, 'diamonds_after' => $challengerDiamond->balance]);
            $opponentAnswer->update(['diamonds_change' => 0, 'diamonds_after' => $opponentDiamond->balance]);
            return;
        }

        // Senaryo 2: Her ikisi de yanlış → Bakiyeler düşer, uygulama kasasına aktarılır
        if (!$challengerCorrect && !$opponentCorrect) {
            $challengerLoss = min($questionValue, $challengerDiamond->balance);
            $opponentLoss = min($questionValue, $opponentDiamond->balance);
            $totalLoss = $challengerLoss + $opponentLoss;

            if ($challengerLoss > 0) {
                $challengerDiamond->subtract($challengerLoss);
            }
            if ($opponentLoss > 0) {
                $opponentDiamond->subtract($opponentLoss);
            }

            // Uygulama komisyonu (her iki oyuncu yanlış cevap verdiğinde)
            $commission = (int) ($totalLoss * 0.1); // %10 komisyon
            $duel->increment('app_commission', $commission);

            $challengerAnswer->update([
                'diamonds_change' => -$challengerLoss,
                'diamonds_after' => $challengerDiamond->balance
            ]);
            $opponentAnswer->update([
                'diamonds_change' => -$opponentLoss,
                'diamonds_after' => $opponentDiamond->balance
            ]);
            return;
        }

        // Senaryo 3: Biri doğru, diğeri yanlış → Yanlış cevap verenin elması doğru cevap verene aktarılır
        if ($challengerCorrect && !$opponentCorrect) {
            // Rakip yanlış, meydan okuyan doğru
            $opponentLoss = min($questionValue, $opponentDiamond->balance);
            if ($opponentLoss > 0) {
                $opponentDiamond->subtract($opponentLoss);
                $challengerDiamond->add($opponentLoss);
            }

            $challengerAnswer->update([
                'diamonds_change' => $opponentLoss,
                'diamonds_after' => $challengerDiamond->balance
            ]);
            $opponentAnswer->update([
                'diamonds_change' => -$opponentLoss,
                'diamonds_after' => $opponentDiamond->balance
            ]);
        } elseif (!$challengerCorrect && $opponentCorrect) {
            // Meydan okuyan yanlış, rakip doğru
            $challengerLoss = min($questionValue, $challengerDiamond->balance);
            if ($challengerLoss > 0) {
                $challengerDiamond->subtract($challengerLoss);
                $opponentDiamond->add($challengerLoss);
            }

            $challengerAnswer->update([
                'diamonds_change' => -$challengerLoss,
                'diamonds_after' => $challengerDiamond->balance
            ]);
            $opponentAnswer->update([
                'diamonds_change' => $challengerLoss,
                'diamonds_after' => $opponentDiamond->balance
            ]);
        }
    }

    /**
     * Sonraki soruya geç veya düello bitir
     */
    private function moveToNextQuestion(Duel $duel): void
    {
        $challengerDiamond = Diamond::where('user_id', $duel->challenger_id)->first();
        $opponentDiamond = Diamond::where('user_id', $duel->opponent_id)->first();

        // Bakiye kontrolü - biri sıfırlandı mı?
        if ($challengerDiamond->balance <= 0 || $opponentDiamond->balance <= 0) {
            // Düello bitir
            $winnerId = $challengerDiamond->balance > 0 ? $duel->challenger_id : $duel->opponent_id;
            $this->finishDuel($duel, $winnerId);
            return;
        }

        // Sonraki soruya geç
        $nextQuestion = $this->getNextQuestion($duel);
        if ($nextQuestion) {
            $duel->update([
                'current_question_id' => $nextQuestion->id,
                'current_question_number' => $duel->current_question_number + 1,
            ]);

            // Socket bildirimi gönder
            $this->sendDuelNextQuestionWebhook($duel, $nextQuestion);
        } else {
            // Soru kalmadı, düello bitir (daha fazla elması olan kazanır)
            $winnerId = $challengerDiamond->balance >= $opponentDiamond->balance 
                ? $duel->challenger_id 
                : $duel->opponent_id;
            $this->finishDuel($duel, $winnerId);
        }
    }

    /**
     * Düello bitir
     * Kazananın elmaslarından %10 komisyon kesilir
     */
    private function finishDuel(Duel $duel, int $winnerId): void
    {
        $challengerDiamond = Diamond::where('user_id', $duel->challenger_id)->first();
        $opponentDiamond = Diamond::where('user_id', $duel->opponent_id)->first();

        // Kazananın elmaslarını güncelle (komisyon kesilir)
        // Her şart ve koşulda kazananın toplam elmasından %10 komisyon kesilir
        $winnerDiamond = Diamond::where('user_id', $winnerId)->first();
        $winnerBalance = $winnerDiamond->balance;
        
        // Komisyon hesapla (%10)
        $commission = (int) ($winnerBalance * 0.1);
        $finalBalance = $winnerBalance - $commission;

        // Komisyonu kes
        if ($commission > 0) {
            $winnerDiamond->update(['balance' => $finalBalance]);
            $duel->increment('app_commission', $commission);
        }

        // Düello bitiş bilgilerini güncelle
        $duel->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
            'finished_at' => now(),
            'challenger_diamonds_after' => $challengerDiamond->balance,
            'opponent_diamonds_after' => $opponentDiamond->balance,
        ]);
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
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-answer", [
                'duel_id' => $duel->id,
                'user_id' => $user->id,
                'is_correct' => $isCorrect,
                'both_answered' => $bothAnswered,
                'timestamp' => now()->toISOString()
            ]);
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
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/duel-finished", [
                'duel_id' => $duel->id,
                'winner_id' => $duel->winner_id,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send duel finished webhook', ['error' => $e->getMessage()]);
        }
    }
}
