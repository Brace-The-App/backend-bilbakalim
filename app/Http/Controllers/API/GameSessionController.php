<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Game Sessions",
 *     description="Oyun oturum işlemleri"
 * )
 */
class GameSessionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/game-sessions/create",
     *     summary="Oyun oturumu oluştur",
     *     description="Yeni bir oyun oturumu oluşturur",
     *     tags={"Game Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="individual_game_id", type="integer", description="Bireysel oyun ID"),
     *                 @OA\Property(property="tournament_id", type="integer", description="Turnuva ID"),
     *                 @OA\Property(property="game_type", type="string", enum={"individual","tournament","practice"}, description="Oyun türü"),
     *                 @OA\Property(property="total_questions", type="integer", minimum=5, maximum=50, description="Toplam soru sayısı"),
     *                 @OA\Property(property="time_remaining", type="integer", minimum=60, maximum=3600, description="Kalan süre (saniye)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Oyun oturumu oluşturuldu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Oyun oturumu oluşturuldu"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="session", type="object"),
     *                 @OA\Property(property="questions", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'game_type' => 'required|in:individual,tournament',
            'individual_game_id' => 'nullable|exists:individual_games,id',
            'tournament_id' => 'nullable|exists:tournaments,id',
            'total_questions' => 'required|integer|min:1|max:100',
            'time_limit_seconds' => 'required|integer|min:60|max:3600'
        ]);

        try {
            // Kullanıcının aktif oturumu var mı kontrol et
            $activeSession = GameSession::where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();

            if ($activeSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zaten aktif bir oyun oturumunuz bulunmaktadır.',
                    'active_session' => $activeSession
                ], 400);
            }

            $session = GameSession::create([
                'session_id' => Str::uuid(),
                'user_id' => Auth::id(),
                'individual_game_id' => $request->individual_game_id,
                'tournament_id' => $request->tournament_id,
                'game_type' => $request->game_type,
                'status' => 'active',
                'total_questions' => $request->total_questions,
                'time_remaining' => $request->time_limit_seconds,
                'started_at' => now(),
                'last_activity_at' => now(),
                'game_data' => [
                    'joker_remaining' => 3,
                    'questions_answered' => 0,
                    'streak_count' => 0,
                    'max_streak' => 0
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Oyun oturumu oluşturuldu.',
                'data' => $session
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Oyun oturumu oluşturulurken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/game-sessions/active",
     *     summary="Aktif oturumu getir",
     *     description="Kullanıcının aktif oyun oturumunu getirir",
     *     tags={"Game Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Aktif oturum bulundu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aktif oturum bulunamadı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Aktif oturum bulunamadı")
     *         )
     *     )
     * )
     */
    public function getActiveSession(): JsonResponse
    {
        $session = GameSession::where('user_id', Auth::id())
            ->where('status', 'active')
            ->with(['individualGame', 'tournament'])
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif oyun oturumu bulunamadı.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $session
        ]);
    }
}