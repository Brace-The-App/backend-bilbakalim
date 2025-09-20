<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Tournament;
use App\Models\Question;
use App\Models\GameSession;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Tournaments",
 *     description="Turnuva işlemleri"
 * )
 */
class TournamentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tournaments",
     *     summary="Aktif turnuvaları listele",
     *     description="Mevcut turnuvaları listeler",
     *     tags={"Tournaments"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Turnuva durumu",
     *         required=false,
     *         @OA\Schema(type="string", enum={"upcoming","active","completed"}, example="upcoming")
     *     ),
     *     @OA\Parameter(
     *         name="difficulty",
     *         in="query",
     *         description="Zorluk seviyesi",
     *         required=false,
     *         @OA\Schema(type="string", enum={"easy","medium","hard"})
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Öne çıkan turnuvalar",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
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
     *         description="Turnuvalar listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status', 'upcoming');
        $difficulty = $request->get('difficulty');
        $featured = $request->get('featured');

        $query = Tournament::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($difficulty) {
            $query->where('difficulty_level', $difficulty);
        }

        if ($featured) {
            $query->featured();
        }

        $tournaments = $query->with(['tournamentUsers' => function($query) {
            $query->where('user_id', Auth::id());
        }])
        ->orderBy('start_date', 'asc')
        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tournaments
        ]);
    }

    /**
     * Turnuva detayı
     */
    public function show(Tournament $tournament): JsonResponse
    {
        $tournament->load(['tournamentUsers' => function($query) {
            $query->where('user_id', Auth::id());
        }]);

        $userParticipation = $tournament->tournamentUsers->first();
        $isJoined = $userParticipation ? true : false;

        return response()->json([
            'success' => true,
            'data' => [
                'tournament' => $tournament,
                'is_joined' => $isJoined,
                'user_participation' => $userParticipation,
                'participants_count' => $tournament->participants_count,
                'available_slots' => $tournament->available_slots
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournaments/{tournament}/join",
     *     summary="Turnuvaya katıl",
     *     description="Belirtilen turnuvaya katılım sağlar",
     *     tags={"Tournaments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament",
     *         in="path",
     *         description="Turnuva ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="joker_count", type="integer", minimum=0, maximum=10, description="Joker sayısı")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuvaya başarıyla katıldınız",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Turnuvaya başarıyla katıldınız"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tournament", type="object"),
     *                 @OA\Property(property="participation", type="object"),
     *                 @OA\Property(property="entry_fee_paid", type="number", example=50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hatalı istek",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu turnuvaya katılım için uygun değil")
     *         )
     *     )
     * )
     */
    public function join(Request $request, Tournament $tournament): JsonResponse
    {
        $request->validate([
            'joker_count' => 'integer|min:0|max:10'
        ]);

        // Turnuva durumu kontrolü
        if ($tournament->status !== 'upcoming') {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya katılım için uygun değil.'
            ], 400);
        }

        // Kontenjan kontrolü
        if ($tournament->available_slots <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva kontenjanı dolu.'
            ], 400);
        }

        // Kullanıcı zaten katılmış mı kontrol et
        $existingParticipation = $tournament->tournamentUsers()
            ->where('user_id', Auth::id())
            ->first();

        if ($existingParticipation) {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya zaten katıldınız.'
            ], 400);
        }

        // Giriş ücreti kontrolü
        if ($tournament->entry_fee > 0) {
            $user = Auth::user();
            if ($user->total_coins < $tournament->entry_fee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Yeterli jetonunuz bulunmuyor.',
                    'required_coins' => $tournament->entry_fee,
                    'user_coins' => $user->total_coins
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            // Giriş ücretini düş
            if ($tournament->entry_fee > 0) {
                $user = Auth::user();
                $user->decrement('total_coins', $tournament->entry_fee);

                // Coin geçmişi kaydet
                $user->coinHistory()->create([
                    'coin_amount' => -$tournament->entry_fee,
                    'transaction_type' => 'tournament_entry',
                    'status' => 'completed',
                    'description' => 'Turnuva giriş ücreti',
                    'metadata' => [
                        'tournament_id' => $tournament->id,
                        'tournament_title' => $tournament->title
                    ],
                    'balance_before' => $user->total_coins + $tournament->entry_fee,
                    'balance_after' => $user->total_coins
                ]);
            }

            // Turnuvaya katılım kaydı oluştur
            $tournamentUser = $tournament->tournamentUsers()->create([
                'user_id' => Auth::id(),
                'joker_hakki' => $request->joker_count ?? 3,
                'status' => 'joined',
                'joined_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Turnuvaya başarıyla katıldınız.',
                'data' => [
                    'tournament' => $tournament,
                    'participation' => $tournamentUser,
                    'entry_fee_paid' => $tournament->entry_fee
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Turnuvaya katılırken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tournaments/{tournament}/test-start",
     *     summary="Test turnuva başlat",
     *     description="Test amaçlı turnuva başlatır",
     *     tags={"Tournaments"},
     *     @OA\Parameter(
     *         name="tournament",
     *         in="path",
     *         description="Turnuva ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test turnuva başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test turnuva başlatıldı")
     *         )
     *     )
     * )
     */
    public function testStart(Tournament $tournament): JsonResponse
    {
        try {
            // Turnuva durumunu güncelle
            $tournament->update([
                'status' => 'active',
                'start_time' => now()
            ]);

            // Test kullanıcıları oluştur
            $testUsers = [
                ['id' => 'test-user-1', 'name' => 'Test Kullanıcı 1'],
                ['id' => 'test-user-2', 'name' => 'Test Kullanıcı 2'],
                ['id' => 'test-user-3', 'name' => 'Test Kullanıcı 3']
            ];

            // Webhook ile socket server'a bildir
            $webhookController = new WebhookController();
            $webhookController->tournamentStarted($tournament, $testUsers);

            return response()->json([
                'success' => true,
                'message' => 'Test turnuva başlatıldı',
                'data' => [
                    'tournament' => $tournament,
                    'test_users' => $testUsers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test turnuva başlatılırken hata oluştu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tournaments/test/status",
     *     summary="Test durumu",
     *     description="Test turnuvalarının durumunu getirir",
     *     tags={"Tournaments"},
     *     @OA\Response(
     *         response=200,
     *         description="Test durumu getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function testStatus(): JsonResponse
    {
        $individualTournaments = Tournament::where('tournament_type', 'individual')
            ->where('status', 'upcoming')
            ->get();

        $multiplayerTournaments = Tournament::where('tournament_type', 'multiplayer')
            ->where('status', 'upcoming')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'individual_tournaments' => $individualTournaments,
                'multiplayer_tournaments' => $multiplayerTournaments,
                'socket_server_status' => 'running',
                'test_endpoints' => [
                    'individual_test' => '/api/tournaments/1/test-start',
                    'multiplayer_test' => '/api/tournaments/2/test-start'
                ]
            ]
        ]);
    }
}