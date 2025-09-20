<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\GameAnswer;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Game Answers",
 *     description="Oyun cevap işlemleri"
 * )
 */
class GameAnswerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/game-answers/submit",
     *     summary="Cevap ver",
     *     description="Oyun sırasında soruya cevap verir",
     *     tags={"Game Answers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="session_id", type="string", description="Oyun oturum ID"),
     *                 @OA\Property(property="question_id", type="integer", description="Soru ID"),
     *                 @OA\Property(property="user_answer", type="string", enum={"1","2","3","4"}, description="Kullanıcı cevabı"),
     *                 @OA\Property(property="time_taken", type="integer", minimum=0, maximum=300, description="Cevap süresi (saniye)"),
     *                 @OA\Property(property="is_joker_used", type="boolean", description="Joker kullanıldı mı")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cevap kaydedildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cevap kaydedildi"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_correct", type="boolean", example=true),
     *                 @OA\Property(property="points_earned", type="integer", example=120),
     *                 @OA\Property(property="coins_earned", type="integer", example=10),
     *                 @OA\Property(property="total_score", type="integer", example=500),
     *                 @OA\Property(property="correct_answers", type="integer", example=8),
     *                 @OA\Property(property="wrong_answers", type="integer", example=2),
     *                 @OA\Property(property="joker_remaining", type="integer", example=2),
     *                 @OA\Property(property="streak_count", type="integer", example=3),
     *                 @OA\Property(property="progress_percentage", type="number", example=50.0),
     *                 @OA\Property(property="is_game_completed", type="boolean", example=false)
     *             )
     *         )
     *     )
     * )
     */
    public function submitAnswer(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|exists:game_sessions,session_id',
            'question_id' => 'required|exists:questions,id',
            'user_answer' => 'required|string|in:1,2,3,4',
            'time_taken' => 'required|integer|min:0|max:300',
            'is_joker_used' => 'boolean'
        ]);

        $session = GameSession::where('session_id', $request->session_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun oturumu bulunamadı.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $question = Question::findOrFail($request->question_id);
            $isCorrect = $question->correct_answer === $request->user_answer;
            
            // Puan hesapla
            $basePoints = 100;
            $timeBonus = max(0, 30 - $request->time_taken) * 2; // Hızlı cevap bonusu
            $streakBonus = ($session->game_data['streak_count'] ?? 0) * 10; // Seri bonusu
            $pointsEarned = $isCorrect ? $basePoints + $timeBonus + $streakBonus : 0;
            
            // Coin hesapla
            $coinsEarned = $isCorrect ? $question->coin_value : 0;
            
            // Joker kullanıldıysa puanı yarıya indir
            if ($request->is_joker_used) {
                $pointsEarned = floor($pointsEarned / 2);
                $coinsEarned = floor($coinsEarned / 2);
            }

            // Cevabı kaydet
            $gameAnswer = $session->gameAnswers()->create([
                'question_id' => $question->id,
                'user_id' => Auth::id(),
                'user_answer' => $request->user_answer,
                'is_correct' => $isCorrect,
                'is_joker_used' => $request->is_joker_used ?? false,
                'time_taken' => $request->time_taken,
                'coins_earned' => $coinsEarned,
                'points_earned' => $pointsEarned,
                'answered_at' => now()
            ]);

            // Oturum bilgilerini güncelle
            $gameData = $session->game_data;
            $gameData['questions_answered'] = ($gameData['questions_answered'] ?? 0) + 1;
            
            if ($isCorrect) {
                $gameData['streak_count'] = ($gameData['streak_count'] ?? 0) + 1;
                $gameData['max_streak'] = max($gameData['max_streak'] ?? 0, $gameData['streak_count']);
            } else {
                $gameData['streak_count'] = 0;
            }

            if ($request->is_joker_used) {
                $gameData['joker_remaining'] = max(0, ($gameData['joker_remaining'] ?? 3) - 1);
            }

            $session->update([
                'current_question_index' => $session->current_question_index + 1,
                'correct_answers' => $session->correct_answers + ($isCorrect ? 1 : 0),
                'wrong_answers' => $session->wrong_answers + ($isCorrect ? 0 : 1),
                'joker_used' => $session->joker_used + ($request->is_joker_used ? 1 : 0),
                'score' => $session->score + $pointsEarned,
                'game_data' => $gameData,
                'last_activity_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cevap kaydedildi.',
                'data' => [
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'coins_earned' => $coinsEarned,
                    'total_score' => $session->score,
                    'correct_answers' => $session->correct_answers,
                    'wrong_answers' => $session->wrong_answers,
                    'joker_remaining' => $gameData['joker_remaining'],
                    'streak_count' => $gameData['streak_count'],
                    'progress_percentage' => $session->progress_percentage,
                    'is_game_completed' => $session->current_question_index >= $session->total_questions
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Cevap kaydedilirken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cevap geçmişi
     */
    public function getAnswerHistory(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|exists:game_sessions,session_id'
        ]);

        $session = GameSession::where('session_id', $request->session_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun oturumu bulunamadı.'
            ], 404);
        }

        $answers = $session->gameAnswers()
            ->with('question')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $answers
        ]);
    }
}