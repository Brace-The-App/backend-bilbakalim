<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RevealsCorrectAnswerWhenWrong;
use App\Models\Question;
use App\Models\IndividualGame;
use App\Models\GameAnswer;
use App\Models\User;
use App\Models\Package;
use App\Models\Tournament;
use App\Models\TournamentUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PremiumQuizController extends Controller
{
    use RevealsCorrectAnswerWhenWrong;
    /**
     * @OA\Post(
     *     path="/api/quiz/premium/start",
     *     summary="Premium Quiz Başlat",
     *     description="Premium kullanıcılar için 15 soruluk quiz oyununu başlatır. 7 orta + 8 zor soru.",
     *     tags={"Premium Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Premium quiz başarıyla başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Premium quiz başlatıldı."),
     *             @OA\Property(property="game", type="object"),
     *             @OA\Property(property="question", type="object"),
     *             @OA\Property(property="jokers", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Premium kullanıcı değil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu özellik sadece premium kullanıcılar için.")
     *         )
     *     )
     * )
     */
    public function startPremiumQuiz(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Premium kullanıcı kontrolü
        if (!$user->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'Bu özellik sadece premium kullanıcılar için.'
            ], 403);
        }

        // Aktif oyun var mı kontrol et
        $activeGame = IndividualGame::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('game_type', 'premium')
            ->first();

        if ($activeGame) {
            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir premium oyununuz var.',
                'active_game' => $activeGame,
                'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
            ], 400);
        }

        // Kullanıcının mevcut joker sayılarını al, yoksa başlangıç jokerlerini ver
        $userJokers = [
            'fifty_fifty' => $user->fifty_fifty_jokers ?? config('app.joker_fifty_fifty_count', 1),
            'double_answer' => $user->double_answer_jokers ?? config('app.joker_double_answer_count', 1),
            'hint' => $user->hint_jokers ?? config('app.joker_hint_count', 1)
        ];

        // Eğer kullanıcının jokerleri yoksa, başlangıç jokerlerini ver
        if (is_null($user->fifty_fifty_jokers)) {
            $user->update([
                'fifty_fifty_jokers' => config('app.joker_fifty_fifty_count', 1),
                'double_answer_jokers' => config('app.joker_double_answer_count', 1),
                'hint_jokers' => config('app.joker_hint_count', 1)
            ]);
        }

        // Yeni premium oyun oluştur
        $game = IndividualGame::create([
            'user_id' => $user->id,
            'game_type' => 'premium',
            'difficulty_level' => 'mixed',
            'question_count' => config('app.quiz_premium_question_count', 15), // Toplam 15 soru
            'time_limit_seconds' => config('app.quiz_premium_time_limit', 1800), // 30 dakika
            'joker_count' => 3, // 3 joker
            'score' => 0,
            'correct_answers' => 0,
            'wrong_answers' => 0,
            'coins_earned' => 0,
            'status' => 'active',
            'started_at' => now(),
            'settings' => [
                'medium_questions_remaining' => 7,
                'hard_questions_remaining' => 8,
                'jokers' => $userJokers, // Kullanıcının mevcut jokerleri
                'current_question_number' => 1
            ]
        ]);

        // İlk soruyu getir
        $question = $this->getNextPremiumQuestion($game);

        // Socket.IO'ya quiz başlatma bildirimi gönder
        $this->broadcastQuizStarted($game, $question);

        // Soruyu çoklu dil formatında formatla
        $questionData = $question ? $this->formatQuestionMultilingual($question) : null;

        return response()->json([
            'success' => true,
            'message' => 'Premium quiz başlatıldı.',
            'game' => $game,
            'question' => $questionData,
            'jokers' => $game->settings['jokers'],
            'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/premium/answer",
     *     summary="Premium Quiz Cevap Gönder",
     *     description="Premium quiz oyununda soruya cevap gönderir. Joker kullanımı destekler.",
     *     tags={"Premium Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="game_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="selected_option", type="string", example="2"),
     *                 @OA\Property(property="time_spent", type="integer", example=30),
     *                 @OA\Property(property="joker_used", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *                 @OA\Property(property="second_option", type="string", example="3", description="Çift cevap joker için ikinci seçenek")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="game_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="selected_option", type="string", example="2"),
     *             @OA\Property(property="time_spent", type="integer", example=30),
     *             @OA\Property(property="joker_used", type="string", example="fifty_fifty", enum={"fifty_fifty", "double_answer", "hint"}),
     *             @OA\Property(property="second_option", type="string", example="3", description="Çift cevap joker için ikinci seçenek")
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
     *             @OA\Property(property="joker_used", type="string", example="fifty_fifty"),
     *             @OA\Property(property="game_stats", type="object"),
     *             @OA\Property(property="jokers", type="object"),
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
            ->where('game_type', 'premium')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif premium oyun bulunamadı.'
            ], 404);
        }

        $question = Question::find($request->question_id);

        // Eğer selected_option 5 ise veya null/boş ise (süre doldu), yanlış olarak işaretle
        $selectedOption = $request->selected_option;
        $answerAlreadySaved = false;

        if ($request->selected_option == '5' || $request->selected_option === 5 || empty($selectedOption) || $selectedOption === null || $selectedOption === '') {
            $isCorrect = false;
            $selectedOption = null; // 5 geldiğinde null olarak kaydet
            $jokerUsed = null;
        } else {
            // Joker kullanımı kontrolü
            $jokerUsed = null;
            if ($request->joker_used) {
                $settings = $game->settings;
                if ($settings['jokers'][$request->joker_used] > 0) {
                    $settings['jokers'][$request->joker_used]--;
                    $jokerUsed = $request->joker_used;
                    $game->update(['settings' => $settings]);

                    // Kullanıcının genel joker sayısını da azalt
                    $this->decrementUserJoker($user, $request->joker_used);
                }
            }

            // Çift cevap joker kullanıldıysa ve ikinci şık henüz gönderilmediyse, kontrol et
            if ($jokerUsed === 'double_answer' && !$request->has('second_option')) {
                // İlk şıkkın doğru olup olmadığını kontrol et
                $firstOptionCorrect = (string) $question->correct_answer === (string) $selectedOption;

                // Eğer ilk şık doğruysa, ikinci şıkkı seçmeye gerek yok, direkt doğru cevap olarak kaydet
                if ($firstOptionCorrect) {
                    // Doğru cevabı direkt kaydet
                    GameAnswer::create([
                        'individual_game_id' => $game->id,
                        'game_session_id' => null,
                        'user_id' => $user->id,
                        'question_id' => $question->id,
                        'selected_option' => $selectedOption,
                        'is_correct' => true,
                        'time_spent' => $request->time_spent ?? 30,
                        'joker_used' => 'double_answer',
                        'answered_at' => now(),
                        'user_answer' => $selectedOption
                    ]);

                    $isCorrect = true;
                    $answerAlreadySaved = true;
                } else {
                    // İlk şık yanlışsa, ikinci şıkkı beklemeli
                    // Pending cevabı settings'te sakla
                    $settings = $game->settings;
                    if (!isset($settings['pending_answers'])) {
                        $settings['pending_answers'] = [];
                    }
                    $settings['pending_answers'][$question->id] = [
                        'selected_option' => $selectedOption,
                        'time_spent' => $request->time_spent ?? 30,
                        'answered_at' => now()->toISOString()
                    ];
                    $game->update(['settings' => $settings]);

                    // İkinci şıkkı beklediğimizi belirten response döndür
                    return response()->json([
                        'success' => true,
                        'waiting_for_second_option' => true,
                        'message' => 'Lütfen ikinci şıkkı seçin.',
                        'first_option' => $selectedOption,
                        'joker_used' => 'double_answer',
                        'first_option_correct' => false
                    ]);
                }
            } else {
                // Çift cevap joker kontrolü (ikinci şık da geldiyse)
                if ($jokerUsed === 'double_answer' && $request->has('second_option')) {
                    // Pending cevabı kontrol et
                    $settings = $game->settings;
                    $firstOption = $selectedOption;
                    $timeSpent = $request->time_spent ?? 30;

                    if (isset($settings['pending_answers'][$question->id])) {
                        $pendingAnswer = $settings['pending_answers'][$question->id];
                        $firstOption = $pendingAnswer['selected_option'];
                        $timeSpent = $pendingAnswer['time_spent'];
                        // Pending cevabı sil
                        unset($settings['pending_answers'][$question->id]);
                        $game->update(['settings' => $settings]);
                    }

                // Çift cevap: iki seçenekten biri doğru olmalı
                    $correctAnswer = (string) $question->correct_answer;
                    $firstOptionStr = (string) $firstOption;
                    $secondOptionStr = (string) $request->second_option;
                    $isCorrect = ($correctAnswer === $firstOptionStr) ||
                                ($correctAnswer === $secondOptionStr);

                    // Cevabı kaydet
                    GameAnswer::create([
                        'individual_game_id' => $game->id,
                        'game_session_id' => null,
                        'user_id' => $user->id,
                        'question_id' => $question->id,
                        'selected_option' => $firstOption,
                        'is_correct' => $isCorrect,
                        'time_spent' => $timeSpent,
                        'joker_used' => 'double_answer',
                        'answered_at' => now(),
                        'user_answer' => $firstOption . ',' . $request->second_option
                    ]);

                    $answerAlreadySaved = true;
            } else {
                    // Normal cevap - Tip uyumsuzluğunu önlemek için string'e çevir
                    $isCorrect = (string) $question->correct_answer === (string) $selectedOption;
                }
            }
        }

        $timeSpent = $request->time_spent ?? 30;

        // Cevabı kaydet (eğer daha önce kaydedilmediyse)
        if (!$answerAlreadySaved) {
        GameAnswer::create([
            'individual_game_id' => $game->id,
            'game_session_id' => null, // Premium quiz için null
            'user_id' => $user->id,
            'question_id' => $question->id,
            'selected_option' => $selectedOption,
            'is_correct' => $isCorrect,
            'time_spent' => $timeSpent,
            'joker_used' => $jokerUsed,
            'answered_at' => now(),
                'user_answer' => $selectedOption ?? null
        ]);
        }

        // Oyun istatistiklerini güncelle
        $coinsChange = $isCorrect ? $question->coin_value : -$question->coin_value;

        $settings = $game->settings;
        $settings['current_question_number']++;

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
                $nextQuestion = $this->getNextPremiumQuestion($game);
                $nextQuestionCoinValue = $nextQuestion ? $nextQuestion->coin_value : 0;

                // Eğer bir sonraki soru için yeterli coin yoksa (5 coin ve altı) veya mevcut soru için coin yoksa
                if ($userCoins < $coinDeduction || ($nextQuestion && $userCoins <= 5 && $userCoins < $nextQuestionCoinValue)) {
                    // Coin yeterli değil, reklam/coin satın alma seçeneği sun
            $game->update([
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers + 1,
                'coins_earned' => $game->coins_earned + $coinsChange,
                'total_time_seconds' => $game->total_time_seconds + $timeSpent,
                'status' => 'completed',
                'ended_at' => now(),
                'settings' => $settings
            ]);

                    // Kullanıcının coin'ini güncelle (eksiye gitmemesi için max(0, ...) kullan)
                    $finalCoins = max(0, $userCoins - $coinDeduction);
                    $user->update(['coins' => $finalCoins]);

            // Socket.IO'ya oyun bitiş bildirimi gönder
            $this->broadcastQuizCompleted($game, $user, [], []);

            return response()->json(array_merge([
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
                ],
                'game_completed' => true
            ], $this->correctAnswerRevealForQuestion($question, false)));
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
            'correct_answers' => $isCorrect ? $game->correct_answers + 1 : $game->correct_answers,
            'wrong_answers' => !$isCorrect ? $game->wrong_answers + 1 : $game->wrong_answers,
            'coins_earned' => $game->coins_earned + $coinsChange,
            'total_time_seconds' => $game->total_time_seconds + $timeSpent,
            'settings' => $settings
        ]);

        // Socket.IO'ya cevap bildirimi gönder
        $this->broadcastQuizAnswer($game, $user, $question, $isCorrect, $coinsChange, $jokerUsed);

        // Oyun bitti mi kontrol et
        $maxQuestions = config('app.quiz_premium_question_count', 15);
        if ($settings['current_question_number'] > $maxQuestions) {
            $game->update(['status' => 'completed', 'completed_at' => now()]);

            // Kullanıcının tüm cevaplarını getir
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
                    'joker_used' => $answer->joker_used,
                    'coins_earned' => $answer->is_correct ? $answer->question->coin_value : -$answer->question->coin_value,
                    'answered_at' => $answer->answered_at
                ];
            });

            // Ödül kontrolü
            $reward = $this->calculateReward($game);

            // Socket.IO'ya quiz tamamlanma bildirimi gönder
            $this->broadcastQuizCompleted($game, $user, $answerDetails->toArray(), $reward);

            return response()->json(array_merge([
                'success' => true,
                'is_correct' => $isCorrect,
                'earned_coins' => $coinsChange,
                'game_completed' => true,
                'final_stats' => [
                    'total_questions' => $maxQuestions,
                    'correct_answers' => $game->correct_answers,
                    'wrong_answers' => $game->wrong_answers,
                    'accuracy_rate' => $game->accuracy_rate,
                    'total_coins' => $game->coins_earned,
                    'total_time' => $game->total_time_seconds
                ],
                'answer_details' => $answerDetails,
                'reward' => $reward
            ], $this->correctAnswerRevealForQuestion($question, $isCorrect)));
        }

        // Sonraki soruyu getir
        $nextQuestion = $this->getNextPremiumQuestion($game);

        // Soruyu çoklu dil formatında formatla
        $nextQuestionData = $nextQuestion ? $this->formatQuestionMultilingual($nextQuestion) : null;

        return response()->json(array_merge([
            'success' => true,
            'is_correct' => $isCorrect,
            'earned_coins' => $coinsChange,
            'joker_used' => $jokerUsed,
            'game_stats' => [
                'current_question' => $settings['current_question_number'],
                'total_questions' => config('app.quiz_premium_question_count', 15),
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers,
                'total_coins' => $game->coins_earned,
                'user_coins' => $user->coins
            ],
            'jokers' => $game->settings['jokers'],
            'next_question' => $nextQuestionData
        ], $this->correctAnswerRevealForQuestion($question, $isCorrect)));
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/premium/joker",
     *     summary="Premium Quiz Joker Kullan",
     *     description="Premium quiz oyununda joker kullanır.",
     *     tags={"Premium Quiz"},
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
     *             @OA\Property(property="remaining_jokers", type="object"),
     *             @OA\Property(property="selected_answer", type="string", example="1", nullable=true),
     *             @OA\Property(property="is_correct", type="boolean", example=true, nullable=true)
     *         )
     *     )
     * )
     */
    public function useJoker(Request $request): JsonResponse
    {
        $rules = [
            'game_id' => 'required|integer',
            'question_id' => 'required|exists:questions,id',
            'joker_type' => 'required|in:fifty_fifty,double_answer,hint',
        ];

        // selected_answer sadece null değilse ve boş string değilse validation'a ekle
        if ($request->has('selected_answer') && $request->selected_answer !== null && $request->selected_answer !== '') {
            $rules['selected_answer'] = 'required|in:1,2,3,4,5';
        }

        $request->validate($rules);

        $user = Auth::user();
        $gameId = $request->game_id;
        $jokerType = $request->joker_type;
        $isTournament = false;
        $game = null;
        $tournament = null;
        $tournamentUser = null;

        // Önce premium quiz olarak kontrol et
        $game = IndividualGame::where('id', $gameId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('game_type', 'premium')
            ->first();

        // Eğer premium quiz değilse, turnuva olarak kontrol et
        if (!$game) {
            $tournament = Tournament::where('id', $gameId)
                ->where('status', 'active')
                ->first();

            if ($tournament) {
                $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['active', 'waiting'])
                    ->first();

                if ($tournamentUser) {
                    $isTournament = true;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aktif turnuva katılımınız bulunamadı.'
                    ], 404);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktif premium oyun veya turnuva bulunamadı.'
                ], 404);
            }
        }

        $question = Question::find($request->question_id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Soru bulunamadı.'
            ], 404);
        }

        // Turnuva için joker kontrolü
        if ($isTournament) {
            // Turnuva joker hakkı kontrolü (joker_hakki alanı)
            if ($tournamentUser->joker_hakki <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turnuva joker hakkınız kalmadı.'
                ], 400);
            }

            // Joker tiplerini kontrol et ve gerekirse başlat
            $answersDetail = is_array($tournamentUser->answers_detail) ? $tournamentUser->answers_detail : [];

            // Eğer jokers anahtarı yoksa, başlangıç değerlerini ayarla
            if (!isset($answersDetail['jokers']) || !is_array($answersDetail['jokers'])) {
                $totalJokers = $tournamentUser->joker_hakki ?? 3;
                // Her joker tipine eşit dağıt (kalan varsa sırayla ekle)
                $jokersPerType = floor($totalJokers / 3);
                $remainingJokers = $totalJokers % 3;

                $answersDetail['jokers'] = [
                    'fifty_fifty' => $jokersPerType + ($remainingJokers > 0 ? 1 : 0),
                    'double_answer' => $jokersPerType + ($remainingJokers > 1 ? 1 : 0),
                    'hint' => $jokersPerType + ($remainingJokers > 2 ? 1 : 0),
                ];

                // Başlangıç değerlerini kaydet
                $tournamentUser->update(['answers_detail' => $answersDetail]);
                $tournamentUser->refresh();
            }

            // Joker tipi kontrolü
            if (!isset($answersDetail['jokers'][$jokerType]) || $answersDetail['jokers'][$jokerType] <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu joker tipinden hakkınız kalmadı.'
                ], 400);
            }
        } else {
            // Premium quiz için joker kontrolü
            $settings = $game->settings ?? [];

            // Eğer jokers anahtarı yoksa, kullanıcının güncel joker değerlerini al
            if (!isset($settings['jokers']) || !is_array($settings['jokers'])) {
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
                    'message' => 'Bu joker türü kalmadı.'
                ], 400);
            }
        }

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
        if ($isTournament) {
            // Turnuva için joker tiplerini ayrı ayrı takip et
            // answers_detail zaten yukarıda kontrol edildi ve başlatıldı
            $answersDetail = is_array($tournamentUser->answers_detail) ? $tournamentUser->answers_detail : [];

            // Kullanılan joker tipini azalt (yukarıda kontrol edildi, burada kesinlikle var)
            $answersDetail['jokers'][$jokerType]--;

            // Toplam joker hakkını da azalt
            $tournamentUser->decrement('joker_hakki');

            // Güncellenmiş joker bilgilerini kaydet (mevcut answers_detail verilerini koru)
            $tournamentUser->update(['answers_detail' => $answersDetail]);

            // TournamentUser'ı yeniden yükle
            $tournamentUser->refresh();

            // Kalan joker tiplerini döndür
            $remainingJokers = [
                'fifty_fifty' => $answersDetail['jokers']['fifty_fifty'] ?? 0,
                'double_answer' => $answersDetail['jokers']['double_answer'] ?? 0,
                'hint' => $answersDetail['jokers']['hint'] ?? 0,
                'tournament_jokers' => $tournamentUser->joker_hakki // Toplam kalan joker hakkı
            ];
        } else {
            // Premium quiz için settings'teki jokerleri azalt
            $settings['jokers'][$jokerType]--;
            $game->update(['settings' => $settings]);

            // Kullanıcının genel joker sayısını da azalt
            $this->decrementUserJoker($user, $jokerType);

            $remainingJokers = $settings['jokers'];
        }

        // Socket.IO'ya joker kullanım bildirimi gönder
        if ($isTournament) {
            $this->broadcastTournamentJokerUsed($tournament, $user, $jokerType, $result);
        } else {
            $this->broadcastJokerUsed($game, $user, $jokerType, $result);
        }

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
            'remaining_jokers' => $remainingJokers,
            'game_type' => $isTournament ? 'tournament' : 'premium'
        ];

        // Eğer seçilen cevap gönderildiyse, response'a ekle
        if ($selectedAnswer !== null) {
            $response['selected_answer'] = $selectedAnswer;
            $response['is_correct'] = $isCorrect;
        }

        return response()->json($response);
    }

    /**
     * Premium Quiz - Oyunu bitir
     */
    public function endPremiumQuiz(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('game_type', 'premium')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif premium oyun bulunamadı.'
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
                'joker_used' => $answer->joker_used,
                'coins_earned' => $answer->is_correct ? $answer->question->coin_value : -$answer->question->coin_value,
                'answered_at' => $answer->answered_at
            ];
        });

        // Ödül hesapla
        $reward = $this->calculateReward($game);

        // Socket.IO'ya quiz tamamlanma bildirimi gönder
        $this->broadcastQuizCompleted($game, $user, $answerDetails->toArray(), $reward);

        return response()->json([
            'success' => true,
            'message' => 'Premium oyun tamamlandı.',
            'final_stats' => [
                'total_questions' => $game->settings['current_question_number'] - 1,
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers,
                'accuracy_rate' => $game->accuracy_rate,
                'total_coins' => $game->coins_earned,
                'total_time' => $game->total_time_seconds
            ],
            'answer_details' => $answerDetails,
            'reward' => $reward
        ]);
    }

    /**
     * Sonraki premium soruyu getir
     * Reklam sorusu mantığı: ad_appearance_frequency'e göre kaç soruda bir reklam sorusu gösterilir
     */
    private function getNextPremiumQuestion(IndividualGame $game): ?Question
    {
        $settings = $game->settings;
        $currentQuestionNumber = $settings['current_question_number'] ?? 1;
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
        $mediumRemaining = $settings['medium_questions_remaining'] ?? 7;
        $hardRemaining = $settings['hard_questions_remaining'] ?? 8;

        // Önce orta, sonra zor sorular
        if ($mediumRemaining > 0) {
            $question = Question::active()
                ->byLevel('medium')
                ->whereNotIn('id', $answeredQuestionIds)
                ->inRandomOrder()
                ->first();

            $settings['medium_questions_remaining'] = $mediumRemaining - 1;
        } else {
            $question = Question::active()
                ->byLevel('hard')
                ->whereNotIn('id', $answeredQuestionIds)
                ->inRandomOrder()
                ->first();

            $settings['hard_questions_remaining'] = $hardRemaining - 1;
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
     * %50 Joker
     */
    private function useFiftyFiftyJoker(Question $question): array
    {
        $choices = $question->choices;
        $correctAnswer = (string) $question->correct_answer;
        $allChoiceKeys = ['1', '2', '3', '4'];

        // Doğru cevabı hariç tut, 2 yanlış seçeneği kaldır
        $wrongOptions = array_filter($allChoiceKeys, function($choice) use ($correctAnswer) {
            return $choice !== $correctAnswer;
        });

        // 2 yanlış şık seç
        $removeOptions = array_values(array_slice($wrongOptions, 0, 2));

        // Kalan şıkları al (doğru cevap + 1 yanlış cevap)
        $remainingChoices = [];
        foreach ($allChoiceKeys as $key) {
            if (!in_array($key, $removeOptions)) {
                $remainingChoices[$key] = $choices[$key] ?? '';
            }
        }

        return [
            'type' => 'fifty_fifty',
            'removed_options' => $removeOptions, // Array olarak 2 şık
            'remaining_choices' => $remainingChoices
        ];
    }

    /**
     * Çift Cevap Joker
     */
    private function useDoubleAnswerJoker(Question $question): array
    {
        return [
            'message' => 'Çift cevap joker kullanıldı. İki cevap seçebilirsiniz.',
            'allow_multiple' => true
        ];
    }

    /**
     * Sen Söyle (İpucu) Joker
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
            'hint' => $hints[array_rand($hints)]
        ];
    }

    /**
     * Ödül hesapla
     */
    private function calculateReward(IndividualGame $game): array
    {
        $accuracyRate = $game->accuracy_rate;

        // %100 doğru cevap özel ödül sistemi
        if ($accuracyRate == 100) {
            $user = $game->user;
            $coinsReward = config('app.reward_perfect_score_coins', 10000);
            $fiftyFiftyReward = config('app.reward_perfect_score_fifty_fifty', 10);
            $doubleAnswerReward = config('app.reward_perfect_score_double_answer', 10);
            $hintReward = config('app.reward_perfect_score_hint', 10);

            $user->increment('coins', $coinsReward);

            // Joker ödülleri ekle
            $user->increment('fifty_fifty_jokers', $fiftyFiftyReward);
            $user->increment('double_answer_jokers', $doubleAnswerReward);
            $user->increment('hint_jokers', $hintReward);

            return [
                'type' => 'perfect_score',
                'coins' => $coinsReward,
                'jokers' => [
                    'fifty_fifty' => $fiftyFiftyReward,
                    'double_answer' => $doubleAnswerReward,
                    'hint' => $hintReward
                ],
                'message' => "Mükemmel! %100 doğru cevap ile özel ödülü kazandınız!"
            ];
        }

        // %80+ başarı ödülü
        if ($accuracyRate >= 80) {
            $user = $game->user;
            $coinsReward = config('app.reward_high_accuracy_coins', 2000);
            $fiftyFiftyReward = config('app.reward_high_accuracy_fifty_fifty', 3);
            $doubleAnswerReward = config('app.reward_high_accuracy_double_answer', 3);
            $hintReward = config('app.reward_high_accuracy_hint', 3);

            $user->increment('coins', $coinsReward);

            // Joker ödülleri ekle
            $user->increment('fifty_fifty_jokers', $fiftyFiftyReward);
            $user->increment('double_answer_jokers', $doubleAnswerReward);
            $user->increment('hint_jokers', $hintReward);

            return [
                'type' => 'high_accuracy',
                'coins' => $coinsReward,
                'jokers' => [
                    'fifty_fifty' => $fiftyFiftyReward,
                    'double_answer' => $doubleAnswerReward,
                    'hint' => $hintReward
                ],
                'message' => "Tebrikler! Yüksek başarı ödülünü kazandınız! ({$accuracyRate}% başarı)"
            ];
        }

        $minAccuracyRate = config('app.reward_min_accuracy_rate', 80);

        return [
            'type' => 'none',
            'message' => "Ödül kazanmak için %{$minAccuracyRate} ve üzeri başarı gerekli. Mevcut başarınız: %{$accuracyRate}"
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/quiz/premium/jokers",
     *     summary="Kullanıcı Joker Durumu",
     *     description="Kullanıcının mevcut joker sayılarını getirir. Eğer tournament_id parametresi gönderilirse, turnuva joker durumunu döndürür.",
     *     tags={"Premium Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="query",
     *         description="Turnuva ID (opsiyonel - turnuva joker durumu için)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Joker durumu başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="game_type", type="string", example="premium", enum={"premium", "tournament"}),
     *             @OA\Property(property="jokers", type="object",
     *                 @OA\Property(property="fifty_fifty", type="integer", example=5),
     *                 @OA\Property(property="double_answer", type="integer", example=3),
     *                 @OA\Property(property="hint", type="integer", example=2)
     *             ),
     *             @OA\Property(property="total_jokers", type="integer", example=10),
     *             @OA\Property(property="tournament_jokers", type="integer", example=10, description="Turnuva için toplam joker hakkı"),
     *             @OA\Property(property="user_coins", type="integer", example=1500, description="Premium quiz için kullanıcı coin'i")
     *         )
     *     )
     * )
     */
    public function getUserJokers(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Eğer tournament_id parametresi gönderilmişse, turnuva joker durumunu döndür
        if ($request->has('tournament_id') && $request->tournament_id) {
            $tournament = \App\Models\Tournament::find($request->tournament_id);

            if (!$tournament) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turnuva bulunamadı.'
                ], 404);
            }

            $tournamentUser = \App\Models\TournamentUser::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$tournamentUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu turnuvaya katılımınız bulunamadı.'
                ], 404);
            }

            // Joker tiplerini kontrol et ve gerekirse başlat
            $answersDetail = is_array($tournamentUser->answers_detail) ? $tournamentUser->answers_detail : [];

            // Eğer jokers anahtarı yoksa, başlangıç değerlerini ayarla
            if (!isset($answersDetail['jokers']) || !is_array($answersDetail['jokers'])) {
                $totalJokers = $tournamentUser->joker_hakki ?? 3;
                // Her joker tipine eşit dağıt (kalan varsa sırayla ekle)
                $jokersPerType = floor($totalJokers / 3);
                $remainingJokers = $totalJokers % 3;

                $answersDetail['jokers'] = [
                    'fifty_fifty' => $jokersPerType + ($remainingJokers > 0 ? 1 : 0),
                    'double_answer' => $jokersPerType + ($remainingJokers > 1 ? 1 : 0),
                    'hint' => $jokersPerType + ($remainingJokers > 2 ? 1 : 0),
                ];

                // Başlangıç değerlerini kaydet
                $tournamentUser->update(['answers_detail' => $answersDetail]);
                $tournamentUser->refresh();
            }

        return response()->json([
            'success' => true,
                'game_type' => 'tournament',
                'jokers' => [
                    'fifty_fifty' => $answersDetail['jokers']['fifty_fifty'] ?? 0,
                    'double_answer' => $answersDetail['jokers']['double_answer'] ?? 0,
                    'hint' => $answersDetail['jokers']['hint'] ?? 0
                ],
                'total_jokers' => $tournamentUser->joker_hakki ?? 0,
                'tournament_jokers' => $tournamentUser->joker_hakki ?? 0
            ]);
        }

        // Premium quiz için normal joker durumu
        return response()->json([
            'success' => true,
            'game_type' => 'premium',
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
     *     path="/api/quiz/premium/buy-joker",
     *     summary="Joker Satın Al",
     *     description="Kullanıcı jeton karşılığında joker satın alabilir.",
     *     tags={"Premium Quiz"},
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
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="total_cost", type="integer", example=200),
     *             @OA\Property(property="remaining_coins", type="integer", example=1300),
     *             @OA\Property(property="new_joker_count", type="integer", example=7)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Yeterli jeton yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Yeterli jetonunuz yok."),
     *             @OA\Property(property="required_coins", type="integer", example=200),
     *             @OA\Property(property="user_coins", type="integer", example=100)
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
            'fifty_fifty' => config('app.joker_fifty_fifty_price', 100),
            'double_answer' => config('app.joker_double_answer_price', 200),
            'hint' => config('app.joker_hint_price', 150)
        ];

        $totalCost = $jokerPrices[$jokerType] * $quantity;

        if ($user->coins < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => 'Yeterli jetonunuz yok.',
                'required_coins' => $totalCost,
                'user_coins' => $user->coins
            ], 400);
        }

        // Jetonu düş ve joker ekle
        $user->decrement('coins', $totalCost);
        $user->increment($jokerType . '_jokers', $quantity);

        return response()->json([
            'success' => true,
            'message' => "{$quantity} adet " . $this->getJokerDisplayName($jokerType) . " satın alındı.",
            'joker_type' => $jokerType,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'remaining_coins' => $user->coins,
            'new_joker_count' => $user->{$jokerType . '_jokers'}
        ]);
    }

    /**
     * Joker türü için görüntüleme adı
     */
    private function getJokerDisplayName(string $jokerType): string
    {
        $names = [
            'fifty_fifty' => '%50-%50 Joker',
            'double_answer' => 'Çift Cevap Joker',
            'hint' => 'İpucu Joker'
        ];

        return $names[$jokerType] ?? $jokerType;
    }

    /**
     * Premium Quiz - Oyun detayları (cevaplar dahil)
     */
    public function getGameDetails(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $user = Auth::user();
        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', $user->id)
            ->where('game_type', 'premium')
            ->where('status', 'completed')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Premium oyun bulunamadı.'
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
                'joker_used' => $answer->joker_used,
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
     * Socket.IO'ya quiz başlatma bildirimi gönder
     */
    private function broadcastQuizStarted(IndividualGame $game, $question): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://localhost:3000');

            Http::post("{$socketUrl}/webhook/quiz-started", [
                'game_id' => $game->id,
                'user_id' => $game->user_id,
                'game_type' => 'premium',
                'question' => $question,
                'jokers' => $game->settings['jokers'],
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Premium quiz started broadcast sent', [
                'game_id' => $game->id,
                'user_id' => $game->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast premium quiz started', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Socket.IO'ya quiz cevap bildirimi gönder
     */
    private function broadcastQuizAnswer(IndividualGame $game, User $user, $question, bool $isCorrect, int $coinsChange, ?string $jokerUsed): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://localhost:3000');

            Http::post("{$socketUrl}/webhook/quiz-answer-submitted", array_merge([
                'game_id' => $game->id,
                'user_id' => $user->id,
                'question_id' => $question->id,
                'is_correct' => $isCorrect,
                'coins_earned' => $coinsChange,
                'game_type' => 'premium',
                'joker_used' => $jokerUsed,
                'user_coins' => $user->coins,
                'game_stats' => [
                    'current_question' => $game->settings['current_question_number'],
                    'total_questions' => config('app.quiz_premium_question_count', 15),
                    'correct_answers' => $game->correct_answers,
                    'wrong_answers' => $game->wrong_answers,
                    'total_coins' => $game->coins_earned
                ],
                'jokers' => $game->settings['jokers'],
                'timestamp' => now()->toISOString()
            ], $this->correctAnswerRevealForQuestion($question, $isCorrect)));

            Log::info('Premium quiz answer broadcast sent', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'is_correct' => $isCorrect,
                'joker_used' => $jokerUsed
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast premium quiz answer', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Socket.IO'ya joker kullanım bildirimi gönder
     */
    private function broadcastJokerUsed(IndividualGame $game, User $user, string $jokerType, array $result): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://localhost:3000');

            Http::post("{$socketUrl}/webhook/quiz-joker-used", [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'joker_type' => $jokerType,
                'result' => $result,
                'remaining_jokers' => $game->settings['jokers'],
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Premium quiz joker used broadcast sent', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'joker_type' => $jokerType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast premium quiz joker used', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Socket.IO'ya turnuva joker kullanım bildirimi gönder
     */
    private function broadcastTournamentJokerUsed(Tournament $tournament, User $user, string $jokerType, array $result): void
    {
        try {
            $webhookService = app(\App\Http\Services\WebhookService::class);
            $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();

            $webhookService->sendWebhook('/socket-webhooks/webhook/tournament-joker-used', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'joker_type' => $jokerType,
                'result' => $result,
                'remaining_jokers' => $tournamentUser ? $tournamentUser->joker_hakki : 0,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament joker used broadcast sent', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'joker_type' => $jokerType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast tournament joker used', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
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
     * Socket.IO'ya quiz tamamlanma bildirimi gönder
     */
    private function broadcastQuizCompleted(IndividualGame $game, User $user, array $answerDetails, array $reward): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://localhost:3000');

            Http::post("{$socketUrl}/webhook/quiz-completed", [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'game_type' => 'premium',
                'final_stats' => [
                    'total_questions' => config('app.quiz_premium_question_count', 15),
                    'correct_answers' => $game->correct_answers,
                    'wrong_answers' => $game->wrong_answers,
                    'accuracy_rate' => $game->accuracy_rate,
                    'total_coins' => $game->coins_earned,
                    'total_time' => $game->total_time_seconds
                ],
                'answer_details' => $answerDetails,
                'reward' => $reward,
                'user_coins' => $user->coins,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Premium quiz completed broadcast sent', [
                'game_id' => $game->id,
                'user_id' => $user->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast premium quiz completed', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/premium/mobile/start",
     *     summary="Mobil Premium Quiz Başlat",
     *     description="Mobil için premium quiz başlatır ve tüm soruları döndürür",
     *     tags={"Quiz Mobile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="question_count", type="integer", description="Soru sayısı (varsayılan: 15)", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mobil premium quiz başarıyla başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mobil premium quiz başlatıldı."),
     *             @OA\Property(property="game", type="object"),
     *             @OA\Property(property="questions", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="jokers", type="object")
     *         )
     *     )
     * )
     */
    public function startMobilePremiumQuiz(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Premium kontrolü
        if (!$user->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'Premium üyelik gerekli.'
            ], 403);
        }

        // Aktif oyun kontrolü
        $activeGame = IndividualGame::where('user_id', $user->id)
            ->where('game_type', 'premium')
            ->where('status', 'active')
            ->first();

        if ($activeGame) {
            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir premium oyununuz var.',
                'active_game' => $activeGame,
                'jokers' => [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ],
                'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
            ], 400);
        }

        $questionCount = $request->question_count ?? config('app.quiz.premium.question_count', 15);
        $timeLimit = config('app.quiz.premium.time_limit_seconds', 1800);

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

        // Soru dağılımı: Reklam sorusu mantığıyla birlikte
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

            // Normal soru seçimi: İlk 7 orta, sonraki 8 zor
            if ($allQuestions->where('question_level', 'medium')->count() < 7) {
                $question = Question::where('question_level', 'medium')
            ->where('is_active', true)
                    ->whereNotIn('id', $allQuestions->pluck('id'))
            ->inRandomOrder()
                    ->first();
            } else {
                $question = Question::where('question_level', 'hard')
                    ->where('is_active', true)
                    ->whereNotIn('id', $allQuestions->pluck('id'))
                    ->inRandomOrder()
                    ->first();
            }

            if ($question) {
                $allQuestions->push($question);
            }
        }

        // Oyun oluştur
        $game = IndividualGame::create([
            'user_id' => $user->id,
            'game_type' => 'premium',
            'difficulty_level' => 'mixed',
            'question_count' => $questionCount,
            'time_limit_seconds' => $timeLimit,
            'joker_count' => 3,
            'score' => 0,
            'correct_answers' => 0,
            'wrong_answers' => 0,
            'coins_earned' => 0,
            'status' => 'active',
            'started_at' => now(),
            'settings' => [
                'medium_questions_remaining' => 6,
                'hard_questions_remaining' => 8,
                'jokers' => [
                    'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                    'double_answer' => $user->double_answer_jokers ?? 0,
                    'hint' => $user->hint_jokers ?? 0
                ],
                'current_question_number' => 1
            ]
        ]);

        // Webhook gönder
        Log::info('Mobil Premium Quiz başlatma webhook gönderiliyor', [
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'game_type' => 'premium',
            'question_count' => $questionCount
        ]);

        $this->broadcastQuizStarted($game, $allQuestions->first());

        return response()->json([
            'success' => true,
            'message' => 'Mobil premium quiz başlatıldı.',
            'game' => $game,
            'questions' => $allQuestions->map(function($question) {
                return $this->formatQuestionMultilingual($question);
            }),
            'jokers' => [
                'fifty_fifty' => $user->fifty_fifty_jokers ?? 0,
                'double_answer' => $user->double_answer_jokers ?? 0,
                'hint' => $user->hint_jokers ?? 0
            ],
            'answer_time_limit' => config('app.quiz_answer_time_limit', 15) // Cevap süresi (saniye)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/quiz/premium/mobile/submit-answers",
     *     summary="Mobil Premium Quiz Toplu Cevap Gönder",
     *     description="Mobil premium quiz için tüm cevapları toplu olarak gönderir",
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
     *             @OA\Property(property="answer_details", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="reward", type="object")
     *         )
     *     )
     * )
     */
    public function submitMobilePremiumAnswers(Request $request): JsonResponse
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
            ->where('game_type', 'premium')
            ->where('status', 'active')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif premium oyun bulunamadı.'
            ], 404);
        }

        $totalCoins = 0;
        $correctAnswers = 0;
        $wrongAnswers = 0;
        $answerDetails = collect();
        $jokersUsed = [];

        DB::beginTransaction();
        try {
            foreach ($answers as $answer) {
                $question = Question::find($answer['question_id']);
                if (!$question) continue;

                $jokerUsed = $answer['joker_used'] ?? null;
                $isCorrect = false;
                $coinsEarned = 0;
                $timeSpent = $answer['time_spent'] ?? 0;

                // Joker kullanımı kontrolü
                if ($jokerUsed) {
                    $jokerResult = $this->processJokerUsage($user, $jokerUsed, $question, $answer['selected_option']);
                    $isCorrect = $jokerResult['is_correct'];
                    $coinsEarned = $isCorrect ? $question->coin_value : 0;
                    $jokersUsed[] = $jokerUsed;
                } else {
                    $isCorrect = $answer['selected_option'] == $question->correct_answer;
                    $coinsEarned = $isCorrect ? $question->coin_value : 0;
                }

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
                    'joker_used' => $jokerUsed,
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
                    'joker_used' => $jokerUsed,
                    'coins_earned' => $coinsEarned,
                    'answered_at' => now()->toISOString()
                ]);

                // Webhook gönder (her cevap için)
                Log::info('Mobil Premium Quiz cevap webhook gönderiliyor', [
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'is_correct' => $isCorrect,
                    'coins_earned' => $coinsEarned,
                    'joker_used' => $jokerUsed
                ]);
                $this->broadcastQuizAnswer($game, $user, $question, $isCorrect, $coinsEarned, $jokerUsed);

                // Joker kullanımı webhook'u
                if ($jokerUsed) {
                    Log::info('Mobil Premium Quiz joker webhook gönderiliyor', [
                        'game_id' => $game->id,
                        'user_id' => $user->id,
                        'joker_type' => $jokerUsed
                    ]);
                    $this->broadcastJokerUsed($game, $user, $jokerUsed, ['is_correct' => $isCorrect]);
                }
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
                'score' => $correctAnswers,
                'jokers_used' => $jokersUsed
            ]);

            DB::commit();

            // Ödül hesapla
            $accuracyRate = count($answers) > 0 ? ($correctAnswers / count($answers)) * 100 : 0;

            // Game objesini güncelle (accuracy_rate için)
            $game->accuracy_rate = $accuracyRate;
            $reward = $this->calculateReward($game);

            // Quiz tamamlama webhook'u
            Log::info('Mobil Premium Quiz tamamlama webhook gönderiliyor', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'total_questions' => count($answers),
                'correct_answers' => $correctAnswers,
                'total_coins' => $totalCoins,
                'accuracy_rate' => $accuracyRate
            ]);
            $this->broadcastQuizCompleted($game, $user, $answerDetails->toArray(), $reward);

            return response()->json([
                'success' => true,
                'message' => 'Cevaplar başarıyla gönderildi.',
                'final_stats' => [
                    'total_questions' => count($answers),
                    'correct_answers' => $correctAnswers,
                    'wrong_answers' => $wrongAnswers,
                    'accuracy_rate' => round($accuracyRate, 2),
                    'total_coins' => $totalCoins,
                    'total_time' => array_sum(array_column($answers, 'time_spent'))
                ],
                'answer_details' => $answerDetails,
                'reward' => $reward
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mobil Premium Quiz cevap gönderme hatası', [
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

    private function processJokerUsage($user, $jokerType, $question, $selectedOption)
    {
        $isCorrect = false;

        switch ($jokerType) {
            case 'fifty_fifty':
                // 50-50 joker: 2 yanlış seçenek kaldırılır, kullanıcı doğru cevabı seçerse doğru
                $isCorrect = $selectedOption == $question->correct_answer;
                break;

            case 'double_answer':
                // Çift cevap joker: Kullanıcı 2 seçenek seçebilir, doğru cevap varsa doğru
                if (is_string($selectedOption) && strpos($selectedOption, ',') !== false) {
                    $selectedOptions = explode(',', $selectedOption);
                    $isCorrect = in_array($question->correct_answer, $selectedOptions);
                } else {
                    $isCorrect = $selectedOption == $question->correct_answer;
                }
                break;

            case 'hint':
                // İpucu joker: Sadece ipucu verir, cevap doğruluğu normal şekilde kontrol edilir
                $isCorrect = $selectedOption == $question->correct_answer;
                break;

            default:
                $isCorrect = $selectedOption == $question->correct_answer;
        }

        return [
            'is_correct' => $isCorrect,
            'joker_type' => $jokerType
        ];
    }
}
