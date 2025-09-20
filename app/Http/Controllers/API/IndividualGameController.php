<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\IndividualGame;
use App\Models\Question;
use App\Models\Category;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Individual Games",
 *     description="Bireysel oyun işlemleri"
 * )
 */
class IndividualGameController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/individual-games/create",
     *     summary="Bireysel oyun oluştur",
     *     description="Yeni bir bireysel oyun oluşturur",
     *     tags={"Individual Games"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="category_id", type="integer", description="Kategori ID"),
     *                 @OA\Property(property="game_type", type="string", enum={"individual","practice","daily_challenge"}, description="Oyun türü"),
     *                 @OA\Property(property="difficulty_level", type="string", enum={"easy","medium","hard"}, description="Zorluk seviyesi"),
     *                 @OA\Property(property="question_count", type="integer", minimum=5, maximum=50, description="Soru sayısı"),
     *                 @OA\Property(property="time_limit_seconds", type="integer", minimum=60, maximum=3600, description="Zaman sınırı (saniye)"),
     *                 @OA\Property(property="joker_count", type="integer", minimum=0, maximum=10, description="Joker sayısı")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Oyun başarıyla oluşturuldu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Oyun başarıyla oluşturuldu"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="game", type="object"),
     *                 @OA\Property(property="session", type="object"),
     *                 @OA\Property(property="questions", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hatalı istek",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Zaten aktif bir oyununuz bulunmaktadır")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'game_type' => 'required|in:individual,practice,daily_challenge',
            'difficulty_level' => 'required|in:easy,medium,hard',
            'question_count' => 'required|integer|min:5|max:50',
            'time_limit_seconds' => 'required|integer|min:60|max:3600',
            'joker_count' => 'required|integer|min:0|max:10'
        ]);

        try {
            DB::beginTransaction();

            // Kullanıcının aktif oyunu var mı kontrol et
            $activeGame = IndividualGame::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if ($activeGame) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zaten aktif bir oyununuz bulunmaktadır.',
                    'active_game' => $activeGame
                ], 400);
            }

            // Soruları getir
            $questionsQuery = Question::active();
            
            if ($request->category_id) {
                $questionsQuery->where('category_id', $request->category_id);
            }
            
            $questionsQuery->where('question_level', $request->difficulty_level);
            
            $questions = $questionsQuery->inRandomOrder()
                ->limit($request->question_count)
                ->get();

            if ($questions->count() < $request->question_count) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen kriterlere uygun yeterli soru bulunamadı.'
                ], 400);
            }

            // Bireysel oyun oluştur
            $game = IndividualGame::create([
                'user_id' => Auth::id(),
                'category_id' => $request->category_id,
                'game_type' => $request->game_type,
                'difficulty_level' => $request->difficulty_level,
                'question_count' => $request->question_count,
                'time_limit_seconds' => $request->time_limit_seconds,
                'joker_count' => $request->joker_count,
                'status' => 'pending',
                'settings' => [
                    'sound_enabled' => Auth::user()->game_sound ?? true,
                    'auto_question' => Auth::user()->auto_question ?? false
                ]
            ]);

            // Oyun oturumu oluştur
            $session = GameSession::create([
                'session_id' => Str::uuid(),
                'user_id' => Auth::id(),
                'individual_game_id' => $game->id,
                'game_type' => 'individual',
                'status' => 'active',
                'total_questions' => $request->question_count,
                'time_remaining' => $request->time_limit_seconds,
                'started_at' => now(),
                'last_activity_at' => now(),
                'current_question' => $questions->first()->toArray(),
                'game_data' => [
                    'questions' => $questions->toArray(),
                    'current_question_index' => 0,
                    'joker_remaining' => $request->joker_count
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oyun başarıyla oluşturuldu.',
                'data' => [
                    'game' => $game,
                    'session' => $session,
                    'questions' => $questions
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oyun oluşturulurken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/individual-games/active",
     *     summary="Aktif oyunu getir",
     *     description="Kullanıcının aktif oyununu getirir",
     *     tags={"Individual Games"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Aktif oyun bulundu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aktif oyun bulunamadı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Aktif oyun bulunamadı")
     *         )
     *     )
     * )
     */
    public function getActiveGame(): JsonResponse
    {
        $game = IndividualGame::where('user_id', Auth::id())
            ->where('status', 'active')
            ->with(['category', 'gameSessions' => function($query) {
                $query->where('status', 'active')->latest();
            }])
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun bulunamadı.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $game
        ]);
    }

    /**
     * Oyunu başlat
     */
    public function startGame(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun bulunamadı veya zaten başlatılmış.'
            ], 404);
        }

        $game->update([
            'status' => 'active',
            'started_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Oyun başlatıldı.',
            'data' => $game
        ]);
    }

    /**
     * Oyunu tamamla
     */
    public function completeGame(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id',
            'final_score' => 'required|integer|min:0',
            'correct_answers' => 'required|integer|min:0',
            'wrong_answers' => 'required|integer|min:0',
            'total_time_seconds' => 'required|integer|min:0',
            'coins_earned' => 'required|integer|min:0'
        ]);

        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun bulunamadı.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Oyunu tamamla
            $game->update([
                'status' => 'completed',
                'score' => $request->final_score,
                'correct_answers' => $request->correct_answers,
                'wrong_answers' => $request->wrong_answers,
                'total_time_seconds' => $request->total_time_seconds,
                'coins_earned' => $request->coins_earned,
                'completed_at' => now()
            ]);

            // Kullanıcının coin'ini güncelle
            $user = Auth::user();
            $user->increment('total_coins', $request->coins_earned);

            // Coin geçmişi kaydet
            $user->coinHistory()->create([
                'coin_amount' => $request->coins_earned,
                'transaction_type' => 'game_reward',
                'status' => 'completed',
                'description' => 'Oyun tamamlama ödülü',
                'metadata' => [
                    'game_id' => $game->id,
                    'game_type' => 'individual',
                    'score' => $request->final_score,
                    'correct_answers' => $request->correct_answers
                ],
                'balance_before' => $user->total_coins - $request->coins_earned,
                'balance_after' => $user->total_coins
            ]);

            // Oyun oturumunu tamamla
            $game->gameSessions()->where('status', 'active')->update([
                'status' => 'completed',
                'score' => $request->final_score,
                'correct_answers' => $request->correct_answers,
                'wrong_answers' => $request->wrong_answers
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oyun başarıyla tamamlandı.',
                'data' => [
                    'game' => $game->fresh(),
                    'coins_earned' => $request->coins_earned,
                    'total_coins' => $user->total_coins
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oyun tamamlanırken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Oyunu terk et
     */
    public function abandonGame(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|exists:individual_games,id'
        ]);

        $game = IndividualGame::where('id', $request->game_id)
            ->where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun bulunamadı.'
            ], 404);
        }

        $game->update([
            'status' => 'abandoned',
            'completed_at' => now()
        ]);

        // Oyun oturumunu terk et
        $game->gameSessions()->where('status', 'active')->update([
            'status' => 'abandoned'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Oyun terk edildi.',
            'data' => $game
        ]);
    }

    /**
     * Oyun geçmişi
     */
    public function gameHistory(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $gameType = $request->get('game_type');
        $status = $request->get('status');

        $query = IndividualGame::where('user_id', Auth::id())
            ->with(['category']);

        if ($gameType) {
            $query->where('game_type', $gameType);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $games = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $games
        ]);
    }

    /**
     * Oyun istatistikleri
     */
    public function gameStats(): JsonResponse
    {
        $userId = Auth::id();

        $stats = [
            'total_games' => IndividualGame::where('user_id', $userId)->count(),
            'completed_games' => IndividualGame::where('user_id', $userId)->completed()->count(),
            'total_score' => IndividualGame::where('user_id', $userId)->sum('score'),
            'total_coins_earned' => IndividualGame::where('user_id', $userId)->sum('coins_earned'),
            'average_score' => IndividualGame::where('user_id', $userId)->avg('score'),
            'best_score' => IndividualGame::where('user_id', $userId)->max('score'),
            'accuracy_rate' => IndividualGame::where('user_id', $userId)
                ->selectRaw('AVG((correct_answers / (correct_answers + wrong_answers)) * 100) as accuracy')
                ->value('accuracy') ?? 0,
            'games_by_difficulty' => IndividualGame::where('user_id', $userId)
                ->selectRaw('difficulty_level, COUNT(*) as count')
                ->groupBy('difficulty_level')
                ->get()
                ->pluck('count', 'difficulty_level'),
            'games_by_type' => IndividualGame::where('user_id', $userId)
                ->selectRaw('game_type, COUNT(*) as count')
                ->groupBy('game_type')
                ->get()
                ->pluck('count', 'game_type')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}