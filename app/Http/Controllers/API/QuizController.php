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
                'active_game' => $activeGame
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

        return response()->json([
            'success' => true,
            'message' => 'Normal quiz başlatıldı.',
            'game' => $game,
            'question' => $question,
            'active_game' => null
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
            'selected_option' => 'required|in:1,2,3,4',
            'time_spent' => 'nullable|integer|min:1',
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

        // Joker kullanımı kontrolü
        $jokerUsed = null;
        if ($request->joker_used) {
            $settings = $game->settings;
            if ($settings['jokers'][$request->joker_used] > 0) {
                $settings['jokers'][$request->joker_used]--;
                $jokerUsed = $request->joker_used;
                $game->update(['settings' => $settings]);
            }
        }

        // Çift cevap joker kontrolü
        $isCorrect = false;
        if ($jokerUsed === 'double_answer' && $request->second_option) {
            // Çift cevap: iki seçenekten biri doğru olmalı
            $isCorrect = ($question->correct_answer === $request->selected_option) ||
                ($question->correct_answer === $request->second_option);
        } else {
            // Normal cevap
            $isCorrect = $question->correct_answer === $request->selected_option;
        }

        $timeSpent = $request->time_spent ?? 30;

        // Cevabı kaydet
        GameAnswer::create([
            'individual_game_id' => $game->id,
            'game_session_id' => null, // Normal quiz için null
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option' => $request->selected_option,
            'is_correct' => $isCorrect,
            'time_spent' => $timeSpent,
            'joker_used' => $jokerUsed,
            'answered_at' => now(),
            'user_answer' => $jokerUsed === 'double_answer' ?
                $request->selected_option . ',' . $request->second_option :
                $request->selected_option
        ]);

        // Oyun istatistiklerini güncelle
        $coinsChange = $isCorrect ? $question->coin_value : -$question->coin_value;

        $settings = $game->settings ?? [];
        $settings['total_questions_answered'] = ($settings['total_questions_answered'] ?? 0) + 1;

        $game->update([
            'question_count' => $game->question_count + 1,
            'correct_answers' => $isCorrect ? $game->correct_answers + 1 : $game->correct_answers,
            'wrong_answers' => !$isCorrect ? $game->wrong_answers + 1 : $game->wrong_answers,
            'coins_earned' => $game->coins_earned + $coinsChange,
            'total_time_seconds' => $game->total_time_seconds + $timeSpent,
            'settings' => $settings
        ]);

        // Kullanıcının jetonunu güncelle
        $user->increment('coins', $coinsChange);

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

        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_option' => $question->correct_answer,
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
            'next_question' => $nextQuestion
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
     *                 @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"})
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="game_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Joker başarıyla kullanıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="joker_type", type="string", example="fifty_fifty"),
     *             @OA\Property(property="result", type="object")
     *         )
     *     )
     * )
     */
    public function useJoker(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id',
            'question_id' => 'required|exists:questions,id',
            'joker_type' => 'required|in:fifty_fifty,double_answer,hint'
        ]);

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
        $settings = $game->settings;
        if ($settings['jokers'][$jokerType] <= 0) {
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

        return response()->json([
            'success' => true,
            'joker_type' => $jokerType,
            'result' => $result,
            'jokers_remaining' => $settings['jokers']
        ]);
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
        $hints = [
            'Bu soru ' . $question->question_level . ' seviyesinde.',
            'Doğru cevap ' . $question->correct_answer . ' şıkkında.',
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
    private function getNextQuestion(IndividualGame $game): ?Question
    {
        $settings = $game->settings ?? [];
        $totalAnswered = $settings['total_questions_answered'] ?? 0;

        // İlk 10 soru kolay, sonrakiler rastgele
        if ($totalAnswered < 10) {
            $question = Question::active()
                ->byLevel('easy')
                ->inRandomOrder()
                ->first();

            $settings['current_difficulty'] = 'easy';
        } else {
            $difficulties = ['easy', 'medium', 'hard'];
            $randomDifficulty = $difficulties[array_rand($difficulties)];

            $question = Question::active()
                ->byLevel($randomDifficulty)
                ->inRandomOrder()
                ->first();

            $settings['current_difficulty'] = $randomDifficulty;
        }

        $game->update(['settings' => $settings]);

        return $question;
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
            ], 200);
        }

        try {
            $questionCount = $request->question_count ?? 10;
            $timeLimit = config('app.quiz.normal.time_limit_seconds', 600);

            // İlk 10 soru kolay, sonrakiler rastgele
            $easyQuestions = Question::where('question_level', 'easy')
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit(min(10, $questionCount))
                ->get();

            $remainingCount = $questionCount - $easyQuestions->count();
            $randomQuestions = collect();

            if ($remainingCount > 0) {
                $randomQuestions = Question::where('is_active', true)
                    ->whereNotIn('id', $easyQuestions->pluck('id'))
                    ->inRandomOrder()
                    ->limit($remainingCount)
                    ->get();
            }

            $allQuestions = $easyQuestions->merge($randomQuestions);

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
                    'easy_questions_count' => $easyQuestions->count(),
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
                    return [
                        'id' => $question->id,
                        'question' => $question->question,
                        'one_choice' => $question->one_choice,
                        'two_choice' => $question->two_choice,
                        'three_choice' => $question->three_choice,
                        'four_choice' => $question->four_choice,
                        'correct_answer' => $question->correct_answer,
                        'category_id' => $question->category_id,
                    'question_level' => $question->question_level,
                    'coin_value' => $question->coin_value,
                    'image' => $question->image ? asset('storage/' . $question->image) : null
                    ];
                }),
                'active_game' => null,
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
                    'game_id' => $game->id,
                    'game_session_id' => null,
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'selected_option' => $answer['selected_option'],
                    'is_correct' => $isCorrect,
                    'time_spent' => $timeSpent,
                    'coins_earned' => $coinsEarned,
                    'answered_at' => now()
                ]);

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

            // Oyunu tamamla
            $game->update([
                'status' => 'completed',
                'completed_at' => now(),
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'coins_earned' => $totalCoins,
                'score' => $correctAnswers
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
