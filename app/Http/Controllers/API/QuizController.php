<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\IndividualGame;
use App\Models\GameAnswer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Services\WebhookService;
use Carbon\Carbon;

class QuizController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/quiz/normal/start",
     *     summary="Normal Quiz Başlat",
     *     description="Sonsuz mod normal quiz oyununu başlatır. İlk 10 soru kolay, sonrakiler rastgele zorlukta.",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Quiz başarıyla başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Normal quiz başlatıldı."),
     *             @OA\Property(property="game", type="object"),
     *             @OA\Property(property="question", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Zaten aktif bir oyun var",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Zaten aktif bir oyununuz var.")
     *         )
     *     )
     * )
     */
    public function startNormalQuiz(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Aktif oyun var mı kontrol et
        $activeGame = IndividualGame::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('game_type', 'normal')
            ->first();

        if ($activeGame) {
            // Aktif oyunun settings içindeki jokerleri User tablosundaki güncel değerlerle senkronize et
            $settings = $activeGame->settings ?? [];
            $settings['jokers'] = [
                'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                'double_answer' => $user->double_answer_jokers ?? 0,
                'hint' => $user->hint_jokers ?? 0
            ];

            // Settings'i güncelle
            $activeGame->update(['settings' => $settings]);
            $activeGame->refresh();

            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir oyununuz var.',
                'game' => null,
                'question' => null,
                'active_game' => $activeGame,
                'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
            ], 200);
        }

        // Kullanıcının mevcut joker sayılarını al, yoksa başlangıç jokerlerini ver
        $userJokers = [
            'fifty_fifty' => $user->fifty_fifty_jokers ?? config('app.joker_counts.fifty_fifty', 1),
            'double_answer' => $user->double_answer_jokers ?? config('app.joker_counts.double_answer', 1),
            'hint' => $user->hint_jokers ?? config('app.joker_counts.hint', 1)
        ];

        // Eğer kullanıcının jokerleri yoksa, başlangıç jokerlerini ver
        if (is_null($user->fifty_fifty_jokers)) {
            $user->update([
                'fifty_fifty_jokers' => config('app.joker_counts.fifty_fifty', 1),
                'double_answer_jokers' => config('app.joker_counts.double_answer', 1),
                'hint_jokers' => config('app.joker_counts.hint', 1)
            ]);
        }

        // Yeni oyun oluştur
        // Kullanıcının güncel joker sayılarını al (yoksa 0 olarak başlat)
        $user->refresh(); // User'ı yeniden yükle
        $game = IndividualGame::create([
            'user_id' => $user->id,
            'game_type' => 'normal',
            'difficulty_level' => 'mixed',
            'question_count' => 0, // Sonsuz mod
            'time_limit_seconds' => null, // Süre sınırı yok
            'joker_count' => 3, // 3 joker hakkı
            'score' => 0,
            'correct_answers' => 0,
            'wrong_answers' => 0,
            'coins_earned' => 0,
            'status' => 'active',
            'started_at' => now(),
            'settings' => [
                'easy_questions_count' => 0,
                'current_difficulty' => 'easy',
                'jokers_used' => [],
                'jokers' => [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ]
            ]
        ]);

        // İlk soruyu getir (kolay seviye)
        $question = $this->getNextQuestion($game);

        // Socket.IO'ya quiz başlatma bildirimi gönder
        \Log::info('Quiz başlatma webhook gönderiliyor', [
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'game_type' => 'normal'
        ]);
        $this->broadcastQuizStarted($game, $question);

        // Soruyu çoklu dil formatında formatla
        $questionData = $question ? $this->formatQuestionMultilingual($question) : null;

        return response()->json([
            'success' => true,
            'message' => 'Normal quiz başlatıldı.',
            'game' => $game,
            'question' => $questionData,
            'active_game' => null,
            'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/answer",
     *     summary="Normal Quiz Cevap Gönder",
     *     description="Normal quiz oyununda soruya cevap gönderir.",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="game_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="selected_option", type="string", example="2"),
     *                 @OA\Property(property="time_spent", type="integer", example=30)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="game_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="selected_option", type="string", example="2"),
     *             @OA\Property(property="time_spent", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cevap başarıyla gönderildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="correct_option", type="string", example="2"),
     *             @OA\Property(property="earned_coins", type="integer", example=50),
     *             @OA\Property(property="game_stats", type="object"),
     *             @OA\Property(property="next_question", type="object")
     *         )
     *     )
     * )
     */
    public function submitAnswer(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id',
            'question_id' => 'required|exists:questions,id',
            'selected_option' => 'nullable|in:1,2,3,4,5', // 5 = süre bitti, cevap verilmedi
            'time_spent' => 'nullable|integer|min:0',
            'joker_used' => 'nullable|in:fifty_fifty,double_answer,hint',
            'second_option' => 'nullable|in:1,2,3,4' // Çift cevap için ikinci seçenek
        ]);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun bulunamadı.'
            ], 404);
        }

        // Normal Quiz'de süre sınırı yok

        $question = Question::find($request->question_id);

        // Eğer selected_option 5 ise veya null/boş ise (süre doldu), yanlış olarak işaretle
        $selectedOption = $request->selected_option;
        if ($request->selected_option == '5' || $request->selected_option === 5 || empty($selectedOption) || $selectedOption === null || $selectedOption === '') {
            $isCorrect = false;
            $selectedOption = null; // 5 geldiğinde null olarak kaydet
        } else {
            // Joker kullanımı kontrolü
            $jokerUsed = null;
            if ($request->joker_used) {
                $settings = $game->settings ?? [];
                
                // Eğer jokers anahtarı yoksa, kullanıcının güncel joker değerlerini al
                if (!isset($settings['jokers']) || !is_array($settings['jokers'])) {
                    $settings['jokers'] = [
                        'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                        'double_answer' => $user->double_answer_jokers ?? 0,
                        'hint' => $user->hint_jokers ?? 0
                    ];
                }
                
                if (isset($settings['jokers'][$request->joker_used]) && $settings['jokers'][$request->joker_used] > 0) {
                    $settings['jokers'][$request->joker_used]--;
                    $jokerUsed = $request->joker_used;
                    $game->update(['settings' => $settings]);
                }
            }

            // Çift cevap joker kontrolü
            if ($jokerUsed === 'double_answer' && $request->second_option) {
                // Çift cevap: iki seçenekten biri doğru olmalı
                // Tip uyumsuzluğunu önlemek için string'e çevir
                $correctAnswer = (string) $question->correct_answer;
                $selectedOptionStr = (string) $selectedOption;
                $secondOptionStr = (string) $request->second_option;
                $isCorrect = ($correctAnswer === $selectedOptionStr) ||
                    ($correctAnswer === $secondOptionStr);
            } else {
                // Normal cevap - Tip uyumsuzluğunu önlemek için string'e çevir
                $isCorrect = (string) $question->correct_answer === (string) $selectedOption;
            }
        }

        $jokerUsed = $jokerUsed ?? null;

        $timeSpent = $request->time_spent ?? 30;

        // Cevabı kaydet
        GameAnswer::create([
            'individual_game_id' => $game->id,
            'game_session_id' => null, // Normal quiz için null
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option' => $selectedOption,
            'is_correct' => $isCorrect,
            'time_spent' => $timeSpent,
            'joker_used' => $jokerUsed,
            'answered_at' => now(),
            'user_answer' => ($jokerUsed === 'double_answer' && $selectedOption && $request->second_option) ?
                $selectedOption . ',' . $request->second_option :
                ($selectedOption ?? null)
        ]);

        // Oyun istatistiklerini güncelle
        $coinsChange = $isCorrect ? $question->coin_value : -$question->coin_value;

        $settings = $game->settings ?? [];
        $settings['total_questions_answered'] = ($settings['total_questions_answered'] ?? 0) + 1;

        // Cevaplanan soru ID'sini listeye ekle (tekrar sorulmasını önlemek için)
        if (!isset($settings['answered_question_ids'])) {
            $settings['answered_question_ids'] = [];
        }
        if (!in_array($question->id, $settings['answered_question_ids'])) {
            $settings['answered_question_ids'][] = $question->id;
        }

        // Yanlış cevap durumunda coin kontrolü yap
        if (!$isCorrect) {
            // Kullanıcının mevcut coin'ini kontrol et
            $user->refresh(); // Güncel coin değerini al
            $userCoins = $user->coins;
            $coinDeduction = abs($coinsChange); // Coin düşüş miktarı (pozitif değer)

            // Eğer kullanıcının coini yeterli değilse veya 5 coin ve altındaysa, reklam/coin satın alma seçeneği sun
            if ($userCoins < $coinDeduction || $userCoins <= 5) {
                // Sonraki soruyu getir (kontrol için)
                $nextQuestion = $this->getNextQuestion($game);
                $nextQuestionCoinValue = $nextQuestion ? $nextQuestion->coin_value : 0;

                // Eğer bir sonraki soru için yeterli coin yoksa (5 coin ve altı) veya mevcut soru için coin yoksa
                if ($userCoins < $coinDeduction || ($nextQuestion && $userCoins <= 5 && $userCoins < $nextQuestionCoinValue)) {
                    // Coin yeterli değil, reklam/coin satın alma seçeneği sun
        $game->update([
            'question_count' => $game->question_count + 1,
                        'correct_answers' => $game->correct_answers,
                        'wrong_answers' => $game->wrong_answers + 1,
            'coins_earned' => $game->coins_earned + $coinsChange,
            'total_time_seconds' => $game->total_time_seconds + $timeSpent,
            'settings' => $settings
        ]);

                    // Kullanıcının coin'ini güncelle (eksiye gitmemesi için max(0, ...) kullan)
                    $finalCoins = max(0, $userCoins - $coinDeduction);
                    $user->update(['coins' => $finalCoins]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Yeterli jetonunuz yok. Reklam izleyerek veya jeton satın alarak devam edebilirsiniz.',
                        'is_correct' => false,
                        'earned_coins' => $coinsChange,
                        'requires_coin_purchase' => true,
                        'requires_ad_watch' => true,
                        'user_coins' => $finalCoins,
                        'required_coins' => $coinDeduction,
                        'next_question_coin_value' => $nextQuestionCoinValue,
                        'game_stats' => [
                            'total_questions' => $game->question_count,
                            'correct_answers' => $game->correct_answers,
                            'wrong_answers' => $game->wrong_answers,
                            'total_coins' => $game->coins_earned,
                            'user_coins' => $finalCoins
                        ]
                    ]);
                }
            }

            // Coin yeterli ama eksiye gitmemesi için kontrol et
            $finalCoins = max(0, $userCoins + $coinsChange);
            $user->update(['coins' => $finalCoins]);
        } else {
            // Doğru cevap - coin ekle
        $user->increment('coins', $coinsChange);
        }

        $game->update([
            'question_count' => $game->question_count + 1,
            'correct_answers' => $isCorrect ? $game->correct_answers + 1 : $game->correct_answers,
            'wrong_answers' => !$isCorrect ? $game->wrong_answers + 1 : $game->wrong_answers,
            'coins_earned' => $game->coins_earned + $coinsChange,
            'total_time_seconds' => $game->total_time_seconds + $timeSpent,
            'settings' => $settings
        ]);

        // Socket.IO'ya cevap bildirimi gönder
        \Log::info('Quiz cevap webhook gönderiliyor', [
            'game_id' => $game->id,
            'user_id' => $user->id,
            'question_id' => $question->id,
            'is_correct' => $isCorrect,
            'coins_earned' => $coinsChange
        ]);
        $this->broadcastQuizAnswer($game, $user, $question, $isCorrect, $coinsChange);

        // Sonraki soruyu getir
        $nextQuestion = $this->getNextQuestion($game);

        // Soruyu çoklu dil formatında formatla
        $nextQuestionData = $nextQuestion ? $this->formatQuestionMultilingual($nextQuestion) : null;

        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'earned_coins' => $coinsChange,
            'joker_used' => $jokerUsed,
            'game_stats' => [
                'total_questions' => $game->question_count,
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers,
                'total_coins' => $game->coins_earned,
                'user_coins' => $user->coins,
                'jokers_remaining' => $game->settings['jokers'] ?? []
            ],
            'next_question' => $nextQuestionData
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/end",
     *     summary="Normal Quiz Oyunu Bitir",
     *     description="Normal quiz oyununu bitirir ve sonuçları döner.",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="game_id", type="integer", example=1)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="game_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Oyun başarıyla bitti",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Oyun tamamlandı."),
     *             @OA\Property(property="final_stats", type="object"),
     *             @OA\Property(property="answer_details", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function endNormalQuiz(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun bulunamadı.'
            ], 404);
        }

        $game->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // Kullanıcının cevaplarını getir
        $answers = GameAnswer::where('individual_game_id', $game->id)
            ->with('question')
            ->orderBy('answered_at', 'asc')
            ->get();

        $answerDetails = $answers->map(function ($answer) {
            return [
                'question_id' => $answer->question_id,
                'question_text' => $answer->question->question,
                'choices' => $answer->question->choices,
                'correct_answer' => $answer->question->correct_answer,
                'correct_answer_text' => $answer->question->correct_choice_text,
                'user_answer' => $answer->selected_option,
                'user_answer_text' => $answer->question->choices[$answer->selected_option] ?? '',
                'is_correct' => $answer->is_correct,
                'time_spent' => $answer->time_spent,
                'coins_earned' => $answer->is_correct ? $answer->question->coin_value : -$answer->question->coin_value,
                'answered_at' => $answer->answered_at
            ];
        });

        // Socket.IO'ya quiz tamamlanma bildirimi gönder
        $this->broadcastQuizCompleted($game, $user, $answerDetails->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Oyun tamamlandı.',
            'final_stats' => [
                'total_questions' => $game->question_count,
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers,
                'accuracy_rate' => $game->accuracy_rate,
                'total_coins' => $game->coins_earned,
                'total_time' => $game->total_time_seconds
            ],
            'answer_details' => $answerDetails
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/quiz/normal/history",
     *     summary="Normal Quiz Oyun Geçmişi",
     *     description="Kullanıcının normal quiz oyun geçmişini getirir.",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Oyun geçmişi başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="games", type="object")
     *         )
     *     )
     * )
     */
    public function getGameHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        $games = IndividualGame::where('user_id', $user->id)
            ->where('game_type', 'normal')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'games' => $games
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/quiz/normal/details/{game_id}",
     *     summary="Normal Quiz Oyun Detayları",
     *     description="Normal quiz oyununun detaylarını ve cevapları getirir.",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="game_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Oyun ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Oyun detayları başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="game", type="object"),
     *             @OA\Property(property="answer_details", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getGameDetails(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('game_type', 'normal')
            ->where('status', 'completed')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun bulunamadı.'
            ], 404);
        }

        // Kullanıcının cevaplarını getir
        $answers = GameAnswer::where('individual_game_id', $game->id)
            ->with('question')
            ->orderBy('answered_at', 'asc')
            ->get();

        $answerDetails = $answers->map(function ($answer) {
            return [
                'question_id' => $answer->question_id,
                'question_text' => $answer->question->question,
                'choices' => $answer->question->choices,
                'correct_answer' => $answer->question->correct_answer,
                'correct_answer_text' => $answer->question->correct_choice_text,
                'user_answer' => $answer->selected_option,
                'user_answer_text' => $answer->question->choices[$answer->selected_option] ?? '',
                'is_correct' => $answer->is_correct,
                'time_spent' => $answer->time_spent,
                'coins_earned' => $answer->is_correct ? $answer->question->coin_value : -$answer->question->coin_value,
                'answered_at' => $answer->answered_at
            ];
        });

        return response()->json([
            'success' => true,
            'game' => $game,
            'answer_details' => $answerDetails
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/quiz/normal/jokers",
     *     summary="Normal Quiz Joker Listesi",
     *     description="Kullanıcının mevcut joker sayılarını getirir",
     *     tags={"Normal Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Joker listesi başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="jokers", type="object",
     *                 @OA\Property(property="fifty_fifty", type="integer", example=1),
     *                 @OA\Property(property="double_answer", type="integer", example=1),
     *                 @OA\Property(property="hint", type="integer", example=1)
     *             ),
     *             @OA\Property(property="total_jokers", type="integer", example=3),
     *             @OA\Property(property="user_coins", type="integer", example=1000)
     *         )
     *     )
     * )
     */
    public function getJokers(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'jokers' => [
                'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                'double_answer' => $user->double_answer_jokers ?? 0,
                'hint' => $user->hint_jokers ?? 0
            ],
            'total_jokers' => ($user->fifty_fifty_jokers ?? 0) + ($user->double_answer_jokers ?? 0) + ($user->hint_jokers ?? 0),
            'user_coins' => $user->coins
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/use-joker",
     *     summary="Normal Quiz Joker Kullan",
     *     description="Normal quiz'de joker kullanır",
     *     tags={"Normal Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="game_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *                 @OA\Property(property="selected_answer", type="string", example="1", description="Seçilen şık (opsiyonel, 1-4 arası)")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="game_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *             @OA\Property(property="selected_answer", type="string", example="1", description="Seçilen şık (opsiyonel, 1-4 arası)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Joker başarıyla kullanıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty"),
     *             @OA\Property(property="result", type="object"),
     *             @OA\Property(property="selected_answer", type="string", example="1", nullable=true),
     *             @OA\Property(property="is_correct", type="boolean", example=true, nullable=true)
     *         )
     *     )
     * )
     */
    public function useJoker(Request $request): JsonResponse
    {
        $rules = [
            'game_id' => 'required|exists:individual_games,id',
            'question_id' => 'required|exists:questions,id',
            'joker_type' => 'required|in:fifty_fifty,double_answer,hint',
        ];
        
        // selected_answer sadece null değilse ve boş string değilse validation'a ekle
        if ($request->has('selected_answer') && $request->selected_answer !== null && $request->selected_answer !== '') {
            $rules['selected_answer'] = 'required|in:1,2,3,4';
        }
        
        $request->validate($rules);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('game_type', 'normal')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif normal oyun bulunamadı.'
            ], 404);
        }

        $question = Question::find($request->question_id);
        $jokerType = $request->joker_type;

        // Joker hakkı kontrolü
        $settings = $game->settings ?? [];
        
        // Eğer jokers anahtarı yoksa, kullanıcının güncel joker değerlerini al
        if (!isset($settings['jokers']) || !is_array($settings['jokers'])) {
            $user = Auth::user();
            $settings['jokers'] = [
                'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                'double_answer' => $user->double_answer_jokers ?? 0,
                'hint' => $user->hint_jokers ?? 0
            ];
        }
        
        // Joker tipi kontrolü
        if (!isset($settings['jokers'][$jokerType]) || $settings['jokers'][$jokerType] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu joker hakkınız kalmamış.'
            ], 400);
        }

        // Joker kullanımı
        $result = [];
        switch ($jokerType) {
            case 'fifty_fifty':
                $result = $this->useFiftyFiftyJoker($question);
                break;
            case 'double_answer':
                $result = $this->useDoubleAnswerJoker($question);
                break;
            case 'hint':
                $result = $this->useHintJoker($question);
                break;
        }

        // Joker hakkını azalt
        $settings['jokers'][$jokerType]--;
        $game->update(['settings' => $settings]);

        // Kullanıcının genel joker sayısını da azalt
        $this->decrementUserJoker($user, $jokerType);

        // Seçilen cevabın doğruluğunu kontrol et
        $selectedAnswer = $request->selected_answer;
        $isCorrect = null;
        
        if ($selectedAnswer !== null) {
            // Çift cevap joker kontrolü
            if ($jokerType === 'double_answer' && $request->second_option) {
                // Çift cevap: iki seçenekten biri doğru olmalı
                // Tip uyumsuzluğunu önlemek için string'e çevir
                $correctAnswer = (string) $question->correct_answer;
                $selectedAnswerStr = (string) $selectedAnswer;
                $secondOptionStr = (string) $request->second_option;
                $isCorrect = ($correctAnswer === $selectedAnswerStr) ||
                            ($correctAnswer === $secondOptionStr);
            } else {
                // Normal cevap kontrolü - Tip uyumsuzluğunu önlemek için string'e çevir
                $isCorrect = (string) $question->correct_answer === (string) $selectedAnswer;
            }
        }

        $response = [
            'success' => true,
            'joker_type' => $jokerType,
            'result' => $result,
            'jokers_remaining' => $settings['jokers']
        ];

        // Eğer seçilen cevap gönderildiyse, response'a ekle
        if ($selectedAnswer !== null) {
            $response['selected_answer'] = $selectedAnswer;
            $response['is_correct'] = $isCorrect;
        }

        return response()->json($response);
    }

    /**
     * 50-50 Joker
     */
    private function useFiftyFiftyJoker(Question $question): array
    {
        $choices = ['1', '2', '3', '4'];
        $correctAnswer = $question->correct_answer;

        // Doğru cevabı hariç tut, 2 yanlış seçeneği kaldır
        $wrongChoices = array_filter($choices, function($choice) use ($correctAnswer) {
            return $choice !== $correctAnswer;
        });

        $removeChoices = array_slice($wrongChoices, 0, 2);

        return [
            'type' => 'fifty_fifty',
            'removed_choices' => $removeChoices,
            'remaining_choices' => array_diff($choices, $removeChoices)
        ];
    }

    /**
     * Çift Cevap Joker
     */
    private function useDoubleAnswerJoker(Question $question): array
    {
        return [
            'type' => 'double_answer',
            'message' => 'Artık 2 seçenek işaretleyebilirsiniz. İkisinden biri doğru olmalı.'
        ];
    }

    /**
     * İpucu Joker
     */
    private function useHintJoker(Question $question): array
    {
        // Şık numarasını harfe çevir (1 -> A, 2 -> B, 3 -> C, 4 -> D)
        $answerMap = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
        $answerLetter = $answerMap[$question->correct_answer] ?? $question->correct_answer;
        
        $hints = [
            'Doğru cevap ' . $answerLetter . ' şıkkında.',
            'Kategori: ' . ($question->category->name ?? 'Genel')
        ];

        return [
            'type' => 'hint',
            'hint' => $hints[array_rand($hints)]
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/buy-joker",
     *     summary="Normal Quiz Joker Satın Al",
     *     description="Normal quiz için joker satın alır",
     *     tags={"Normal Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *                 @OA\Property(property="quantity", type="integer", example=2, minimum=1, maximum=10)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *             @OA\Property(property="quantity", type="integer", example=2, minimum=1, maximum=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Joker başarıyla satın alındı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="2 adet %50-%50 Joker satın alındı."),
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty"),
     *             @OA\Property(property="quantity", type="string", example="2"),
     *             @OA\Property(property="total_cost", type="integer", example=200),
     *             @OA\Property(property="remaining_coins", type="integer", example=800),
     *             @OA\Property(property="new_joker_count", type="integer", example=3)
     *         )
     *     )
     * )
     */
    public function buyJoker(Request $request): JsonResponse
    {
        $request->validate([
            'joker_type' => 'required|in:fifty_fifty,double_answer,hint',
            'quantity' => 'required|integer|min:1|max:10'
        ]);

        $user = Auth::user();
        $jokerType = $request->joker_type;
        $quantity = $request->quantity;

        // Joker fiyatları (config'den alınabilir)
        $jokerPrices = [
            'fifty_fifty' => config('app.joker_prices.fifty_fifty', 100),
            'double_answer' => config('app.joker_prices.double_answer', 200),
            'hint' => config('app.joker_prices.hint', 150)
        ];

        $totalCost = $jokerPrices[$jokerType] * $quantity;

        if ($user->coins < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => 'Yeterli coin bulunmuyor.',
                'required_coins' => $totalCost,
                'current_coins' => $user->coins
            ], 400);
        }

        // Coin'i düş ve joker'i ekle
        $user->decrement('coins', $totalCost);
        $user->increment($jokerType . '_jokers', $quantity);

        // User'ı yeniden yükle ki güncel joker değerlerini alabilelim
        $user->refresh();

        // Kullanıcının aktif oyunu varsa, oyunun settings içindeki jokerleri de güncelle
        $activeGame = IndividualGame::where('user_id', $user->id)
            ->where('game_type', 'normal')
            ->where('status', 'active')
            ->first();

        if ($activeGame) {
            $settings = $activeGame->settings ?? [];

            // User tablosundaki güncel joker değerlerini settings'e yansıt
            $settings['jokers'] = [
                'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                'double_answer' => $user->double_answer_jokers ?? 0,
                'hint' => $user->hint_jokers ?? 0
            ];

            $activeGame->update(['settings' => $settings]);
        }

        $jokerNames = [
            'fifty_fifty' => '%50-%50 Joker',
            'double_answer' => 'Çift Cevap Joker',
            'hint' => 'İpucu Joker'
        ];

        return response()->json([
            'success' => true,
            'message' => "{$quantity} adet {$jokerNames[$jokerType]} satın alındı.",
            'joker_type' => $jokerType,
            'quantity' => (string)$quantity,
            'total_cost' => $totalCost,
            'remaining_coins' => $user->coins,
            'new_joker_count' => $user->{$jokerType . '_jokers'}
        ]);
    }

    /**
     * Sonraki soruyu getir
     */
    /**
     * Sonraki soruyu getir
     * Reklam sorusu mantığı: ad_appearance_frequency'e göre kaç soruda bir reklam sorusu gösterilir
     */
    private function getNextQuestion(IndividualGame $game): ?Question
    {
        $settings = $game->settings ?? [];
        $totalAnswered = $settings['total_questions_answered'] ?? 0;
        $currentQuestionNumber = $totalAnswered + 1;
        $answeredQuestionIds = $settings['answered_question_ids'] ?? [];

        // Reklam sorusu kontrolü
        $adAppearanceFrequencySetting = \App\Models\GeneralSetting::where('key', 'ad_appearance_frequency')->first();
        $adAppearanceFrequency = $adAppearanceFrequencySetting ? (int) $adAppearanceFrequencySetting->value : 0;

        // Eğer setting yoksa veya frequency 0 ise, reklam sorusu gösterilmez
        // Eğer soru numarası ad_appearance_frequency'e bölünüyorsa, reklam sorusu seç
        if ($adAppearanceFrequency > 0 && $currentQuestionNumber % $adAppearanceFrequency === 0) {
            // Reklam kategorisini bul (name'i "Reklam" veya "reklam" olan kategori)
            $adCategory = \App\Models\Category::where('is_active', true)
                ->get()
                ->filter(function($category) {
                    $nameTr = strtolower($category->getTranslation('name', 'tr', false) ?? '');
                    $nameEn = strtolower($category->getTranslation('name', 'en', false) ?? '');
                    return strpos($nameTr, 'reklam') !== false || strpos($nameEn, 'advertisement') !== false;
                })
                ->first();

            // Eğer reklam kategorisi bulunduysa ve soru varsa, reklam sorusu seç
            if ($adCategory) {
                // Reklam kategorisinden soru seç (cevaplanan soruları hariç tut)
                $adQuestion = Question::where('is_active', true)
                    ->where('category_id', $adCategory->id)
                    ->whereNotIn('id', $answeredQuestionIds)
                    ->inRandomOrder()
                    ->first();

                // Eğer reklam sorusu bulunduysa, answered_question_ids listesine ekle ve döndür
                if ($adQuestion) {
                    // ÖNEMLİ: Soru seçildiği anda answered_question_ids listesine ekle
                    if (!in_array($adQuestion->id, $answeredQuestionIds)) {
                        $answeredQuestionIds[] = $adQuestion->id;
                        $settings['answered_question_ids'] = $answeredQuestionIds;
                        $game->update(['settings' => $settings]);
                    }
                    return $adQuestion;
                }
            }
            // Eğer reklam kategorisi yoksa veya reklam sorusu yoksa, normal soru seçimine geç (aşağıdaki kod devam eder)
        }

        // Normal soru seçimi (cevaplanan soruları hariç tut)
        // İlk 10 soru kolay, sonrakiler rastgele
        if ($totalAnswered < 10) {
            $question = Question::active()
                ->byLevel('easy')
                ->whereNotIn('id', $answeredQuestionIds)
                ->inRandomOrder()
                ->first();

            $settings['current_difficulty'] = 'easy';
        } else {
            $difficulties = ['easy', 'medium', 'hard'];
            $randomDifficulty = $difficulties[array_rand($difficulties)];

            $question = Question::active()
                ->byLevel($randomDifficulty)
                ->whereNotIn('id', $answeredQuestionIds)
                ->inRandomOrder()
                ->first();

            $settings['current_difficulty'] = $randomDifficulty;
        }

        // ÖNEMLİ: Soru seçildiği anda answered_question_ids listesine ekle
        // Bu sayede aynı soru bir daha kesinlikle seçilemez
        if ($question && !in_array($question->id, $answeredQuestionIds)) {
            $answeredQuestionIds[] = $question->id;
            $settings['answered_question_ids'] = $answeredQuestionIds;
        }

        $game->update(['settings' => $settings]);

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

        // Eğer zaten tam URL ise, olduğu gibi döndür
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        // Eğer storage/questions/ ile başlıyorsa, sadece questions/ kısmını al
        if (strpos($imagePath, 'storage/questions/') !== false) {
            $imagePath = str_replace('storage/questions/', 'questions/', $imagePath);
        }

        // Eğer questions/ ile başlamıyorsa, ekle
        if (strpos($imagePath, 'questions/') !== 0) {
            $imagePath = 'questions/' . ltrim($imagePath, '/');
        }

        // Tam URL oluştur
        $baseUrl = config('app.url', 'https://bilbakalim.online');
        return rtrim($baseUrl, '/') . '/storage/' . $imagePath;
    }

    /**
     * Socket.IO'ya quiz başlatma bildirimi gönder
     */
    private function broadcastQuizStarted(IndividualGame $game, $question): void
    {
        $webhookService = app(WebhookService::class);

        $webhookService->sendQuizStarted(
            $game->id,
            $game->user_id,
            'normal',
            $question
        );
    }

    /**
     * Socket.IO'ya quiz cevap bildirimi gönder
     */
    private function broadcastQuizAnswer(IndividualGame $game, User $user, $question, bool $isCorrect, int $coinsChange): void
    {
        $webhookService = app(WebhookService::class);

        $webhookService->sendQuizAnswer(
            $game->id,
            $user->id,
            $question->id,
            $isCorrect,
            $coinsChange,
            'normal',
            [
                'user_coins' => $user->coins,
                'game_stats' => [
                    'total_questions' => $game->question_count,
                    'correct_answers' => $game->correct_answers,
                    'wrong_answers' => $game->wrong_answers,
                    'total_coins' => $game->coins_earned
                ]
            ]
        );
    }

    /**
     * Socket.IO'ya quiz tamamlanma bildirimi gönder
     */
    private function broadcastQuizCompleted(IndividualGame $game, User $user, array $answerDetails): void
    {
        $webhookService = app(WebhookService::class);

        $webhookService->sendQuizCompleted(
            $game->id,
            $user->id,
            'normal',
            [
                'total_questions' => $game->question_count,
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers,
                'accuracy_rate' => $game->accuracy_rate,
                'total_coins' => $game->coins_earned,
                'total_time' => $game->total_time_seconds
            ],
            $answerDetails,
            [
                'user_coins' => $user->coins
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/mobile/start",
     *     summary="Mobil Normal Quiz Başlat",
     *     description="Mobil için normal quiz başlatır ve tüm soruları döndürür",
     *     tags={"Quiz Mobile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="question_count", type="integer", description="Soru sayısı (varsayılan: 10)", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mobil quiz başarıyla başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mobil normal quiz başlatıldı."),
     *             @OA\Property(property="game", type="object"),
     *             @OA\Property(property="questions", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function startMobileNormalQuiz(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Aktif oyun kontrolü
        $activeGame = IndividualGame::where('user_id', $user->id)
            ->where('game_type', 'normal')
            ->where('status', 'active')
            ->first();

        if ($activeGame) {
            try {
                // Aktif oyunun settings içindeki jokerleri User tablosundaki güncel değerlerle senkronize et
                $settings = $activeGame->settings ?? [];
                $settings['jokers'] = [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ];

                // Settings'i güncelle
                $activeGame->update(['settings' => $settings]);
                $activeGame->refresh();
            } catch (\Exception $e) {
                \Log::error('Aktif oyun joker güncelleme hatası', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'game_id' => $activeGame->id
                ]);
                // Hata olsa bile oyunu döndür
            }

            return response()->json([
                'success' => true,
                'message' => 'Zaten aktif bir normal oyununuz var.',
                'active_game' => $activeGame,
                'game' => null,
                'questions' => null,
                'jokers' => [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ],
                'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
            ], 200);
        }

        try {
            $questionCount = $request->question_count ?? 10;
            $timeLimit = config('app.quiz.normal.time_limit_seconds', 600);

            // Reklam sorusu mantığı: ad_appearance_frequency'e göre kaç soruda bir reklam sorusu gösterilir
            $adAppearanceFrequencySetting = \App\Models\GeneralSetting::where('key', 'ad_appearance_frequency')->first();
            $adAppearanceFrequency = $adAppearanceFrequencySetting ? (int) $adAppearanceFrequencySetting->value : 0;

            // Reklam kategorisini bul
            $adCategory = \App\Models\Category::where('is_active', true)
                ->get()
                ->filter(function($category) {
                    $nameTr = strtolower($category->getTranslation('name', 'tr', false) ?? '');
                    $nameEn = strtolower($category->getTranslation('name', 'en', false) ?? '');
                    return strpos($nameTr, 'reklam') !== false || strpos($nameEn, 'advertisement') !== false;
                })
                ->first();

            $allQuestions = collect();
            $adQuestionIndex = 0;

            // Soru seçimi: Reklam sorusu mantığıyla birlikte
            // Eğer setting yoksa veya frequency 0 ise, reklam sorusu gösterilmez
            for ($i = 1; $i <= $questionCount; $i++) {
                // Eğer soru numarası ad_appearance_frequency'e bölünüyorsa ve reklam kategorisi varsa, reklam sorusu seç
                if ($adAppearanceFrequency > 0 && $i % $adAppearanceFrequency === 0 && $adCategory) {
                    $adQuestions = Question::where('is_active', true)
                        ->where('category_id', $adCategory->id)
                ->inRandomOrder()
                ->get();

                    if ($adQuestions->isNotEmpty()) {
                        $adQuestion = $adQuestions->get($adQuestionIndex % $adQuestions->count());
                        $allQuestions->push($adQuestion);
                        $adQuestionIndex++;
                        continue;
                    }
                }

                // Normal soru seçimi: İlk 10 kolay, sonrakiler rastgele
                if ($allQuestions->where('question_level', 'easy')->count() < 10) {
                    $question = Question::where('question_level', 'easy')
                        ->where('is_active', true)
                        ->whereNotIn('id', $allQuestions->pluck('id'))
                    ->inRandomOrder()
                        ->first();
                } else {
                    $difficulties = ['easy', 'medium', 'hard'];
                    $randomDifficulty = $difficulties[array_rand($difficulties)];

                    $question = Question::where('question_level', $randomDifficulty)
                        ->where('is_active', true)
                        ->whereNotIn('id', $allQuestions->pluck('id'))
                        ->inRandomOrder()
                        ->first();
                }

                if ($question) {
                    $allQuestions->push($question);
                }
            }

            // Eğer yeterli soru yoksa hata döndür
            if ($allQuestions->count() < $questionCount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Yeterli soru bulunamadı. Lütfen daha az soru sayısı deneyin.',
                    'available_questions' => $allQuestions->count(),
                    'requested_count' => $questionCount
                ], 400);
            }

            // Oyun oluştur
            // Kullanıcının güncel joker sayılarını al
            $user->refresh(); // User'ı yeniden yükle
            $game = IndividualGame::create([
                'user_id' => $user->id,
                'game_type' => 'normal',
                'difficulty_level' => 'mixed',
                'question_count' => $questionCount,
                'time_limit_seconds' => $timeLimit,
                'joker_count' => 0,
                'score' => 0,
                'correct_answers' => 0,
                'wrong_answers' => 0,
                'coins_earned' => 0,
                'status' => 'active',
                'started_at' => now(),
                'settings' => [
                    'easy_questions_count' => $allQuestions->where('question_level', 'easy')->count(),
                    'current_difficulty' => 'easy',
                    'total_questions_count' => $questionCount,
                    'jokers' => [
                        'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                        'double_answer' => $user->double_answer_jokers ?? 0,
                        'hint' => $user->hint_jokers ?? 0
                    ]
                ]
            ]);

            // Webhook gönder (soru varsa)
            if ($allQuestions->isNotEmpty()) {
                Log::info('Mobil Normal Quiz başlatma webhook gönderiliyor', [
                    'game_id' => $game->id,
                    'user_id' => $game->user_id,
                    'game_type' => 'normal',
                    'question_count' => $questionCount
                ]);

                $this->broadcastQuizStarted($game, $allQuestions->first());
            }

            return response()->json([
                'success' => true,
                'message' => 'Mobil normal quiz başlatıldı.',
                'game' => $game,
                'questions' => $allQuestions->map(function($question) {
                    return $this->formatQuestionMultilingual($question);
                }),
                'active_game' => null,
                'jokers' => [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ],
                'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
            ]);
        } catch (\Exception $e) {
            \Log::error('Mobil Normal Quiz başlatma hatası', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Quiz başlatılırken bir hata oluştu: ' . $e->getMessage(),
                'game' => null,
                'questions' => null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/normal/mobile/submit-answers",
     *     summary="Mobil Normal Quiz Toplu Cevap Gönder",
     *     description="Mobil normal quiz için tüm cevapları toplu olarak gönderir",
     *     tags={"Quiz Mobile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="game_id", type="integer", description="Oyun ID'si", example=1),
     *                 @OA\Property(property="answers", type="string", description="JSON formatında cevaplar")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cevaplar başarıyla gönderildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cevaplar başarıyla gönderildi."),
     *             @OA\Property(property="final_stats", type="object"),
     *             @OA\Property(property="answer_details", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function submitMobileNormalAnswers(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|integer',
            'answers' => 'required|string'
        ]);

        $user = Auth::user();
        $answers = json_decode($request->answers, true);

        if (!is_array($answers)) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz cevap formatı.'
            ], 400);
        }

        // Oyun kontrolü
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('game_type', 'normal')
            ->where('status', 'active')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun bulunamadı.'
            ], 404);
        }

        $totalCoins = 0;
        $correctAnswers = 0;
        $wrongAnswers = 0;
        $answerDetails = collect();
        $settings = $game->settings ?? [];

        // Cevaplanan soru ID'lerini topla
        $answeredQuestionIds = $settings['answered_question_ids'] ?? [];

        DB::beginTransaction();
        try {
            foreach ($answers as $answer) {
                $question = Question::find($answer['question_id']);
                if (!$question) continue;

                $isCorrect = $answer['selected_option'] == $question->correct_answer;
                $coinsEarned = $isCorrect ? $question->coin_value : 0;
                $timeSpent = $answer['time_spent'] ?? 0;

                // Cevabı kaydet
                GameAnswer::create([
                    'individual_game_id' => $game->id,
                    'game_session_id' => null,
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'selected_option' => $answer['selected_option'],
                    'is_correct' => $isCorrect,
                    'time_spent' => $timeSpent,
                    'coins_earned' => $coinsEarned,
                    'answered_at' => now()
                ]);

                // Cevaplanan soru ID'sini listeye ekle
                if (!in_array($question->id, $answeredQuestionIds)) {
                    $answeredQuestionIds[] = $question->id;
                }

                $totalCoins += $coinsEarned;
                if ($isCorrect) {
                    $correctAnswers++;
                } else {
                    $wrongAnswers++;
                }

                // Cevap detayını ekle
                $answerDetails->push([
                    'question_id' => $question->id,
                    'question_text' => $question->question['tr'] ?? $question->question,
                    'choices' => [
                        '1' => $question->one_choice['tr'] ?? $question->one_choice,
                        '2' => $question->two_choice['tr'] ?? $question->two_choice,
                        '3' => $question->three_choice['tr'] ?? $question->three_choice,
                        '4' => $question->four_choice['tr'] ?? $question->four_choice,
                    ],
                    'correct_answer' => $question->correct_answer,
                    'correct_answer_text' => $this->getChoiceText($question, $question->correct_answer),
                    'user_answer' => $answer['selected_option'],
                    'user_answer_text' => $this->getChoiceText($question, $answer['selected_option']),
                    'is_correct' => $isCorrect,
                    'time_spent' => $timeSpent,
                    'coins_earned' => $coinsEarned,
                    'answered_at' => now()->toISOString()
                ]);

                // Webhook gönder (her cevap için)
                Log::info('Mobil Normal Quiz cevap webhook gönderiliyor', [
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'is_correct' => $isCorrect,
                    'coins_earned' => $coinsEarned
                ]);
                $this->broadcastQuizAnswer($game, $user, $question, $isCorrect, $coinsEarned);
            }

            // Kullanıcının coin'ini güncelle
            $user->increment('coins', $totalCoins);

            // Settings'e cevaplanan soru ID'lerini ekle
            $settings['answered_question_ids'] = $answeredQuestionIds;

            // Oyunu tamamla
            $game->update([
                'status' => 'completed',
                'completed_at' => now(),
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'coins_earned' => $totalCoins,
                'score' => $correctAnswers,
                'settings' => $settings
            ]);

            DB::commit();

            // Quiz tamamlama webhook'u
            Log::info('Mobil Normal Quiz tamamlama webhook gönderiliyor', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'total_questions' => count($answers),
                'correct_answers' => $correctAnswers,
                'total_coins' => $totalCoins
            ]);
            $this->broadcastQuizCompleted($game, $user, $answerDetails->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Cevaplar başarıyla gönderildi.',
                'final_stats' => [
                    'total_questions' => count($answers),
                    'correct_answers' => $correctAnswers,
                    'wrong_answers' => $wrongAnswers,
                    'accuracy_rate' => count($answers) > 0 ? round(($correctAnswers / count($answers)) * 100, 2) : 0,
                    'total_coins' => $totalCoins,
                    'total_time' => array_sum(array_column($answers, 'time_spent'))
                ],
                'answer_details' => $answerDetails
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mobil Normal Quiz cevap gönderme hatası', [
                'error' => $e->getMessage(),
                'game_id' => $request->game_id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cevaplar gönderilirken hata oluştu.'
            ], 500);
        }
    }

    private function getChoiceText($question, $choiceNumber)
    {
        switch ($choiceNumber) {
            case '1':
                return $question->one_choice['tr'] ?? $question->one_choice;
            case '2':
                return $question->two_choice['tr'] ?? $question->two_choice;
            case '3':
                return $question->three_choice['tr'] ?? $question->three_choice;
            case '4':
                return $question->four_choice['tr'] ?? $question->four_choice;
            default:
                return '';
        }
    }

    /**
     * Kullanıcının joker sayısını azalt
     * Her joker kullanımında 1 azaltır
     */
    private function decrementUserJoker(User $user, string $jokerType): void
    {
        switch ($jokerType) {
            case 'fifty_fifty':
                $user->decrement('fifty_fifty_jokers', 1);
                break;
            case 'double_answer':
                $user->decrement('double_answer_jokers', 1);
                break;
            case 'hint':
                $user->decrement('hint_jokers', 1);
                break;
        }
    }

    /**
     * @OA\Get(
     *     path="/api/game-settings",
     *     summary="Get Game Settings",
     *     description="Get static game settings including package configurations",
     *     tags={"Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Game settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Game settings retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="packages", type="object",
     *                     @OA\Property(property="mini_package", type="integer", example=50),
     *                     @OA\Property(property="medium_package", type="integer", example=100),
     *                     @OA\Property(property="large_package", type="integer", example=150)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function getGameSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Game settings retrieved successfully',
            'data' => [
                'packages' => [
                    'mini_package' => 50,
                    'medium_package' => 100,
                    'large_package' => 150
                ]
            ]
        ]);
    }
}
