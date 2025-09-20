<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\TournamentUser;
use App\Models\Tournament;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Tournament Users",
 *     description="Turnuva kullanıcı işlemleri"
 * )
 */
class TournamentUserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tournament-users",
     *     summary="Kullanıcının turnuva katılımları",
     *     description="Kullanıcının turnuva katılım geçmişini listeler",
     *     tags={"Tournament Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Katılım durumu",
     *         required=false,
     *         @OA\Schema(type="string", enum={"joined","active","completed","abandoned"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Sayfa başına kayıt sayısı",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Katılım geçmişi listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');

        $query = TournamentUser::where('user_id', Auth::id())
            ->with(['tournament', 'user']);

        if ($status) {
            $query->where('status', $status);
        }

        $participations = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $participations
        ]);
    }

    /**
     * Turnuva katılım detayı
     */
    public function show(TournamentUser $tournamentUser): JsonResponse
    {
        if ($tournamentUser->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu katılım kaydına erişim yetkiniz yok.'
            ], 403);
        }

        $tournamentUser->load(['tournament', 'user']);

        return response()->json([
            'success' => true,
            'data' => $tournamentUser
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-users/{tournament}/start-game",
     *     summary="Turnuva oyun oturumu başlat",
     *     description="Turnuva için oyun oturumunu başlatır",
     *     tags={"Tournament Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament",
     *         in="path",
     *         description="Turnuva ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva oyunu başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Turnuva oyunu başlatıldı"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="session", type="object"),
     *                 @OA\Property(property="questions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="tournament", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bu turnuvaya katılmamışsınız veya turnuva aktif değil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu turnuvaya katılmamışsınız veya turnuva aktif değil")
     *         )
     *     )
     * )
     */
    public function startGame(Request $request, Tournament $tournament): JsonResponse
    {
        $tournamentUser = $tournament->tournamentUsers()
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$tournamentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya katılmamışsınız veya turnuva aktif değil.'
            ], 404);
        }

        // Aktif oyun oturumu var mı kontrol et
        $activeSession = GameSession::where('user_id', Auth::id())
            ->where('tournament_id', $tournament->id)
            ->where('status', 'active')
            ->first();

        if ($activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'Zaten aktif bir turnuva oyununuz bulunmaktadır.',
                'active_session' => $activeSession
            ], 400);
        }

        try {
            // Soruları getir
            $questions = Question::active()
                ->where('question_level', $tournament->difficulty_level)
                ->inRandomOrder()
                ->limit($tournament->question_count)
                ->get();

            if ($questions->count() < $tournament->question_count) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turnuva için yeterli soru bulunamadı.'
                ], 400);
            }

            // Oyun oturumu oluştur
            $session = GameSession::create([
                'session_id' => \Illuminate\Support\Str::uuid(),
                'user_id' => Auth::id(),
                'tournament_id' => $tournament->id,
                'game_type' => 'tournament',
                'status' => 'active',
                'total_questions' => $tournament->question_count,
                'time_remaining' => $tournament->duration_minutes * 60,
                'started_at' => now(),
                'last_activity_at' => now(),
                'current_question' => $questions->first()->toArray(),
                'game_data' => [
                    'questions' => $questions->toArray(),
                    'current_question_index' => 0,
                    'joker_remaining' => $tournamentUser->joker_hakki
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Turnuva oyunu başlatıldı.',
                'data' => [
                    'session' => $session,
                    'questions' => $questions,
                    'tournament' => $tournament
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva oyunu başlatılırken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-users/submit-answer",
     *     summary="Turnuva cevabı gönder",
     *     description="Turnuva sırasında soruya cevap verir",
     *     tags={"Tournament Users"},
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
            ->where('game_type', 'tournament')
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif turnuva oturumu bulunamadı.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $question = \App\Models\Question::findOrFail($request->question_id);
            $isCorrect = $question->correct_answer === $request->user_answer;
            
            // Puan hesapla
            $basePoints = 100;
            $timeBonus = max(0, 30 - $request->time_taken) * 2;
            $streakBonus = ($session->game_data['streak_count'] ?? 0) * 10;
            $pointsEarned = $isCorrect ? $basePoints + $timeBonus + $streakBonus : 0;
            
            // Joker kullanıldıysa puanı yarıya indir
            if ($request->is_joker_used) {
                $pointsEarned = floor($pointsEarned / 2);
            }

            // Cevabı kaydet
            $gameAnswer = $session->gameAnswers()->create([
                'question_id' => $question->id,
                'user_id' => Auth::id(),
                'user_answer' => $request->user_answer,
                'is_correct' => $isCorrect,
                'is_joker_used' => $request->is_joker_used ?? false,
                'time_taken' => $request->time_taken,
                'coins_earned' => 0, // Turnuvalarda coin kazanılmaz
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

            // Turnuva katılım kaydını güncelle
            $tournamentUser = TournamentUser::where('tournament_id', $session->tournament_id)
                ->where('user_id', Auth::id())
                ->first();

            if ($tournamentUser) {
                $tournamentUser->update([
                    'score' => $session->score,
                    'correct_answers' => $session->correct_answers,
                    'wrong_answers' => $session->wrong_answers,
                    'total_time_seconds' => now()->diffInSeconds($session->started_at),
                    'answers_detail' => $session->gameAnswers()->get()->toArray()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cevap kaydedildi.',
                'data' => [
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
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
     * Turnuva oyunu tamamla
     */
    public function completeGame(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|exists:game_sessions,session_id'
        ]);

        $session = GameSession::where('session_id', $request->session_id)
            ->where('user_id', Auth::id())
            ->where('game_type', 'tournament')
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif turnuva oturumu bulunamadı.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Oturumu tamamla
            $session->update([
                'status' => 'completed',
                'last_activity_at' => now()
            ]);

            // Turnuva katılım kaydını güncelle
            $tournamentUser = TournamentUser::where('tournament_id', $session->tournament_id)
                ->where('user_id', Auth::id())
                ->first();

            if ($tournamentUser) {
                $tournamentUser->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'score' => $session->score,
                    'correct_answers' => $session->correct_answers,
                    'wrong_answers' => $session->wrong_answers,
                    'total_time_seconds' => now()->diffInSeconds($session->started_at),
                    'answers_detail' => $session->gameAnswers()->get()->toArray()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Turnuva oyunu tamamlandı.',
                'data' => [
                    'session' => $session->fresh(),
                    'final_score' => $session->score,
                    'accuracy_rate' => $session->accuracy_rate,
                    'total_time' => now()->diffInSeconds($session->started_at)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Turnuva oyunu tamamlanırken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}