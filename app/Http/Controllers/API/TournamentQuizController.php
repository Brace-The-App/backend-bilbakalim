<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentUser;
use App\Models\Question;
use App\Models\User;
use App\Models\GameAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TournamentQuizController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/tournament-quiz/join",
     *     summary="Turnuvaya Katıl",
     *     description="Kullanıcıyı turnuvaya katılır. Minimum katılımcı sayısına ulaşınca turnuva başlar.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="tournament_id", type="integer", example=1)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="tournament_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuvaya başarıyla katıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Turnuvaya başarıyla katıldınız."),
     *             @OA\Property(property="tournament", type="object"),
     *             @OA\Property(property="waiting_message", type="string", example="Diğer oyuncular bekleniyor... (1/2)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Turnuva bulunamadı veya katılım hatası",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Turnuva bulunamadı.")
     *         )
     *     )
     * )
     */
    public function joinTournament(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id'
        ]);

        $user = Auth::user();
        $tournament = Tournament::find($request->tournament_id);

        // Turnuva durumu kontrolü
        if ($tournament->status !== 'upcoming') {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya katılım alınmıyor.'
            ], 400);
        }

        // Socket bağlantısı kontrolü - turnuvaya katılmak için socket bağlantısı zorunlu
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $isSocketConnected = $webhookService->checkUserConnection($user->id);

        if (!$isSocketConnected) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuvaya katılmak için socket bağlantısı gereklidir. Lütfen uygulamayı yeniden başlatın.'
            ], 400);
        }

        // Katılım kotası kontrolü
        if ($tournament->current_participants >= $tournament->max_participants) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva dolu.'
            ], 400);
        }

        // Zaten katılmış mı kontrol et
        $existingParticipation = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingParticipation) {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya zaten katıldınız.'
            ], 400);
        }

        // Katılım ücreti kontrolü
        if ($tournament->entry_fee > 0 && $user->coins < $tournament->entry_fee) {
            return response()->json([
                'success' => false,
                'message' => 'Yeterli jetonunuz yok.'
            ], 400);
        }

        // Katılım ücretini düş
        if ($tournament->entry_fee > 0) {
            $user->decrement('coins', $tournament->entry_fee);
        }

        // Turnuvaya katıl
        $tournamentUser = TournamentUser::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'joker_hakki' => 3, // 3 joker
            'score' => 0,
            'correct_answers' => 0,
            'wrong_answers' => 0,
            'total_time_seconds' => 0,
            'status' => 'waiting',  // Enum'da 'waiting' var, 'registered' yok
            'joined_at' => now(),
            'answers_detail' => []
        ]);

        // Turnuva katılımcı sayısını güncelle - manuel olarak
        $actualCount = TournamentUser::where('tournament_id', $tournament->id)->count();
        $tournament->update(['current_participants' => $actualCount]);

        // Tournament'i yeniden çek
        $tournament = $tournament->fresh();

        // Socket ile katılım bildirimi gönder
        $this->broadcastUserJoinedTournament($tournament, $user);
        $this->sendTournamentJoinWebhook($tournament, $user);

        // Minimum katılım kontrolü ve bekleme mesajı
        $minParticipants = $tournament->min_participants ?? config('app.tournament_min_participants', 2);
        $waitingMessage = null;

        if ($tournament->current_participants < $minParticipants) {
            $waitingMessage = "Diğer oyuncular bekleniyor... ({$tournament->current_participants}/{$minParticipants})";
            $this->broadcastWaitingPlayers($tournament);
        } else {
            $waitingMessage = "Turnuva başlamaya hazır! ({$tournament->current_participants}/{$minParticipants})";
        }

        return response()->json([
            'success' => true,
            'message' => 'Turnuvaya başarıyla katıldınız.',
            'tournament_user' => $tournamentUser,
            'tournament' => $tournament,
            'waiting_message' => $waitingMessage,
            'min_participants' => $minParticipants,
            'current_participants' => $tournament->current_participants,
            'ready_to_start' => $tournament->current_participants >= $minParticipants
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-quiz/leave",
     *     summary="Turnuvadan Ayrıl",
     *     description="Kullanıcıyı turnuvadan çıkarır.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="tournament_id", type="integer", example=1)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="tournament_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuvadan başarıyla ayrıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Turnuvadan ayrıldınız.")
     *         )
     *     )
     * )
     */
    public function leaveTournament(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id'
        ]);

        $user = Auth::user();
        $tournament = Tournament::find($request->tournament_id);

        $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$tournamentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya katılmamışsınız.'
            ], 404);
        }

        // Sadece turnuva başlamadan önce ayrılabilir (waiting durumunda)
        // Turnuva başladıktan sonra (active, completed, eliminated) ayrılamaz
        if (in_array($tournamentUser->status, ['active', 'completed', 'eliminated'])) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif turnuvadan ayrılamazsınız.'
            ], 400);
        }

        // Turnuva başlamışsa ayrılamaz
        if ($tournament->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva başladıktan sonra ayrılamazsınız.'
            ], 400);
        }

        // Katılım ücretini iade et
        if ($tournament->entry_fee > 0) {
            $user->increment('coins', $tournament->entry_fee);
        }

        // Turnuvadan çıkar
        $tournamentUser->delete();
        $tournament->decrement('current_participants');

        return response()->json([
            'success' => true,
            'message' => 'Turnuvadan ayrıldınız.'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-quiz/create-or-join",
     *     summary="Turnuva Oluştur veya Katıl",
     *     description="Kullanıcı yeni turnuva oluşturabilir veya mevcut boş turnuvalara katılabilir. Boş turnuva varsa oraya katılır, yoksa yeni turnuva oluşturur.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="type", type="string", enum={"time_based", "question_based"}, example="question_based"),
     *                 @OA\Property(property="question_count", type="integer", example=5, description="Soru sayısına göre turnuva için"),
     *                 @OA\Property(property="duration_minutes", type="integer", example=30, description="Süreye göre turnuva için"),
     *                 @OA\Property(property="min_participants", type="integer", example=2, description="Minimum katılımcı sayısı")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"time_based", "question_based"}, example="question_based"),
     *             @OA\Property(property="question_count", type="integer", example=5),
     *             @OA\Property(property="duration_minutes", type="integer", example=30),
     *             @OA\Property(property="min_participants", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva oluşturuldu veya mevcut turnuvaya katıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Yeni turnuva oluşturuldu."),
     *             @OA\Property(property="action", type="string", enum={"created", "joined"}, example="created"),
     *             @OA\Property(property="tournament", type="object"),
     *             @OA\Property(property="waiting_message", type="string", example="Diğer oyuncular bekleniyor... (1/2)")
     *         )
     *     )
     * )
     */
    public function createOrJoinTournament(Request $request): JsonResponse
    {

        $request->validate([
            'type' => 'required|in:time_based,question_based',
            'question_count' => 'required_if:type,question_based|integer|min:1|max:20',
            'duration_minutes' => 'required_if:type,time_based|integer|min:1|max:120',
            'min_participants' => 'nullable|integer|min:1|max:10'  // Test için minimum 1'e düşürüldü
        ]);

        $user = Auth::user();
        $minParticipants = $request->min_participants ?? config('app.tournament.min_participants', 2);

        // Socket bağlantısı kontrolü - turnuvaya katılmak için socket bağlantısı zorunlu
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $isSocketConnected = $webhookService->checkUserConnection($user->id);

        if (!$isSocketConnected) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuvaya katılmak için socket bağlantısı gereklidir. Lütfen uygulamayı yeniden başlatın.'
            ], 400);
        }

        // Yeni turnuva oluştur
        $startTime = now();
        $endTime = $request->type === 'time_based'
            ? $startTime->copy()->addMinutes((int)($request->duration_minutes ?? 5)) // Varsayılan 5 dakika
            : null; // Soru sayısına göre turnuva için süre sınırı yok

        $tournament = Tournament::create([
            'title' => $request->type === 'time_based'
                ? "Süreye Göre Turnuva (" . ($request->duration_minutes ?? 5) . " dk)"
                : "Soru Sayısına Göre Turnuva (" . ($request->question_count ?? 10) . " soru)",
            'description' => $request->type === 'time_based'
                ? ($request->duration_minutes ?? 5) . " dakikalık süreye göre turnuva"
                : ($request->question_count ?? 10) . " soruluk turnuva",
            'tournament_type' => $request->type,
            'question_count' => $request->type === 'question_based' ? ($request->question_count ?? 10) : 0,
            'min_participants' => $minParticipants,
            'start_date' => $startTime->toDateString(),
            'start_time' => $startTime,
            'end_date' => $endTime ? $endTime->toDateString() : $startTime->toDateString(),
            'end_time' => $endTime ?: $startTime,
            'status' => 'upcoming'
        ]);


        $waitingMessage = "Yeni turnuva oluşturuldu. Diğer oyuncular bekleniyor... (1/{$minParticipants})";

        // Socket bildirimi
        $this->sendTournamentJoinWebhook($tournament, $user);

        return response()->json([
            'success' => true,
            'message' => 'Yeni turnuva oluşturuldu.',
            'action' => 'created',
            'tournament' => $tournament,
            'waiting_message' => $waitingMessage
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-quiz/start",
     *     summary="Turnuva Başlat",
     *     description="Admin tarafından turnuvayı başlatır. Minimum katılımcı sayısı kontrolü yapar.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="tournament_id", type="integer", example=1)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="tournament_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva başarıyla başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Turnuva başlatıldı."),
     *             @OA\Property(property="tournament", type="object"),
     *             @OA\Property(property="first_question", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Minimum katılımcı sayısı yetersiz",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Minimum 2 katılımcı gerekli. Şu anda 1 katılımcı var.")
     *         )
     *     )
     * )
     */
    public function startTournament(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id'
        ]);

        $tournament = Tournament::find($request->tournament_id);

        // Minimum katılımcı kontrolü
        $minParticipants = $tournament->min_participants ?? config('app.tournament_min_participants', 2);

        // Gerçek katılımcı sayısını hesapla
        $actualParticipants = TournamentUser::where('tournament_id', $tournament->id)
            ->count();

        if ($actualParticipants < $minParticipants) {
            return response()->json([
                'success' => false,
                'message' => "Minimum {$minParticipants} katılımcı gerekli. Şu anda {$actualParticipants} katılımcı var."
            ], 400);
        }

        // Tüm katılımcıların socket bağlantısı kontrolü
        // Sadece waiting veya active durumundaki katılımcıları kontrol et
        $participants = TournamentUser::where('tournament_id', $tournament->id)
            ->whereIn('status', ['waiting', 'active'])
            ->with('user')
            ->get();

        $connectedParticipants = [];
        $disconnectedParticipants = [];

        // WebhookService ile socket bağlantısı kontrolü
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $userIds = $participants->pluck('user_id')->toArray();

        // Eğer hiç katılımcı yoksa hata döndür
        if (empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuvaya katılımcı bulunamadı.'
            ], 400);
        }

        $connectionStatus = $webhookService->checkUsersConnection($userIds);

        foreach ($participants as $participant) {
            $isConnected = $connectionStatus[$participant->user_id]['isConnected'] ?? false;

            if ($isConnected) {
                $connectedParticipants[] = $participant;
            } else {
                $disconnectedParticipants[] = $participant;
            }
        }

        // Eğer hiç bağlı katılımcı yoksa turnuva başlatma
        if (empty($connectedParticipants)) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva başlatılamıyor. Hiçbir katılımcı socket bağlantısında değil.'
            ], 400);
        }

        // Turnuva durumunu güncelle
        $tournament->update([
            'status' => 'active',
            'start_time' => now()
        ]);

        // Sadece bağlı katılımcıları aktif yap (enum kolonu için model üzerinden güncelle)
        $connectedUserIds = collect($connectedParticipants)->pluck('user_id');
        TournamentUser::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $connectedUserIds)
            ->get()
            ->each(function($tournamentUser) {
                $tournamentUser->status = 'active';
                $tournamentUser->save();
            });

        // Bağlantısı olmayan katılımcıları elenmiş olarak işaretle
        if (!empty($disconnectedParticipants)) {
            $disconnectedUserIds = collect($disconnectedParticipants)->pluck('user_id');
            TournamentUser::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $disconnectedUserIds)
                ->get()
                ->each(function($tournamentUser) {
                    $tournamentUser->status = 'eliminated';  // Enum'da 'eliminated' var, 'disqualified' yok
                    $tournamentUser->eliminated_at = now();
                    $tournamentUser->elimination_reason = 'disconnected';
                    $tournamentUser->save();
                });
        }

        // İlk soruyu hazırla (herkes aynı soruyu görecek)
        $firstQuestion = $this->getTournamentQuestion($tournament, $tournament->id);

        // Turnuva ayarlarını güncelle
        $settings = $tournament->settings ?? [];
        $answeredQuestionIds = [];

        // İlk soruyu answered_question_ids listesine ekle (bir daha gelmesin diye)
        if ($firstQuestion) {
            $answeredQuestionIds[] = $firstQuestion->id;
        }

        $tournament->update([
            'settings' => array_merge($settings, [
                'current_question_number' => 1,
                'current_question_id' => $firstQuestion->id ?? null,
                'question_start_time' => now(),
                'connected_participants' => count($connectedParticipants),
                'disconnected_participants' => count($disconnectedParticipants),
                'answered_question_ids' => $answeredQuestionIds // İlk soruyu listeye ekle
            ])
        ]);

        // Socket ile bildirim gönder
        $this->broadcastTournamentStart($tournament, $firstQuestion);

        // FCM ve Email bildirimleri gönder
        $this->sendTournamentStartNotifications($tournament);

        // Socket.IO webhook ile turnuva başlatma bildirimi
        $this->sendTournamentStartWebhook($tournament, $firstQuestion);

        // İsteği yapan kullanıcının joker durumunu al
        $currentUser = Auth::user();
        $currentUserTournament = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $currentUser->id)
            ->first();
        
        $jokers = [
            'fifty_fifty' => 0,
            'double_answer' => 0,
            'hint' => 0,
            'tournament_jokers' => 0
        ];
        
        if ($currentUserTournament) {
            $answersDetail = is_array($currentUserTournament->answers_detail) ? $currentUserTournament->answers_detail : [];
            
            // Eğer jokers anahtarı yoksa, başlangıç değerlerini ayarla
            if (!isset($answersDetail['jokers']) || !is_array($answersDetail['jokers'])) {
                $totalJokers = $currentUserTournament->joker_hakki ?? 3;
                // Her joker tipine eşit dağıt (kalan varsa sırayla ekle)
                $jokersPerType = floor($totalJokers / 3);
                $remainingJokers = $totalJokers % 3;
                
                $answersDetail['jokers'] = [
                    'fifty_fifty' => $jokersPerType + ($remainingJokers > 0 ? 1 : 0),
                    'double_answer' => $jokersPerType + ($remainingJokers > 1 ? 1 : 0),
                    'hint' => $jokersPerType + ($remainingJokers > 2 ? 1 : 0),
                ];
                
                // Başlangıç değerlerini kaydet
                $currentUserTournament->update(['answers_detail' => $answersDetail]);
                $currentUserTournament->refresh();
            }
            
            $jokers = [
                'fifty_fifty' => $answersDetail['jokers']['fifty_fifty'] ?? 0,
                'double_answer' => $answersDetail['jokers']['double_answer'] ?? 0,
                'hint' => $answersDetail['jokers']['hint'] ?? 0,
                'tournament_jokers' => $currentUserTournament->joker_hakki ?? 0
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Turnuva başlatıldı.',
            'tournament' => $tournament,
            'first_question' => $this->formatQuestionMultilingual($firstQuestion),
            'connected_participants' => count($connectedParticipants),
            'disconnected_participants' => count($disconnectedParticipants),
            'jokers' => $jokers
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tournament-quiz/answer",
     *     summary="Turnuva Cevabı Gönder",
     *     description="Turnuva oyununda soruya cevap gönderir. Hız bonusu hesaplanır.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="tournament_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=123),
     *                 @OA\Property(property="selected_option", type="string", example="2"),
     *                 @OA\Property(property="time_spent", type="integer", example=15),
     *                 @OA\Property(property="joker_used", type="string", example="double_answer", enum={"fifty_fifty", "double_answer", "hint"}, nullable=true, description="Kullanılan joker tipi"),
     *                 @OA\Property(property="second_option", type="string", example="3", enum={"1", "2", "3", "4"}, nullable=true, description="Çift cevap joker için ikinci seçenek")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="tournament_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="selected_option", type="string", example="2"),
     *             @OA\Property(property="time_spent", type="integer", example=15),
     *             @OA\Property(property="joker_used", type="string", example="double_answer", enum={"fifty_fifty", "double_answer", "hint"}, nullable=true),
     *             @OA\Property(property="second_option", type="string", example="3", enum={"1", "2", "3", "4"}, nullable=true)
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
     *             @OA\Property(property="total_score", type="integer", example=75),
     *             @OA\Property(property="leaderboard", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="next_question", type="object")
     *         )
     *     )
     * )
     */
    public function submitTournamentAnswer(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'question_id' => 'required|exists:questions,id',
            'selected_option' => 'required|in:1,2,3,4,5',
            'time_spent' => 'nullable|integer|min:1',
            'question_number' => 'nullable|integer|min:1', // Soru bazlı turnuvalar için soru numarası
            'joker_used' => 'nullable|in:fifty_fifty,double_answer,hint', // Kullanılan joker tipi
            'second_option' => 'nullable|in:1,2,3,4' // Çift cevap joker için ikinci seçenek
        ]);

        $user = Auth::user();
        $tournament = Tournament::find($request->tournament_id);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva bulunamadı.'
            ], 404);
        }

        // Turnuva aktif mi kontrol et - Fresh query ile tekrar çek
        $tournament = $tournament->fresh();

        // Eliminated kullanıcılar da cevap gönderebilir (ama skor güncellenmeyecek)
        $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'eliminated', 'completed'])  // Completed kullanıcılar da kontrol edilmeli
            ->first();

        if (!$tournamentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva katılımınız bulunamadı.'
            ], 404);
        }

        // Eğer turnuva completed durumundaysa, kullanıcının durumunu kontrol et
        // Eğer kullanıcı hala aktifse ve soruları bitirmemişse, cevap gönderebilmeli
        if ($tournament->status === 'completed') {
            // Kullanıcının durumunu kontrol et
            $userStatus = $tournamentUser->status;
            $userAnswerCount = count($tournamentUser->answers_detail ?? []);

            // Eğer kullanıcı hala aktifse ve soruları bitirmemişse, cevap gönderebilmeli
            if ($userStatus === 'active' && $userAnswerCount < $tournament->question_count) {
                // Kullanıcı hala aktif, cevap gönderebilir
                // Turnuva completed olsa bile, bu kullanıcı için hala aktif sayılır
            } else {
                // Kullanıcı zaten completed veya eliminated, cevap gönderemez
                return response()->json([
                    'success' => false,
                    'message' => 'Turnuva tamamlandı. Artık cevap gönderemezsiniz.'
                ], 400);
            }
        } elseif ($tournament->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva aktif değil. Mevcut durum: ' . $tournament->status
            ], 400);
        }

        // Eliminated kullanıcılar için özel kontrol
        $isEliminated = $tournamentUser->status === 'eliminated';

        // Socket bağlantısı kontrolü
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $isSocketConnected = $webhookService->checkUserConnection($user->id);

        if (!$isSocketConnected) {
            // Kullanıcıyı elenmiş olarak işaretle
            $tournamentUser->update([
                'status' => 'eliminated',  // Enum'da 'eliminated' var, 'disqualified' yok
                'eliminated_at' => now(),
                'elimination_reason' => 'disconnected'
            ]);

            // Elenme bildirimi gönder
            $this->broadcastPlayerEliminated($tournament, $user);

            return response()->json([
                'success' => false,
                'message' => 'Socket bağlantınız kesildi. Turnuvadan elendiniz.'
            ], 400);
        }

        $question = Question::find($request->question_id);
        $timeSpent = $request->time_spent ?? 30;
        
        // Eğer selected_option 5 ise, kullanıcı cevap vermemiş demektir
        // Bu durumda yanlış olarak işlenmeli ama normal JSON response dönmeli
        $answerAlreadySaved = false;
        if ($request->selected_option == '5' || $request->selected_option === 5) {
            // Cevap verilmedi, yanlış olarak işle
            $isCorrect = false;
            $answersDetail = $tournamentUser->answers_detail ?? [];
            
            $answerData = [
                'question_id' => $question->id,
                'selected_option' => null, // Cevap verilmedi
                'is_correct' => false,
                'time_spent' => $timeSpent,
                'answered_at' => now()->toISOString()
            ];
            
            $answersDetail[] = $answerData;
            $answerAlreadySaved = true; // Cevap zaten kaydedildi
            
            // Normal akışa devam et (aşağıdaki kod bloğu çalışacak)
            $jokerUsed = null; // Cevap verilmediği için joker kullanılmamış sayılır
        } else {
            // Joker kullanımı kontrolü ve çift cevap joker kontrolü
            $jokerUsed = $request->joker_used ?? null;
            $answersDetail = $tournamentUser->answers_detail ?? [];
        }
        
        // Çift cevap joker kullanıldıysa ve ikinci şık henüz gönderilmediyse, kontrol et
        if ($jokerUsed === 'double_answer' && !$request->has('second_option')) {
            // İlk şıkkın doğru olup olmadığını kontrol et
            $firstOptionCorrect = (string) $question->correct_answer === (string) $request->selected_option;
            
            // Eğer ilk şık doğruysa, ikinci şıkkı seçmeye gerek yok, direkt doğru cevap olarak kaydet
            if ($firstOptionCorrect) {
                // Doğru cevabı direkt kaydet
                $answerData = [
                    'question_id' => $question->id,
                    'selected_option' => $request->selected_option,
                    'is_correct' => true,
                    'joker_used' => 'double_answer',
                    'time_spent' => $timeSpent,
                    'answered_at' => now()->toISOString()
                ];
                
                $answersDetail[] = $answerData;
                $isCorrect = true;
                
                // Cevap zaten kaydedildi, normal akışa devam etmek için flag set et
                $answerAlreadySaved = true;
            } else {
                // İlk şık yanlışsa, ikinci şıkkı beklemeli
                $pendingAnswer = [
                    'question_id' => $question->id,
                    'selected_option' => $request->selected_option,
                    'is_pending' => true,
                    'joker_used' => 'double_answer',
                    'time_spent' => $timeSpent,
                    'answered_at' => now()->toISOString()
                ];
                
                $answersDetail[] = $pendingAnswer;
                $tournamentUser->update(['answers_detail' => $answersDetail]);
                
                // İkinci şıkkı beklediğimizi belirten response döndür
                return response()->json([
                    'success' => true,
                    'waiting_for_second_option' => true,
                    'message' => 'Lütfen ikinci şıkkı seçin.',
                    'first_option' => $request->selected_option,
                    'joker_used' => 'double_answer',
                    'first_option_correct' => false
                ]);
            }
        }
        
        // Eğer çift cevap joker kullanıldıysa ve ikinci şık da geldiyse, kontrol et
        $pendingAnswerIndex = null;
        $firstOption = $request->selected_option;
        
        if ($jokerUsed === 'double_answer' && $request->has('second_option')) {
            // Geçici olarak saklanan ilk cevabı bul (en son eklenen pending cevap)
            $pendingAnswerIndex = null;
            for ($i = count($answersDetail) - 1; $i >= 0; $i--) {
                if (isset($answersDetail[$i]['is_pending']) && 
                    $answersDetail[$i]['is_pending'] === true &&
                    $answersDetail[$i]['question_id'] == $question->id &&
                    isset($answersDetail[$i]['joker_used']) &&
                    $answersDetail[$i]['joker_used'] === 'double_answer') {
                    $pendingAnswerIndex = $i;
                    $firstOption = $answersDetail[$i]['selected_option'];
                    $timeSpent = $answersDetail[$i]['time_spent'];
                    break;
                }
            }
            
            // Çift cevap joker: iki seçenekten biri doğru olmalı
            // Tip uyumsuzluğunu önlemek için string'e çevir
            $correctAnswer = (string) $question->correct_answer;
            $firstOptionStr = (string) $firstOption;
            $secondOptionStr = (string) $request->second_option;
            $isCorrect = ($correctAnswer === $firstOptionStr) || 
                        ($correctAnswer === $secondOptionStr);
            
            // Eğer pending cevap bulunduysa, onu güncelle
            if ($pendingAnswerIndex !== null) {
                $answersDetail[$pendingAnswerIndex] = [
                    'question_id' => $question->id,
                    'selected_option' => $firstOption,
                    'second_option' => $request->second_option,
                    'is_correct' => $isCorrect,
                    'joker_used' => 'double_answer',
                    'user_answer' => $firstOption . ',' . $request->second_option,
                    'time_spent' => $timeSpent,
                    'answered_at' => $answersDetail[$pendingAnswerIndex]['answered_at']
                ];
            } else {
                // Eğer pending cevap bulunamadıysa, yeni cevap ekle
                $answerData = [
                    'question_id' => $question->id,
                    'selected_option' => $firstOption,
                    'second_option' => $request->second_option,
                    'is_correct' => $isCorrect,
                    'joker_used' => 'double_answer',
                    'user_answer' => $firstOption . ',' . $request->second_option,
                    'time_spent' => $timeSpent,
                    'answered_at' => now()->toISOString()
                ];
                $answersDetail[] = $answerData;
            }
        } else {
            // Normal cevap kontrolü - Tip uyumsuzluğunu önlemek için string'e çevir
            // Eğer $isCorrect zaten set edilmediyse (ilk şık doğruysa set edilmiş olabilir)
            if (!isset($isCorrect)) {
                $isCorrect = (string) $question->correct_answer === (string) $request->selected_option;
            }
            
            // Normal cevabı kaydet (eğer daha önce kaydedilmediyse)
            // İlk şık doğruysa ve çift cevap joker kullanıldıysa, cevap zaten kaydedildi
            if (!isset($answerAlreadySaved) || !$answerAlreadySaved) {
                $answerData = [
                    'question_id' => $question->id,
                    'selected_option' => $request->selected_option,
                    'is_correct' => $isCorrect,
                    'time_spent' => $timeSpent,
                    'answered_at' => now()->toISOString()
                ];
                
                // Joker kullanımı bilgisini ekle
                if ($jokerUsed) {
                    $answerData['joker_used'] = $jokerUsed;
                }
                
                $answersDetail[] = $answerData;
            }
        }

        // Coin değişimini hesapla (doğru cevap +coin, yanlış cevap -coin)
        $coinChange = $isCorrect ? $question->coin_value : -$question->coin_value;

        // Score = Turnuvadan kazanılan/kaybedilen toplam coin miktarı
        // Score negatif olabilir (kullanıcı coin kaybedebilir)
        $newScore = $tournamentUser->score + $coinChange;
        $status = $tournamentUser->status;

        if (!$isEliminated) {
            // Aktif kullanıcılar için coin kontrolü
            // Eğer toplam coin (başlangıç coin + turnuva coin değişimi) 0 veya negatif olursa eliminated
            // Ama score negatif olabilir (turnuvadan kaybedilen coin miktarı)
            // Eliminated kontrolü için kullanıcının genel coin'ine bakmamız gerekir
            // Şimdilik sadece score negatif olabilir, eliminated kontrolü coin sistemine göre yapılabilir
        } else {
            // Eliminated kullanıcılar da skor biriktirebilir (score negatif olabilir)
            Log::info('Eliminated kullanıcı cevap gönderdi, skor güncellendi', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'old_score' => $tournamentUser->score,
                'new_score' => $newScore,
                'coin_change' => $coinChange
            ]);
        }

        // Turnuva kullanıcısını güncelle
        $tournamentUser->update([
            'score' => $newScore, // Score = toplam coin değişimi (pozitif veya negatif olabilir)
            'correct_answers' => $isCorrect ? $tournamentUser->correct_answers + 1 : $tournamentUser->correct_answers,
            'wrong_answers' => !$isCorrect ? $tournamentUser->wrong_answers + 1 : $tournamentUser->wrong_answers,
            'total_time_seconds' => $tournamentUser->total_time_seconds + $timeSpent,
            'status' => $status,
            'answers_detail' => $answersDetail
        ]);

        // Kullanıcının coins alanını güncelle (doğru cevap +coin, yanlış cevap -coin)
        $user->refresh(); // Güncel coin değerini al
        $balanceBefore = $user->coins;
        $newBalance = max(0, $balanceBefore + $coinChange); // Coin negatif olamaz (minimum 0)

        $user->update(['coins' => $newBalance]);

        // Coin history'ye kayıt ekle
        \App\Models\CoinHistory::create([
            'user_id' => $user->id,
            'coin_amount' => $coinChange,
            'transaction_type' => $isCorrect ? 'tournament_correct_answer' : 'tournament_wrong_answer',
            'status' => 'completed',
            'description' => $isCorrect
                ? "Turnuva doğru cevap: +{$coinChange} coin"
                : "Turnuva yanlış cevap: {$coinChange} coin",
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'metadata' => [
                'tournament_id' => $tournament->id,
                'question_id' => $question->id,
                'is_correct' => $isCorrect,
                'coin_value' => $question->coin_value
            ]
        ]);

        Log::info('User coins updated', [
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'coin_change' => $coinChange,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance
        ]);

        // Liderlik tablosunu güncelle
        $this->updateLeaderboard($tournament);

        // Socket ile skor güncellemesi gönder
        $this->broadcastScoreUpdate($tournament);

        // Socket.IO webhook ile cevap bildirimi
        $this->sendTournamentAnswerWebhook($tournament, $tournamentUser, $question, $isCorrect, $coinChange);

        // Turnuva türüne göre sonraki soruya geç
        // ÖNEMLİ: Aynı turnuvadaki tüm katılımcılar aynı soruyu görmeli
        // ÖNEMLİ: Cevap verilen soru (mevcut soru) answered_question_ids listesine eklenmeli
        $nextQuestion = null;
        $settings = $tournament->settings ?? [];
        $answeredQuestionIds = $settings['answered_question_ids'] ?? [];
        $currentQuestionId = $settings['current_question_id'] ?? null;

        // ÖNEMLİ: Kullanıcının cevap verdiği soru, turnuva genelindeki mevcut soru ile eşleşmeli
        // Eğer eşleşmiyorsa, kullanıcı yanlış soruya cevap vermiş demektir
        if ($currentQuestionId && $question->id != $currentQuestionId) {
            Log::warning('User answered wrong question', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'answered_question_id' => $question->id,
                'current_question_id' => $currentQuestionId
            ]);

            // Yanlış soruya cevap verilmişse, mevcut soruyu döndür
            $nextQuestion = Question::find($currentQuestionId);
            if ($nextQuestion) {
                $nextQuestionData = $nextQuestion->toArray();
                unset($nextQuestionData['correct_answer']);

                return response()->json([
                    'success' => false,
                    'message' => 'Yanlış soruya cevap verdiniz. Lütfen mevcut soruya cevap verin.',
                    'next_question' => $nextQuestionData
                ], 400);
            }
        }

        // Cevap verilen soruyu (mevcut soru) answered_question_ids listesine ekle
        // Bu sayede bir daha gelmeyecek
        if (!in_array($question->id, $answeredQuestionIds)) {
            $answeredQuestionIds[] = $question->id;
            // Settings'i güncelle
            $tournament->update([
                'settings' => array_merge($settings, [
                    'answered_question_ids' => $answeredQuestionIds
                ])
            ]);
            $settings = $tournament->settings ?? []; // Settings'i yeniden al
        }

        if ($tournament->tournament_type === 'question_based') {
            // Soru bazlı turnuva: Her kullanıcı kendi cevap sayısına göre sıradaki soruyu görür
            // Ama tüm kullanıcılar aynı soru numarasındaki soruyu görür (deterministik)

            // Kullanıcının cevap sayısını al
            $userAnswerCount = count($answersDetail);
            $nextQuestionNumber = $userAnswerCount + 1; // Bir sonraki soru numarası

            // Kullanıcının mevcut soru numarasını güncelle
            $tournamentUser->update(['current_question_number' => $nextQuestionNumber]);

            Log::info('Question-based tournament: Answer submitted', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'user_answer_count' => $userAnswerCount,
                'next_question_number' => $nextQuestionNumber,
                'question_id' => $question->id
            ]);

            // Soru sayısına göre kullanıcı bitiş kontrolü
            if ($nextQuestionNumber > $tournament->question_count) {
                // Bu kullanıcı tüm soruları bitirdi, kullanıcıyı completed olarak işaretle
                $tournamentUser->update([
                    'status' => 'completed',
                    'finished_at' => now()
                ]);

                // Tüm aktif kullanıcıların tüm soruları bitirip bitirmediğini kontrol et
                $activeParticipants = TournamentUser::where('tournament_id', $tournament->id)
                    ->where('status', 'active')
                    ->count();

                // Eğer aktif kullanıcı kalmadıysa, turnuvayı bitir
                if ($activeParticipants === 0) {
                    $this->finishTournament($tournament);
                } else {
                    // Hala aktif kullanıcılar var, turnuvayı bitirme
                    // Diğer kullanıcılar sorularını cevaplamaya devam edebilir
                    Log::info('User completed all questions, but tournament continues', [
                        'tournament_id' => $tournament->id,
                        'user_id' => $user->id,
                        'remaining_active_participants' => $activeParticipants
                    ]);
                }

                $nextQuestion = null;
            } else {
                // Kullanıcının sıradaki soru numarası için soruyu al
                // Tüm kullanıcılar aynı soru numarasındaki soruyu görür (deterministik)
                $usedQuestionIds = $answeredQuestionIds; // Güncel liste

                // Yeni soru seç - ilk soru gibi deterministik (soru numarasına göre)
                // getTournamentQuestionDeterministic metodu kullanılan soruları hariç tutarak deterministik seçim yapar
                $nextQuestion = $this->getTournamentQuestionDeterministic($tournament, $nextQuestionNumber, $usedQuestionIds);

                if ($nextQuestion) {
                    // Yeni soruyu kullanılan sorular listesine ekle (eğer yoksa)
                    if (!in_array($nextQuestion->id, $usedQuestionIds)) {
                        $usedQuestionIds[] = $nextQuestion->id;

                        // Turnuva settings'ini güncelle (answered_question_ids)
                        $tournament->update([
                            'settings' => array_merge($settings, [
                                'answered_question_ids' => $usedQuestionIds
                            ])
                        ]);
                        $settings = $tournament->settings ?? []; // Settings'i yeniden al
                    }
                }
            }
        } elseif ($tournament->tournament_type === 'time_based') {
            // Süre bazlı turnuva: Tüm katılımcılar aynı soruyu görmeli
            // Tüm katılımcılar cevap verene kadar beklemeli
            $currentQuestionNumber = $settings['current_question_number'] ?? 1;

            Log::info('Time-based tournament: Answer submitted', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'question_id' => $question->id,
                'current_question_number' => $currentQuestionNumber
            ]);

            // Bu soruya cevap veren kullanıcı sayısını kontrol et
            // Eliminated kullanıcılar da sayılır (cevap gönderebilirler)
            $answeredCount = TournamentUser::where('tournament_id', $tournament->id)
                ->whereIn('status', ['active', 'eliminated'])
                ->whereRaw('JSON_LENGTH(answers_detail) >= ?', [$currentQuestionNumber])
                ->count();

            $activeCount = TournamentUser::where('tournament_id', $tournament->id)
                ->whereIn('status', ['active', 'eliminated'])
                ->count();

            // Tüm katılımcılar bu soruyu cevapladıysa sonraki soruya geç
            if ($activeCount === 1 || $answeredCount >= $activeCount) {
                // Sonraki soruya geç
                $nextQuestionNumber = $currentQuestionNumber + 1;

                // Yeni soru seç - ilk soru gibi deterministik (soru numarasına göre)
                // ÖNEMLİ: Bir kez sorulan soru bir daha kesinlikle gelmemeli
                // getTournamentQuestionDeterministic metodu kullanılan soruları hariç tutarak deterministik seçim yapar
                $nextQuestion = $this->getTournamentQuestionDeterministic($tournament, $nextQuestionNumber, $answeredQuestionIds);

                if ($nextQuestion) {
                    // Yeni soruyu da turnuva genelinde cevaplanan sorular listesine ekle (hemen gösterilecek)
                    // Bu sayede bir daha gelmeyecek
                    if (!in_array($nextQuestion->id, $answeredQuestionIds)) {
                        $answeredQuestionIds[] = $nextQuestion->id;
                    }

                    $tournament->update([
                        'settings' => array_merge($settings, [
                            'current_question_number' => $nextQuestionNumber,
                            'current_question_id' => $nextQuestion->id,
                            'question_start_time' => now(),
                            'answered_question_ids' => $answeredQuestionIds // Güncel liste kaydediliyor
                        ])
                    ]);

                    Log::info('Time-based tournament: New question selected for tournament', [
                        'tournament_id' => $tournament->id,
                        'new_question_id' => $nextQuestion->id,
                        'question_number' => $nextQuestionNumber,
                        'tournament_answered_question_ids' => $answeredQuestionIds,
                        'excluded_count' => count($answeredQuestionIds)
                    ]);

                    // Yeni soruyu broadcast et - tüm katılımcılar aynı soruyu görecek
                    $this->broadcastNextQuestion($tournament, $nextQuestion);
                }
            } else {
                // Henüz tüm katılımcılar cevap vermedi, mevcut soruyu döndür
                // Tüm katılımcılar aynı soruyu görmeli
                if ($currentQuestionId) {
                    $nextQuestion = Question::find($currentQuestionId);
                }
            }
        }

        // Turnuva bitiş kontrolü - Sonraki soru işlemlerinden SONRA yapılmalı
        // Çünkü checkTournamentEnd turnuva durumunu kontrol ediyor
        // Eğer önce çağrılırsa, yanlış kontrol yapabilir
        $this->checkTournamentEnd($tournament);

        // Turnuva durumunu kontrol et (fresh ile güncel durumu al)
        $tournament->refresh();
        $tournamentFinished = $tournament->status === 'completed';

        // Eğer next_question hala null ise ve turnuva bitmediyse, soru seç
        if (!$nextQuestion && !$tournamentFinished) {
            $settings = $tournament->settings ?? [];
            $answeredQuestionIds = $settings['answered_question_ids'] ?? [];

            if ($tournament->tournament_type === 'question_based') {
                // Soru bazlı turnuva: Kullanıcının cevap sayısına göre sıradaki soruyu seç
                $userAnswerCount = count($answersDetail);
                $nextQuestionNumber = $userAnswerCount + 1;

                if ($nextQuestionNumber <= $tournament->question_count) {
                    $nextQuestion = $this->getTournamentQuestionDeterministic($tournament, $nextQuestionNumber, $answeredQuestionIds);

                    if ($nextQuestion) {
                        // Yeni soruyu kullanılan sorular listesine ekle (eğer yoksa)
                        if (!in_array($nextQuestion->id, $answeredQuestionIds)) {
                            $answeredQuestionIds[] = $nextQuestion->id;
                            $tournament->update([
                                'settings' => array_merge($settings, [
                                    'answered_question_ids' => $answeredQuestionIds
                                ])
                            ]);
                        }
                    }
                }
            } else {
                // Süre bazlı turnuva: Mevcut soruyu döndür (tüm kullanıcılar cevap verene kadar)
                $currentQuestionId = $settings['current_question_id'] ?? null;

                if ($currentQuestionId) {
                    $nextQuestion = Question::find($currentQuestionId);

                    // Güvenlik: Mevcut soru answered_question_ids listesinde yoksa ekle
                    if ($currentQuestionId && !in_array($currentQuestionId, $answeredQuestionIds)) {
                        $answeredQuestionIds[] = $currentQuestionId;
                        $tournament->update([
                            'settings' => array_merge($settings, [
                                'answered_question_ids' => $answeredQuestionIds
                            ])
                        ]);
                    }
                } else {
                    // Eğer hiç soru yoksa, ilk soruyu al (deterministik)
                    $currentQuestionNumber = $settings['current_question_number'] ?? 1;
                    $nextQuestion = $this->getTournamentQuestionDeterministic($tournament, $currentQuestionNumber, $answeredQuestionIds);

                    if (!$nextQuestion) {
                        $nextQuestion = $this->getTournamentQuestion($tournament, $currentQuestionNumber);
                    }

                    if ($nextQuestion) {
                        // İlk soruyu listeye ekle
                        if (!in_array($nextQuestion->id, $answeredQuestionIds)) {
                            $answeredQuestionIds[] = $nextQuestion->id;
                        }

                        $tournament->update([
                            'settings' => array_merge($settings, [
                                'current_question_number' => 1,
                                'current_question_id' => $nextQuestion->id,
                                'question_start_time' => now(),
                                'answered_question_ids' => $answeredQuestionIds
                            ])
                        ]);
                    }
                }
            }
        }

        // Süre bazlı turnuvalarda süre kontrolü
        $timeRemaining = null;
        if ($tournament->tournament_type === 'time_based' && !$tournamentFinished) {
            if ($tournament->end_time) {
                $timeRemaining = max(0, now()->diffInSeconds($tournament->end_time));
            }
        }

        // next_question'ı formatla (correct_answer'ı gizle ve çoklu dil desteği)
        $nextQuestionData = null;
        if ($nextQuestion) {
            $nextQuestionData = $this->formatQuestionMultilingual($nextQuestion);
        }

        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_option' => $question->correct_answer,
            'coin_change' => $coinChange,
            'score' => $newScore, // Score = toplam coin değişimi (turnuvadan kazanılan/kaybedilen)
            'status' => $status,
            'leaderboard' => $this->getLeaderboard($tournament),
            'next_question' => $nextQuestionData,
            'tournament_finished' => $tournamentFinished,
            'time_remaining' => $timeRemaining
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/results/{tournament_id}",
     *     summary="Turnuva Sonuçları",
     *     description="Turnuva sonuçlarını ve liderlik tablosunu getirir.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Turnuva ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva sonuçları başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tournament", type="object"),
     *             @OA\Property(property="leaderboard", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getTournamentResults(Request $request, $tournament_id): JsonResponse
    {
        $tournament = Tournament::find($tournament_id);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva bulunamadı.'
            ], 404);
        }

        $leaderboard = $this->getLeaderboard($tournament);

        return response()->json([
            'success' => true,
            'tournament' => $tournament,
            'leaderboard' => $leaderboard,
            'winner' => $leaderboard->first()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/status/{tournament_id}",
     *     summary="Turnuva Durumu",
     *     description="Turnuva durumunu ve kullanıcının turnuvadaki konumunu getirir.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Turnuva ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva durumu başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tournament", type="object"),
     *             @OA\Property(property="user_status", type="object"),
     *             @OA\Property(property="leaderboard", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getTournamentStatus(Request $request, $tournament_id): JsonResponse
    {
        $user = Auth::user();
        $tournament = Tournament::find($tournament_id);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva bulunamadı.'
            ], 404);
        }

        $userParticipation = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        $userCanContinue = false;
        $userEliminated = false;
        $blockedReason = null;

        if ($userParticipation) {
            $status = $userParticipation->status;
            $userEliminated = in_array($status, ['eliminated', 'completed', 'disqualified']);
            $tournamentFinished = $tournament->status === 'completed';

            if ($userEliminated) {
                $blockedReason = $userParticipation->elimination_reason ?? 'eliminated';
            } elseif ($tournamentFinished) {
                $blockedReason = 'tournament_completed';
            }

            $userCanContinue = !$userEliminated && !$tournamentFinished && in_array($status, ['waiting', 'active']);

            // Frontend için ek bilgi sütunları
            $userParticipation->can_continue = $userCanContinue;
            $userParticipation->is_eliminated = $userEliminated;
            $userParticipation->blocked_reason = $blockedReason;
        }

        $leaderboard = $this->getLeaderboard($tournament);

        return response()->json([
            'success' => true,
            'tournament' => $tournament,
            'user_participation' => $userParticipation,
            'user_can_continue' => $userCanContinue,
            'user_is_eliminated' => $userEliminated,
            'user_blocked_reason' => $blockedReason,
            'leaderboard' => $leaderboard,
            'time_remaining' => $this->getTimeRemaining($tournament)
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/questions/{tournament_id}",
     *     summary="Turnuva Soruları",
     *     description="Turnuva sorularını getirir.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Turnuva ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva soruları başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="questions", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getTournamentQuestions(Request $request, $tournament_id): JsonResponse
    {
        $tournament = Tournament::find($tournament_id);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva bulunamadı.'
            ], 404);
        }

        // Turnuva aktif mi kontrol et
        if ($tournament->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva aktif değil.'
            ], 400);
        }

        $settings = $tournament->settings ?? [];

        // Turnuva türüne göre soru getir
        if ($tournament->tournament_type === 'time_based') {
            // Süre bazlı turnuva: Mevcut aktif soruyu döndür
            $currentQuestionId = $settings['current_question_id'] ?? null;

            if (!$currentQuestionId) {
                // İlk soruyu al
                $question = $this->getTournamentQuestion($tournament, 1);
                if ($question) {
                    $tournament->update([
                        'settings' => array_merge($settings, [
                            'current_question_number' => 1,
                            'current_question_id' => $question->id,
                            'question_start_time' => now()
                        ])
                    ]);
                }
            } else {
                $question = Question::find($currentQuestionId);
            }

            if (!$question) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soru bulunamadı.'
                ], 404);
            }

            // Kalan süreyi hesapla
            $timeRemaining = $this->getTimeRemaining($tournament);

            // Soruyu çoklu dil formatında formatla
            $questionData = $this->formatQuestionMultilingual($question);

            return response()->json([
                'success' => true,
                'tournament' => $tournament,
                'question' => $questionData,
                'question_number' => $settings['current_question_number'] ?? 1,
                'time_remaining' => $timeRemaining,
                'question_start_time' => $settings['question_start_time'] ?? now()->toISOString()
            ]);
        } else {
            // Soru bazlı turnuva: Kullanıcının cevap sayısına göre sıradaki soruyu getir
            $user = Auth::user();
            $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();

            $questionNumber = $request->input('question_number');
            if (!$questionNumber) {
                // Kullanıcının cevap sayısına göre sıradaki soru numarasını hesapla
                if ($tournamentUser) {
                    $userAnswerCount = count($tournamentUser->answers_detail ?? []);
                    $questionNumber = $userAnswerCount + 1;
                } else {
                    $questionNumber = 1;
                }
            } else {
                $questionNumber = (int) $questionNumber;
            }

            // Soru numarası turnuva soru sayısını aşmışsa hata döndür
            if ($questionNumber > $tournament->question_count) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tüm sorular tamamlandı.',
                    'tournament_finished' => true
                ], 400);
            }

            // Soruyu getir - deterministik seçim (tüm kullanıcılar aynı soru numarasındaki soruyu görür)
            $answeredQuestionIds = $settings['answered_question_ids'] ?? [];
            $question = $this->getTournamentQuestionDeterministic($tournament, $questionNumber, $answeredQuestionIds);

            if (!$question) {
                $question = $this->getTournamentQuestion($tournament, $questionNumber);
            }

            if (!$question) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soru bulunamadı.'
                ], 404);
            }

            // Soruyu çoklu dil formatında formatla
            $questionData = $this->formatQuestionMultilingual($question);

            return response()->json([
                'success' => true,
                'tournament' => $tournament,
                'question' => $questionData,
                'question_number' => $questionNumber,
                'total_questions' => $tournament->question_count
            ]);
        }
    }

    /**
     * Belirli soru ID'lerini hariç tutarak rastgele soru getir
     */
    private function getTournamentQuestionExcluding(Tournament $tournament, array $excludedQuestionIds): ?Question
    {
        return Question::where('is_active', true)
            ->whereNotIn('id', $excludedQuestionIds)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Belirli soru ID'lerini hariç tutarak deterministik soru getir (ilk soru gibi)
     * Soru numarasına göre deterministik seçim yapar
     * Reklam sorusu mantığı: ad_appearance_frequency'e göre kaç soruda bir reklam sorusu gösterilir
     */
    private function getTournamentQuestionDeterministic(Tournament $tournament, int $questionNumber, array $excludedQuestionIds = []): ?Question
    {
        // Reklam sorusu kontrolü
        $adAppearanceFrequencySetting = \App\Models\GeneralSetting::where('key', 'ad_appearance_frequency')->first();
        $adAppearanceFrequency = $adAppearanceFrequencySetting ? (int) $adAppearanceFrequencySetting->value : 0;
        
        // Eğer setting yoksa veya frequency 0 ise, reklam sorusu gösterilmez
        // Eğer soru numarası ad_appearance_frequency'e bölünüyorsa, reklam sorusu seç
        if ($adAppearanceFrequency > 0 && $questionNumber % $adAppearanceFrequency === 0) {
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
                // Reklam kategorisinden soru seç
                $adQuestions = Question::where('is_active', true)
                    ->where('category_id', $adCategory->id)
                    ->whereNotIn('id', $excludedQuestionIds)
                    ->orderBy('id', 'asc')
                    ->get();

                // Eğer reklam sorusu bulunduysa döndür, yoksa normal soru seçimine geç
                if ($adQuestions->isNotEmpty()) {
                    // Soru numarasına göre reklam sorusu seç
                    $adQuestionIndex = (($questionNumber / $adAppearanceFrequency) - 1) % $adQuestions->count();
                    return $adQuestions->get($adQuestionIndex);
                }
            }
            // Eğer reklam kategorisi yoksa veya reklam sorusu yoksa, normal soru seçimine geç (aşağıdaki kod devam eder)
        }

        // Normal soru seçimi
        // Önce tüm aktif soruları al (sıralı)
        $allQuestions = Question::where('is_active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->toArray();

        if (empty($allQuestions)) {
            return null;
        }

        // Soru numarasına göre başlangıç index'ini hesapla
        $startIndex = ($questionNumber - 1) % count($allQuestions);

        // Kullanılan soruları hariç tutarak, başlangıç index'inden itibaren arama yap
        $attempts = 0;
        $currentIndex = $startIndex;

        while ($attempts < count($allQuestions)) {
            $selectedQuestionId = $allQuestions[$currentIndex];

            // Eğer bu soru kullanılmadıysa, onu döndür
            if (!in_array($selectedQuestionId, $excludedQuestionIds)) {
                return Question::find($selectedQuestionId);
            }

            // Bir sonraki soruya geç (döngüsel)
            $currentIndex = ($currentIndex + 1) % count($allQuestions);
            $attempts++;
        }

        // Tüm sorular kullanıldıysa null döndür
        return null;
    }

    /**
     * Turnuva sorularını getir - Herkes aynı soruyu görür
     * Reklam sorusu mantığı: ad_appearance_frequency'e göre kaç soruda bir reklam sorusu gösterilir
     */
    private function getTournamentQuestion(Tournament $tournament, int $questionNumber): ?Question
    {
        // Reklam sorusu kontrolü
        $adAppearanceFrequencySetting = \App\Models\GeneralSetting::where('key', 'ad_appearance_frequency')->first();
        $adAppearanceFrequency = $adAppearanceFrequencySetting ? (int) $adAppearanceFrequencySetting->value : 0;
        
        // Eğer setting yoksa veya frequency 0 ise, reklam sorusu gösterilmez
        // Eğer soru numarası ad_appearance_frequency'e bölünüyorsa, reklam sorusu seç
        if ($adAppearanceFrequency > 0 && $questionNumber % $adAppearanceFrequency === 0) {
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
                // Reklam kategorisinden soru seç
                $adQuestions = Question::where('is_active', true)
                    ->where('category_id', $adCategory->id)
                    ->orderBy('id', 'asc')
                    ->get();

                // Eğer reklam sorusu bulunduysa döndür, yoksa normal soru seçimine geç
                if ($adQuestions->isNotEmpty()) {
                    // Soru numarasına göre reklam sorusu seç
                    $adQuestionIndex = (($questionNumber / $adAppearanceFrequency) - 1) % $adQuestions->count();
                    return $adQuestions->get($adQuestionIndex);
                }
            }
            // Eğer reklam kategorisi yoksa veya reklam sorusu yoksa, normal soru seçimine geç (aşağıdaki kod devam eder)
        }

        // Normal soru seçimi
        $questions = Question::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        if ($questions->isEmpty()) {
            return null;
        }

        // Soru numarasına göre mod al (soru sayısı kadar döngü yap)
        $questionIndex = ($questionNumber - 1) % $questions->count();

        return $questions->get($questionIndex);
    }

    /**
     * Turnuva bitiş kontrolü
     */
    private function checkTournamentEnd(Tournament $tournament): void
    {
        // Sadece aktif turnuvaları kontrol et
        if ($tournament->status !== 'active') {
            return;
        }

        $shouldEnd = false;
        $endReason = '';

        if ($tournament->tournament_type === 'time_based') {
            // Süreli turnuva - SADECE süre doldu mu kontrol et
            // Süre bazlı turnuvalarda süre dolana kadar devam eder
            // Kullanıcı coin'i yetiyorsa sınırsız soru cevaplayabilir
            if ($tournament->end_time && now()->isAfter($tournament->end_time)) {
                $shouldEnd = true;
                $endReason = 'time_up';

                Log::info('Time-based tournament: Time is up', [
                    'tournament_id' => $tournament->id,
                    'end_time' => $tournament->end_time,
                    'current_time' => now()
                ]);
            } else {
                Log::info('Time-based tournament: Time remaining', [
                    'tournament_id' => $tournament->id,
                    'end_time' => $tournament->end_time,
                    'current_time' => now(),
                    'time_remaining' => $tournament->end_time ? now()->diffInSeconds($tournament->end_time) : null
                ]);
            }
        } else {
            // Soru sayısına göre turnuva - tüm aktif kullanıcılar tüm soruları bitirdi mi kontrol et
            $activeParticipants = TournamentUser::where('tournament_id', $tournament->id)
                ->where('status', 'active')
                ->get();

            // Tüm aktif kullanıcıların tüm soruları bitirip bitirmediğini kontrol et
            $allActiveCompleted = true;
            foreach ($activeParticipants as $participant) {
                $userAnswerCount = count($participant->answers_detail ?? []);
                if ($userAnswerCount < $tournament->question_count) {
                    $allActiveCompleted = false;
                    break;
                }
            }

            Log::info('Tournament question check', [
                'tournament_id' => $tournament->id,
                'active_participants_count' => $activeParticipants->count(),
                'all_active_completed' => $allActiveCompleted,
                'total_questions' => $tournament->question_count
            ]);

            // Tüm aktif kullanıcılar tüm soruları bitirdiyse turnuvayı bitir
            if ($allActiveCompleted && $activeParticipants->count() > 0) {
                $shouldEnd = true;
                $endReason = 'all_questions_answered';
            }
        }

        // Aktif katılımcı kaldı mı kontrol et (waiting ve active statülerini kontrol et)
        // NOT: Tek kişilik turnuva için bu kontrol yapılmamalı
        $activeParticipants = TournamentUser::where('tournament_id', $tournament->id)
            ->whereIn('status', ['waiting', 'active'])  // Enum değerleri: waiting, active, eliminated, completed
            ->count();

        Log::info('Tournament participant check', [
            'tournament_id' => $tournament->id,
            'active_participants' => $activeParticipants,
            'min_participants' => $tournament->min_participants ?? 2
        ]);

        // Sadece çok kişilik turnuvalar için aktif katılımcı kontrolü yap
        // Tek kişilik turnuva için bu kontrol yapılmamalı
        // Süre bazlı turnuvalarda bu kontrol yapılmamalı (süre dolana kadar devam eder)
        $minParticipants = $tournament->min_participants ?? 2;
        if ($tournament->tournament_type !== 'time_based' && $minParticipants > 1 && $activeParticipants < $minParticipants) {
            $shouldEnd = true;
            $endReason = 'insufficient_participants';
        }

        if ($shouldEnd) {
            Log::info('Tournament ending', [
                'tournament_id' => $tournament->id,
                'reason' => $endReason
            ]);
            $this->endTournament($tournament, $endReason);
        }
    }

    /**
     * Turnuvayı bitir ve kazananı belirle
     */
    private function endTournament(Tournament $tournament, string $reason): void
    {
        Log::info('Ending tournament', [
            'tournament_id' => $tournament->id,
            'reason' => $reason,
            'current_status' => $tournament->status
        ]);

        // Turnuva durumunu güncelle
        $tournament->update([
            'status' => 'completed',
            'end_time' => now()
        ]);

        Log::info('Tournament status updated', [
            'tournament_id' => $tournament->id,
            'new_status' => 'completed'
        ]);

        // Final sıralamayı al
        $finalRankings = TournamentUser::where('tournament_id', $tournament->id)
            ->with('user')
            ->orderBy('score', 'desc')
            ->orderBy('correct_answers', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get();

        Log::info('Tournament final rankings', [
            'tournament_id' => $tournament->id,
            'participants_count' => $finalRankings->count()
        ]);

        // Sıralamayı güncelle
        foreach ($finalRankings as $index => $participant) {
            $participant->update([
                'rank' => $index + 1,
                'status' => 'completed'
            ]);
        }

        // Kazananı belirle
        $winner = $finalRankings->first();
        $winners = $finalRankings->take(3); // İlk 3 kişi

        // Ödülleri hesapla
        $this->calculateTournamentRewards($tournament, $winners);

        // Socket ile turnuva bitiş bildirimi gönder
        $this->broadcastTournamentFinished($tournament, $finalRankings, $winner, $reason);

        // Socket.IO webhook ile turnuva bitiş bildirimi
        $this->sendTournamentFinishedWebhook($tournament, $finalRankings, $winner, $reason);

        Log::info('Tournament ended successfully', [
            'tournament_id' => $tournament->id,
            'winner_id' => $winner ? $winner->user_id : null
        ]);
    }

    /**
     * Turnuva ödüllerini hesapla
     */
    private function calculateTournamentRewards(Tournament $tournament, $winners): void
    {
        $rewards = [
            1 => ['coins' => 1000, 'jokers' => ['fifty_fifty' => 5, 'double_answer' => 5, 'hint' => 5]], // 1. sıra
            2 => ['coins' => 500, 'jokers' => ['fifty_fifty' => 3, 'double_answer' => 3, 'hint' => 3]],  // 2. sıra
            3 => ['coins' => 250, 'jokers' => ['fifty_fifty' => 2, 'double_answer' => 2, 'hint' => 2]]   // 3. sıra
        ];

        foreach ($winners as $index => $winner) {
            $rank = $index + 1;
            if (isset($rewards[$rank])) {
                $reward = $rewards[$rank];

                // Coin ödülü
                $winner->user->increment('coins', $reward['coins']);

                // Joker ödülleri
                foreach ($reward['jokers'] as $jokerType => $count) {
                    $winner->user->increment($jokerType . '_jokers', $count);
                }
            }
        }
    }

    /**
     * Turnuva bitiş bildirimi gönder
     */
    private function broadcastTournamentFinished(Tournament $tournament, $finalRankings, $winner, string $reason): void
    {
        $webhookService = app(\App\Http\Services\WebhookService::class);

        $data = [
            'tournament_id' => $tournament->id,
            'final_rankings' => $finalRankings->map(function($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'final_score' => $participant->score,
                    'correct_answers' => $participant->correct_answers,
                    'wrong_answers' => $participant->wrong_answers,
                    'position' => $participant->rank,
                    'total_time' => $participant->total_time_seconds
                ];
            })->toArray(),
            'winner' => $winner ? [
                'user_id' => $winner->user_id,
                'name' => $winner->user->name,
                'final_score' => $winner->score,
                'correct_answers' => $winner->correct_answers
            ] : null,
            'end_reason' => $reason,
            'timestamp' => now()->toISOString()
        ];

        $webhookService->sendWebhook('tournament-finished', $data);
    }

    /**
     * Turnuva bitiş webhook'u gönder
     */
    private function sendTournamentFinishedWebhook(Tournament $tournament, $finalRankings, $winner, string $reason): void
    {
        $webhookService = app(\App\Http\Services\WebhookService::class);

        $data = [
            'tournament_id' => $tournament->id,
            'final_rankings' => $finalRankings->map(function($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'final_score' => $participant->score,
                    'correct_answers' => $participant->correct_answers,
                    'wrong_answers' => $participant->wrong_answers,
                    'position' => $participant->rank,
                    'total_time' => $participant->total_time_seconds
                ];
            })->toArray(),
            'winner' => $winner ? [
                'user_id' => $winner->user_id,
                'name' => $winner->user->name,
                'final_score' => $winner->score,
                'correct_answers' => $winner->correct_answers
            ] : null,
            'end_reason' => $reason,
            'timestamp' => now()->toISOString()
        ];

        $webhookService->sendWebhook('tournament-finished', $data);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/check-time/{tournament_id}",
     *     summary="Turnuva Süresi Kontrol",
     *     description="Turnuva süresini kontrol eder ve kalan süreyi döner.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Turnuva ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Turnuva süresi kontrol edildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="time_remaining", type="integer", example=300),
     *             @OA\Property(property="is_finished", type="boolean", example=false),
     *             @OA\Property(property="tournament_status", type="string", example="active")
     *         )
     *     )
     * )
     */
    public function checkTournamentTime(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id'
        ]);

        $tournament = Tournament::find($request->tournament_id);

        if ($tournament->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva aktif değil.'
            ], 400);
        }

        // Süreli turnuva kontrolü
        if ($tournament->tournament_type === 'time_based') {
            $timeRemaining = $this->getTimeRemaining($tournament);

            if ($timeRemaining <= 0) {
                // Turnuva süresi doldu
                $this->finishTournament($tournament);

                return response()->json([
                    'success' => true,
                    'tournament_finished' => true,
                    'message' => 'Turnuva süresi doldu.',
                    'final_leaderboard' => $this->getLeaderboard($tournament)
                ]);
            }

            return response()->json([
                'success' => true,
                'time_remaining' => $timeRemaining,
                'tournament_finished' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'tournament_finished' => false
        ]);
    }

    /**
     * Turnuva bitir
     */
    private function finishTournament(Tournament $tournament): void
    {
        Log::info('Finishing tournament (time-based)', [
            'tournament_id' => $tournament->id
        ]);

        $tournament->update([
            'status' => 'completed',
            'end_time' => now()
        ]);

        // Final sıralamayı al (tüm katılımcılar dahil - eliminated dahil)
        $finalRankings = TournamentUser::where('tournament_id', $tournament->id)
            ->with('user')
            ->orderBy('score', 'desc')
            ->orderBy('correct_answers', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get();

        // Tüm katılımcıları tamamlandı olarak işaretle (eliminated dahil - turnuva bitince tamamlanmış sayılır)
        foreach ($finalRankings as $index => $participant) {
            $participant->update([
                'rank' => $index + 1,
                'status' => 'completed'  // Eliminated olsalar bile turnuva bitince completed olur
            ]);
        }

        // Socket ile turnuva bitiş bildirimi gönder
        $this->broadcastTournamentEnd($tournament);

        // Socket.IO webhook ile turnuva bitiş bildirimi
        $this->sendTournamentEndWebhook($tournament);

        Log::info('Tournament finished (time-based)', [
            'tournament_id' => $tournament->id,
            'participants_count' => $finalRankings->count()
        ]);
    }

    /**
     * Turnuva bitişini yayınla
     */
    private function broadcastTournamentEnd(Tournament $tournament): void
    {
        // Socket.io ile turnuva bitiş bildirimi gönder
        Log::info('Tournament finished', [
            'tournament_id' => $tournament->id
        ]);
    }

    /**
     * Kullanıcı katılım bildirimi gönder
     */
    private function broadcastUserJoinedTournament(Tournament $tournament, User $user): void
    {
        // Socket.io ile kullanıcı katılım bildirimi gönder
        Log::info('User joined tournament', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'current_participants' => $tournament->current_participants
        ]);
    }

    /**
     * Bekleme durumu bildirimi gönder
     */
    private function broadcastWaitingPlayers(Tournament $tournament): void
    {
        // Socket.io ile bekleme durumu bildirimi gönder
        Log::info('Waiting for players', [
            'tournament_id' => $tournament->id,
            'current_participants' => $tournament->current_participants,
            'min_participants' => $tournament->min_participants ?? config('app.tournament_min_participants', 2)
        ]);
    }

    /**
     * Turnuva başlangıç bildirimleri gönder
     */
    private function sendTournamentStartNotifications(Tournament $tournament): void
    {
        try {
            // FCM bildirimi
            $fcmTitle = "🏆 Yeni Turnuva Başladı!";
            $fcmContent = "{$tournament->title} turnuvası başladı! Katılmak için hemen giriş yap.";

            // Email bildirimi
            $emailTitle = "Yeni Turnuva Başladı - {$tournament->title}";
            $emailContent = "Merhaba,\n\n{$tournament->title} turnuvası başladı! Katılmak için hemen uygulamaya giriş yapın.\n\nTurnuva Detayları:\n- Süre: {$tournament->duration_minutes} dakika\n- Zorluk: {$tournament->difficulty_level}\n- Katılım Ücreti: {$tournament->entry_fee} jeton\n\nBaşarılar!";

            // NotificationService kullanarak bildirim gönder
            $notificationService = app(\App\Http\Services\NotificationService::class);

            // FCM bildirimi gönder
            $notificationService->sendNotification(
                $fcmTitle,
                $fcmContent,
                'fcm',
                null // Tüm kullanıcılara gönder
            );

            // Email bildirimi gönder
            $notificationService->sendNotification(
                $emailTitle,
                $emailContent,
                'email',
                null // Tüm kullanıcılara gönder
            );

        } catch (\Exception $e) {
            Log::error('Tournament notification failed', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * Socket.IO'ya turnuva başlatma webhook gönder
     */
    private function sendTournamentStartWebhook(Tournament $tournament, $firstQuestion): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            // Katılımcı ID'lerini al
            $participants = TournamentUser::where('tournament_id', $tournament->id)
                ->pluck('user_id')
                ->toArray();

            $questionPayload = $this->formatQuestionForSocket($firstQuestion);
            $startTime = $tournament->start_time instanceof \Carbon\Carbon
                ? $tournament->start_time->toISOString()
                : now()->toISOString();

            Http::post("{$socketUrl}/socket-webhooks/webhook/tournament-started", [
                'tournament_id' => $tournament->id,
                'tournament_type' => $tournament->tournament_type,
                'participants' => $participants,
                'question_count' => $tournament->question_count,
                'time_limit' => $tournament->tournament_type === 'time_based'
                    ? ($tournament->duration_minutes ? $tournament->duration_minutes * 60 : null)
                    : null,
                'start_time' => $startTime,
                'first_question' => $questionPayload,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament start webhook sent', [
                'tournament_id' => $tournament->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send tournament start webhook', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Socket.IO'ya turnuva cevap webhook gönder
     */
    private function sendTournamentAnswerWebhook(Tournament $tournament, TournamentUser $tournamentUser, $question, bool $isCorrect, int $coinChange): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            // Speed bonus hesapla (eğer doğru cevap verildiyse ve hızlıysa)
            $speedBonus = 0;
            if ($isCorrect) {
                $settings = $tournament->settings ?? [];
                $questionStartTime = isset($settings['question_start_time'])
                    ? \Carbon\Carbon::parse($settings['question_start_time'])
                    : now();
                $timeSpent = now()->diffInSeconds($questionStartTime);

                // 10 saniyeden hızlıysa bonus ver
                if ($timeSpent <= 10) {
                    $speedBonus = max(0, 10 - $timeSpent);
                }
            }

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/tournament-answer-submitted", [
                'tournament_id' => $tournament->id,
                'user_id' => $tournamentUser->user_id,
                'question_id' => $question->id,
                'is_correct' => $isCorrect,
                'coin_change' => $coinChange,
                'score' => $tournamentUser->score, // Score = toplam coin değişimi
                'speed_bonus' => $speedBonus,
                'leaderboard' => $this->getLeaderboard($tournament),
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament answer webhook sent', [
                'tournament_id' => $tournament->id,
                'user_id' => $tournamentUser->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send tournament answer webhook', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Socket.IO'ya turnuva bitiş webhook gönder
     */
    private function sendTournamentEndWebhook(Tournament $tournament): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            $leaderboard = $this->getLeaderboard($tournament);
            $winner = $leaderboard->first();
            $winners = $leaderboard->take(3)->values()->all();

            // End reason belirle
            $endReason = 'completed';
            if ($tournament->status === 'completed') {
                $settings = $tournament->settings ?? [];
                $currentQuestion = $settings['current_question_number'] ?? 1;
                if ($currentQuestion > $tournament->question_count) {
                    $endReason = 'all_questions_answered';
                } elseif (now()->isAfter($tournament->end_time ?? now())) {
                    $endReason = 'time_up';
                }
            }

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/tournament-finished", [
                'tournament_id' => $tournament->id,
                'final_rankings' => $leaderboard->values()->all(),
                'final_leaderboard' => $leaderboard->values()->all(),
                'winner' => $winner,
                'winners' => $winners,
                'end_reason' => $endReason,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament end webhook sent', [
                'tournament_id' => $tournament->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send tournament end webhook', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Turnuva katılım bildirimi gönder
     */
    private function sendTournamentJoinWebhook(Tournament $tournament, User $user): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            // Tüm kayıtlı katılımcıları say (waiting, active durumları)
            $currentParticipants = TournamentUser::where('tournament_id', $tournament->id)
                ->whereIn('status', ['waiting', 'active'])  // Enum değerleri
                ->count();

            $minParticipants = $tournament->min_participants ?? config('app.tournament_min_participants', 2);
            $readyToStart = $currentParticipants >= $minParticipants;

            $waitingMessage = $readyToStart
                ? "Turnuva başlamaya hazır! ({$currentParticipants}/{$minParticipants})"
                : "Diğer oyuncular bekleniyor... ({$currentParticipants}/{$minParticipants})";

            $response = Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/user-joined-tournament", [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'current_participants' => $currentParticipants,
                'min_participants' => $minParticipants,
                'ready_to_start' => $readyToStart,
                'waiting_message' => $waitingMessage,
                'timestamp' => now()->toISOString()
            ]);

            if ($response->successful()) {
                Log::info('Tournament join webhook sent successfully', [
                    'tournament_id' => $tournament->id,
                    'user_id' => $user->id,
                    'current_participants' => $currentParticipants,
                    'response' => $response->json()
                ]);
            } else {
                Log::warning('Tournament join webhook failed', [
                    'tournament_id' => $tournament->id,
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send tournament join webhook', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Oyuncu elenme bildirimi gönder
     */
    private function broadcastPlayerEliminated(Tournament $tournament, User $user): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            // Kalan aktif oyuncu sayısını al
            $remainingPlayers = TournamentUser::where('tournament_id', $tournament->id)
                ->where('status', 'active')
                ->count();

            // TournamentUser bilgilerini al
            $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();

            // Pozisyonu hesapla (sıralamadan)
            $leaderboard = $this->getLeaderboard($tournament);
            $position = null;
            foreach ($leaderboard as $index => $player) {
                if ($player['user_id'] == $user->id) {
                    $position = $index + 1;
                    break;
                }
            }

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/tournament-player-eliminated", [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'reason' => $tournamentUser->elimination_reason ?? 'coins_zero',
                'remaining_players' => $remainingPlayers,
                'final_score' => $tournamentUser->score ?? 0,
                'position' => $position,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament player eliminated broadcast sent', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast tournament player eliminated', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sonraki soruyu broadcast et
     */
    private function broadcastNextQuestion(Tournament $tournament, Question $question): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');

            $settings = $tournament->settings ?? [];
            $questionNumber = $settings['current_question_number'] ?? 1;
            $questionPayload = $this->formatQuestionForSocket($question);

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/tournament-next-question", [
                'tournament_id' => $tournament->id,
                'question' => $questionPayload,
                'question_number' => $questionNumber,
                'total_questions' => $tournament->question_count,
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Tournament next question broadcast sent', [
                'tournament_id' => $tournament->id,
                'question_id' => $question->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast tournament next question', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/waiting-status/{tournament_id}",
     *     summary="Turnuva Bekleme Durumu",
     *     description="Turnuva bekleme durumunu ve katılımcı sayısını getirir.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tournament_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Turnuva ID'si"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bekleme durumu başarıyla getirildi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_waiting", type="boolean", example=true),
     *             @OA\Property(property="current_participants", type="integer", example=1),
     *             @OA\Property(property="min_participants", type="integer", example=2),
     *             @OA\Property(property="waiting_message", type="string", example="Diğer oyuncular bekleniyor... (1/2)")
     *         )
     *     )
     * )
     */
    public function getWaitingStatus(Request $request, $tournamentId): JsonResponse
    {
        $tournament = Tournament::find($tournamentId);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva bulunamadı.'
            ], 404);
        }

        $minParticipants = $tournament->min_participants ?? config('app.tournament_min_participants', 2);
        $currentParticipants = $tournament->current_participants;

        $waitingMessage = null;
        $canStart = false;

        if ($currentParticipants < $minParticipants) {
            $waitingMessage = "Diğer oyuncular bekleniyor... ({$currentParticipants}/{$minParticipants})";
        } else {
            $canStart = true;
            $waitingMessage = "Yeterli katılımcı var. Turnuva başlatılabilir.";
        }

        return response()->json([
            'success' => true,
            'tournament_id' => $tournament->id,
            'current_participants' => $currentParticipants,
            'min_participants' => $minParticipants,
            'waiting_message' => $waitingMessage,
            'can_start' => $canStart,
            'status' => $tournament->status
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/active-multiplayer",
     *     summary="Aktif Multiplayer Turnuvaları Listele",
     *     description="Aktif durumda olan multiplayer turnuvalarını listeler.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Aktif turnuvalar başarıyla listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tournaments", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function getActiveMultiplayerTournaments(): JsonResponse
    {
        // Aktif ve beklemede olan turnuvaları getir (upcoming ve active)
        $tournaments = Tournament::whereIn('status', ['upcoming', 'active'])
            ->where('tournament_type', 'time_based')
            ->with(['participants' => function($query) {
                $query->whereIn('status', ['waiting', 'upcoming']);
            }])
            ->withCount(['participants as current_participants_count' => function($query) {
                $query->where('status', 'waiting');
            }])
            ->orderBy('status', 'asc') // active önce, sonra upcoming
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($tournament) {
                $canJoin = $tournament->current_participants_count < $tournament->max_participants &&
                    in_array($tournament->status, ['upcoming', 'active']);

                $waitingMessage = '';
                if ($tournament->status === 'upcoming') {
                    if ($tournament->current_participants_count < $tournament->min_participants) {
                        $waitingMessage = "Diğer oyuncular bekleniyor... ({$tournament->current_participants_count}/{$tournament->min_participants})";
                    } else {
                        $waitingMessage = "Yeterli katılımcı var. Turnuva başlatılabilir.";
                    }
                } else if ($tournament->status === 'active') {
                    $waitingMessage = "Turnuva devam ediyor... ({$tournament->current_participants_count} katılımcı)";
                }

                return [
                    'id' => $tournament->id,
                    'title' => $tournament->title,
                    'description' => $tournament->description,
                    'tournament_type' => $tournament->tournament_type,
                    'question_count' => $tournament->question_count,
                    'min_participants' => $tournament->min_participants,
                    'max_participants' => $tournament->max_participants,
                    'current_participants' => $tournament->current_participants_count,
                    'status' => $tournament->status,
                    'start_time' => $tournament->start_time,
                    'created_at' => $tournament->created_at,
                    'can_join' => $canJoin,
                    'waiting_message' => $waitingMessage
                ];
            });

        return response()->json([
            'success' => true,
            'tournaments' => $tournaments,
            'total' => $tournaments->count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tournament-quiz/question-based",
     *     summary="Question Based Turnuvaları Listele",
     *     description="Soru sayısına göre turnuvaları listeler.",
     *     tags={"Tournament Quiz"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"upcoming", "active", "completed"}),
     *         description="Turnuva durumu filtresi"
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=20),
     *         description="Sayfa başına kayıt sayısı"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question based turnuvalar başarıyla listelendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tournaments", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total", type="integer", example=10)
     *         )
     *     )
     * )
     */
    public function getQuestionBasedTournaments(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $limit = $request->get('limit', 20);

        $query = Tournament::where('tournament_type', 'question_based')
            ->with(['participants' => function($query) {
                $query->where('status', 'waiting');
            }])
            ->withCount(['participants as current_participants_count' => function($query) {
                $query->whereIn('status', ['waiting', 'upcoming']);
            }]);

        if ($status) {
            $query->where('status', $status);
        }

        $tournaments = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($tournament) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->title,
                    'description' => $tournament->description,
                    'tournament_type' => $tournament->tournament_type,
                    'question_count' => $tournament->question_count,
                    'min_participants' => $tournament->min_participants,
                    'max_participants' => $tournament->max_participants,
                    'current_participants' => $tournament->current_participants_count,
                    'status' => $tournament->status,
                    'start_time' => $tournament->start_time,
                    'end_time' => $tournament->end_time,
                    'created_at' => $tournament->created_at,
                    'can_join' => $tournament->current_participants_count < $tournament->max_participants && $tournament->status === 'upcoming',
                    'waiting_message' => $tournament->current_participants_count < $tournament->min_participants
                        ? "Diğer oyuncular bekleniyor... ({$tournament->current_participants_count}/{$tournament->min_participants})"
                        : "Yeterli katılımcı var. Turnuva başlatılabilir."
                ];
            });

        return response()->json([
            'success' => true,
            'tournaments' => $tournaments,
            'total' => $tournaments->count(),
            'filters' => [
                'status' => $status,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Liderlik tablosunu güncelle
     */
    private function updateLeaderboard(Tournament $tournament): void
    {
        $participants = TournamentUser::where('tournament_id', $tournament->id)
            ->where('status', 'active')
            ->orderBy('score', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get();

        $rank = 1;
        foreach ($participants as $participant) {
            $participant->update(['rank' => $rank]);
            $rank++;
        }
    }

    /**
     * Liderlik tablosunu getir
     */
    private function getLeaderboard(Tournament $tournament)
    {
        // Sadece aktif, eliminated veya completed durumundaki katılımcıları göster
        // Waiting durumundaki (henüz katılmamış) kullanıcıları gösterme
        return TournamentUser::where('tournament_id', $tournament->id)
            ->whereIn('status', ['active', 'eliminated', 'completed'])
            ->with('user:id,name,avatar,profile_image')
            ->orderBy('score', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get()
            ->map(function ($participant) {
                $user = $participant->user;

                // Profil görseli tam URL
                $profileImageUrl = null;
                if ($user && !empty($user->profile_image)) {
                    $profileImageUrl = $this->formatProfileImageUrl($user->profile_image);
                }

                return [
                    'user' => $user,
                    'user_id' => $participant->user_id,
                    'score' => $participant->score,
                    'correct_answers' => $participant->correct_answers,
                    'wrong_answers' => $participant->wrong_answers,
                    'status' => $participant->status,
                    'rank' => $participant->rank,
                    'profile_image' => $profileImageUrl,
                ];
            });
    }

    /**
     * Kalan süreyi hesapla
     */
    private function getTimeRemaining(Tournament $tournament): ?int
    {
        if ($tournament->status !== 'active') {
            return null;
        }

        if ($tournament->tournament_type === 'time_based') {
            // start_time'ı kopyala, çünkü addMinutes orijinal objeyi değiştirir
            $endTime = $tournament->start_time->copy()->addMinutes($tournament->duration_minutes);
            $remaining = max(0, now()->diffInSeconds($endTime, false));
            return $remaining;
        }

        return null;
    }

    /**
     * Turnuva başlangıcını yayınla
     */
    private function broadcastTournamentStart(Tournament $tournament, Question $question): void
    {
        // Socket.io ile bildirim gönder
        // Bu kısım socket server ile entegre edilecek
        Log::info('Tournament started', [
            'tournament_id' => $tournament->id,
            'question_id' => $question->id
        ]);
    }

    /**
     * Skor güncellemesini yayınla
     */
    private function broadcastScoreUpdate(Tournament $tournament): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            $leaderboard = $this->getLeaderboard($tournament);

            Http::timeout(5)->post("{$socketUrl}/socket-webhooks/webhook/tournament-ranking-updated", [
                'tournament_id' => $tournament->id,
                'rankings' => $leaderboard->values()->all(),
                'timestamp' => now()->toISOString()
            ]);

            Log::info('Score updated and ranking webhook sent', [
                'tournament_id' => $tournament->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send ranking webhook', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
        }
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
     * Profil görseli URL'ini tam URL'e çevir
     */
    private function formatProfileImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // Eğer zaten tam URL ise, olduğu gibi döndür
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        // Eğer storage/profile_images/ ile başlıyorsa, sadece profile_images/ kısmını al
        if (strpos($imagePath, 'storage/profile_images/') !== false) {
            $imagePath = str_replace('storage/profile_images/', 'profile_images/', $imagePath);
        }

        // Eğer profile_images/ ile başlamıyorsa, ekle
        if (strpos($imagePath, 'profile_images/') !== 0) {
            $imagePath = 'profile_images/' . ltrim($imagePath, '/');
        }

        // Tam URL oluştur
        $baseUrl = config('app.url', 'https://bilbakalim.online');
        return rtrim($baseUrl, '/') . '/storage/' . $imagePath;
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
     * Socket'e gönderilecek soru formatı
     */
    private function formatQuestionForSocket(?Question $question): ?array
    {
        return $this->formatQuestionMultilingual($question);
    }
}
