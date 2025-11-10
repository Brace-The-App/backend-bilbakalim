<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\IndividualGame;
use App\Models\GameAnswer;
use App\Models\User;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PremiumQuizController extends Controller
{
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
                'active_game' => $activeGame
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
        
        // Question'dan correct_answer'ı gizle
        $questionData = $question ? $question->toArray() : null;
        if ($questionData) {
            unset($questionData['correct_answer']);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Premium quiz başlatıldı.',
            'game' => $game,
            'question' => $questionData,
            'jokers' => $game->settings['jokers']
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
            'selected_option' => 'nullable|in:1,2,3,4',
            'time_spent' => 'nullable|integer|min:1',
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
        
        // Eğer selected_option null veya boş ise (süre doldu), yanlış olarak işaretle
        $selectedOption = $request->selected_option;
        if (empty($selectedOption) || $selectedOption === null || $selectedOption === '') {
            $isCorrect = false;
            $selectedOption = null;
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
            
            // Çift cevap joker kontrolü
            if ($jokerUsed === 'double_answer' && $request->second_option) {
                // Çift cevap: iki seçenekten biri doğru olmalı
                $isCorrect = ($question->correct_answer === $selectedOption) || 
                            ($question->correct_answer === $request->second_option);
            } else {
                // Normal cevap
                $isCorrect = $question->correct_answer === $selectedOption;
            }
        }
        
        $timeSpent = $request->time_spent ?? 30;
        
        // Cevabı kaydet
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
            'user_answer' => ($jokerUsed === 'double_answer' && $selectedOption && $request->second_option) ? 
                $selectedOption . ',' . $request->second_option : 
                ($selectedOption ?? null)
        ]);
        
        // Oyun istatistiklerini güncelle
        $coinsChange = $isCorrect ? $question->coin_value : -$question->coin_value;
        
        $settings = $game->settings;
        $settings['current_question_number']++;
        
        // Yanlış cevap durumunda oyunu bitir
        if (!$isCorrect) {
            $game->update([
                'correct_answers' => $game->correct_answers,
                'wrong_answers' => $game->wrong_answers + 1,
                'coins_earned' => $game->coins_earned + $coinsChange,
                'total_time_seconds' => $game->total_time_seconds + $timeSpent,
                'status' => 'completed',
                'ended_at' => now(),
                'settings' => $settings
            ]);
            
            // Kullanıcının coin'ini güncelle
            $user->increment('coins', $coinsChange);
            
            // Socket.IO'ya oyun bitiş bildirimi gönder
            $this->broadcastQuizCompleted($game, $user, [], []);
            
            return response()->json([
                'success' => false,
                'message' => 'Yanlış cevap! Oyun bitti.',
                'is_correct' => false,
                'earned_coins' => $coinsChange,
                'game_stats' => [
                    'total_questions' => $game->question_count,
                    'correct_answers' => $game->correct_answers,
                    'wrong_answers' => $game->wrong_answers,
                    'total_coins' => $game->coins_earned,
                    'user_coins' => $user->coins
                ],
                'game_completed' => true
            ]);
        }
        
        $game->update([
            'correct_answers' => $game->correct_answers + 1,
            'wrong_answers' => $game->wrong_answers,
            'coins_earned' => $game->coins_earned + $coinsChange,
            'total_time_seconds' => $game->total_time_seconds + $timeSpent,
            'settings' => $settings
        ]);
        
        // Kullanıcının jetonunu güncelle
        $user->increment('coins', $coinsChange);
        
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
            
            return response()->json([
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
            ]);
        }
        
        // Sonraki soruyu getir
        $nextQuestion = $this->getNextPremiumQuestion($game);
        
        // Sonraki sorudan correct_answer'ı gizle
        $nextQuestionData = $nextQuestion ? $nextQuestion->toArray() : null;
        if ($nextQuestionData) {
            unset($nextQuestionData['correct_answer']);
        }
        
        return response()->json([
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
        ]);
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
     *             @OA\Property(property="result", type="object"),
     *             @OA\Property(property="remaining_jokers", type="object")
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
            ->where('game_type', 'premium')
            ->first();
            
        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif premium oyun bulunamadı.'
            ], 404);
        }
        
        $settings = $game->settings;
        $jokerType = $request->joker_type;
        
        if ($settings['jokers'][$jokerType] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu joker türü kalmadı.'
            ], 400);
        }
        
        $question = Question::find($request->question_id);
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
        
        // Socket.IO'ya joker kullanım bildirimi gönder
        $this->broadcastJokerUsed($game, $user, $jokerType, $result);
        
        return response()->json([
            'success' => true,
            'joker_type' => $jokerType,
            'result' => $result,
            'remaining_jokers' => $settings['jokers']
        ]);
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
     */
    private function getNextPremiumQuestion(IndividualGame $game): ?Question
    {
        $settings = $game->settings;
        $mediumRemaining = $settings['medium_questions_remaining'] ?? 7;
        $hardRemaining = $settings['hard_questions_remaining'] ?? 8;
        
        // Önce orta, sonra zor sorular
        if ($mediumRemaining > 0) {
            $question = Question::active()
                ->byLevel('medium')
                ->inRandomOrder()
                ->first();
                
            $settings['medium_questions_remaining'] = $mediumRemaining - 1;
        } else {
            $question = Question::active()
                ->byLevel('hard')
                ->inRandomOrder()
                ->first();
                
            $settings['hard_questions_remaining'] = $hardRemaining - 1;
        }
        
        $game->update(['settings' => $settings]);
        
        return $question;
    }
    
    /**
     * %50 Joker
     */
    private function useFiftyFiftyJoker(Question $question): array
    {
        $choices = $question->choices;
        $correctAnswer = $question->correct_answer;
        
        // Doğru cevabı ve bir yanlış cevabı bırak
        $wrongOptions = array_diff(['1', '2', '3', '4'], [$correctAnswer]);
        $removeOption = $wrongOptions[array_rand($wrongOptions)];
        
        $remainingChoices = $choices;
        unset($remainingChoices[$removeOption]);
        
        return [
            'remaining_choices' => $remainingChoices,
            'removed_option' => $removeOption
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
        $hints = [
            'Bu soru ' . $question->question_level . ' seviyesinde.',
            'Doğru cevap ' . $question->correct_answer . ' şıkkında.',
            'Kategori: ' . $question->category->name ?? 'Genel'
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
        
        return [
            'type' => 'none',
            'message' => "Ödül kazanmak için %{$minAccuracyRate} ve üzeri başarı gerekli. Mevcut başarınız: %{$accuracyRate}"
        ];
    }
    
    /**
     * @OA\Get(
     *     path="/api/quiz/premium/jokers",
     *     summary="Kullanıcı Joker Durumu",
     *     description="Kullanıcının mevcut joker sayılarını getirir.",
     *     tags={"Premium Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Joker durumu başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="jokers", type="object",
     *                 @OA\Property(property="fifty_fifty", type="integer", example=5),
     *                 @OA\Property(property="double_answer", type="integer", example=3),
     *                 @OA\Property(property="hint", type="integer", example=2)
     *             ),
     *             @OA\Property(property="total_jokers", type="integer", example=10),
     *             @OA\Property(property="user_coins", type="integer", example=1500)
     *         )
     *     )
     * )
     */
    public function getUserJokers(Request $request): JsonResponse
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
            
            Http::post("{$socketUrl}/webhook/quiz-answer-submitted", [
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
            ]);
            
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
     * Kullanıcının joker sayısını azalt
     */
    private function decrementUserJoker(User $user, string $jokerType): void
    {
        switch ($jokerType) {
            case 'fifty_fifty':
                $user->decrement('fifty_fifty_jokers');
                break;
            case 'double_answer':
                $user->decrement('double_answer_jokers');
                break;
            case 'hint':
                $user->decrement('hint_jokers');
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
                'active_game' => $activeGame
            ], 400);
        }

        $questionCount = $request->question_count ?? config('app.quiz.premium.question_count', 15);
        $timeLimit = config('app.quiz.premium.time_limit_seconds', 1800);

        // Soru dağılımı: 7 orta, 8 zor (toplam 15 soru)
        $mediumQuestions = Question::where('question_level', 'medium')
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(7)
            ->get();

        $hardQuestions = Question::where('question_level', 'hard')
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $allQuestions = $mediumQuestions->merge($hardQuestions);

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
                    'fifty_fifty' => $user->fifty_fifty_jokers,
                    'double_answer' => $user->double_answer_jokers,
                    'hint' => $user->hint_jokers
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
            'jokers' => [
                'fifty_fifty' => $user->fifty_fifty_jokers,
                'double_answer' => $user->double_answer_jokers,
                'hint' => $user->hint_jokers
            ]
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
