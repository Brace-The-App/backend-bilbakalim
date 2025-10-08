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
            'status' => 'registered',
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
        
        // Sadece bekleyen durumda ayrılabilir
        if ($tournamentUser->status !== 'waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Aktif turnuvadan ayrılamazsınız.'
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
            'duration_minutes' => 'required_if:type,time_based|integer|min:5|max:120',
            'min_participants' => 'integer|min:2|max:10'
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

        // Önce mevcut boş turnuvaları kontrol et
        $existingTournament = Tournament::where('status', 'active')
            ->where('tournament_type', $request->type)
            ->where('min_participants', $minParticipants)
            ->whereHas('participants', function($query) {
                $query->where('status', 'registered');
            }, '<', $minParticipants)
            ->whereDoesntHave('participants', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if ($existingTournament) {
            // Mevcut turnuvaya katıl
            $tournamentUser = TournamentUser::create([
                'tournament_id' => $existingTournament->id,
                'user_id' => $user->id,
                'status' => 'registered',
                'score' => 0,
                'coins' => $user->coins
            ]);

            $currentParticipants = $existingTournament->participants()->where('status', 'registered')->count();
            $waitingMessage = "Mevcut turnuvaya katıldınız. Diğer oyuncular bekleniyor... ({$currentParticipants}/{$minParticipants})";

            // Socket bildirimi
            $this->sendTournamentJoinWebhook($existingTournament, $user);

            return response()->json([
                'success' => true,
                'message' => 'Mevcut turnuvaya katıldınız.',
                'action' => 'joined',
                'tournament' => $existingTournament,
                'waiting_message' => $waitingMessage
            ]);
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

        // Kullanıcıyı turnuvaya ekle
        $tournamentUser = TournamentUser::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'score' => 0,
            'coins' => $user->coins
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
        $participants = TournamentUser::where('tournament_id', $tournament->id)
            ->with('user')
            ->get();
            
        $connectedParticipants = [];
        $disconnectedParticipants = [];
        
        // WebhookService ile socket bağlantısı kontrolü
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $userIds = $participants->pluck('user_id')->toArray();
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
        
        // Sadece bağlı katılımcıları aktif yap
        TournamentUser::where('tournament_id', $tournament->id)
            ->whereIn('user_id', collect($connectedParticipants)->pluck('user_id'))
            ->update(['status' => 'participating']);
            
        // Bağlantısı olmayan katılımcıları elenmiş olarak işaretle
        if (!empty($disconnectedParticipants)) {
            TournamentUser::where('tournament_id', $tournament->id)
                ->whereIn('user_id', collect($disconnectedParticipants)->pluck('user_id'))
                ->update([
                    'status' => 'disqualified',
                    'eliminated_at' => now(),
                    'elimination_reason' => 'disconnected'
                ]);
        }
        
        // İlk soruyu hazırla (herkes aynı soruyu görecek)
        $firstQuestion = $this->getTournamentQuestion($tournament, 1);
        
        // Turnuva ayarlarını güncelle
        $tournament->update([
            'settings' => array_merge($tournament->settings ?? [], [
                'current_question_number' => 1,
                'current_question_id' => $firstQuestion->id,
                'question_start_time' => now(),
                'connected_participants' => count($connectedParticipants),
                'disconnected_participants' => count($disconnectedParticipants)
            ])
        ]);
        
        // Socket ile bildirim gönder
        $this->broadcastTournamentStart($tournament, $firstQuestion);
        
        // FCM ve Email bildirimleri gönder
        $this->sendTournamentStartNotifications($tournament);
        
        // Socket.IO webhook ile turnuva başlatma bildirimi
        $this->sendTournamentStartWebhook($tournament, $firstQuestion);
        
        return response()->json([
            'success' => true,
            'message' => 'Turnuva başlatıldı.',
            'tournament' => $tournament,
            'first_question' => $firstQuestion,
            'connected_participants' => count($connectedParticipants),
            'disconnected_participants' => count($disconnectedParticipants)
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
     *                 @OA\Property(property="time_spent", type="integer", example=15)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="tournament_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=123),
     *             @OA\Property(property="selected_option", type="string", example="2"),
     *             @OA\Property(property="time_spent", type="integer", example=15)
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
            'selected_option' => 'required|in:1,2,3,4',
            'time_spent' => 'nullable|integer|min:1'
        ]);
        
        $user = Auth::user();
        $tournament = Tournament::find($request->tournament_id);
        
        // Turnuva aktif mi kontrol et
        if ($tournament->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Turnuva aktif değil.'
            ], 400);
        }
        
        $tournamentUser = TournamentUser::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('status', 'participating')
            ->first();
            
        if (!$tournamentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif turnuva katılımınız bulunamadı.'
            ], 404);
        }
        
        // Socket bağlantısı kontrolü
        $webhookService = app(\App\Http\Services\WebhookService::class);
        $isSocketConnected = $webhookService->checkUserConnection($user->id);
        
        if (!$isSocketConnected) {
            // Kullanıcıyı elenmiş olarak işaretle
            $tournamentUser->update([
                'status' => 'disqualified',
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
        $isCorrect = $question->correct_answer === $request->selected_option;
        $timeSpent = $request->time_spent ?? 30;
        
        // Cevabı kaydet
        $answersDetail = $tournamentUser->answers_detail ?? [];
        $answersDetail[] = [
            'question_id' => $question->id,
            'selected_option' => $request->selected_option,
            'is_correct' => $isCorrect,
            'time_spent' => $timeSpent,
            'answered_at' => now()->toISOString()
        ];
        
        // Skor hesapla (hız bonusu yok)
        $scoreChange = $isCorrect ? $question->coin_value : -$question->coin_value;
        $newScore = $tournamentUser->score + $scoreChange;
        
        // Jeton sıfırlandı mı kontrol et
        $status = 'participating';
        if ($newScore <= 0) {
            $status = 'disqualified';
            $newScore = 0;
            
            // Elenme bildirimi gönder
            $this->broadcastPlayerEliminated($tournament, $user);
        }
        
        // Turnuva kullanıcısını güncelle
        $tournamentUser->update([
            'score' => $newScore,
            'correct_answers' => $isCorrect ? $tournamentUser->correct_answers + 1 : $tournamentUser->correct_answers,
            'wrong_answers' => !$isCorrect ? $tournamentUser->wrong_answers + 1 : $tournamentUser->wrong_answers,
            'total_time_seconds' => $tournamentUser->total_time_seconds + $timeSpent,
            'status' => $status,
            'answers_detail' => $answersDetail
        ]);
        
        // Tournament'te coin güncellemesi yapılmaz, sadece tournament score'u güncellenir
        
        // Liderlik tablosunu güncelle
        $this->updateLeaderboard($tournament);
        
        // Socket ile skor güncellemesi gönder
        $this->broadcastScoreUpdate($tournament);
        
        // Socket.IO webhook ile cevap bildirimi
        $this->sendTournamentAnswerWebhook($tournament, $tournamentUser, $question, $isCorrect, $scoreChange);
        
        // Turnuva bitiş kontrolü
        $this->checkTournamentEnd($tournament);
        
        // Turnuva türüne göre sonraki soruya geç
        $nextQuestion = null;
        if ($tournament->tournament_type === 'question_based') {
            $settings = $tournament->settings ?? [];
            $currentQuestionNumber = $settings['current_question_number'] ?? 1;
            
            // Eğer tüm katılımcılar bu soruyu cevapladıysa sonraki soruya geç
            $answeredCount = TournamentUser::where('tournament_id', $tournament->id)
                ->where('status', 'active')
                ->where('current_question_number', $currentQuestionNumber)
                ->count();
                
            $activeCount = TournamentUser::where('tournament_id', $tournament->id)
                ->where('status', 'active')
                ->count();
                
            if ($answeredCount >= $activeCount) {
                // Sonraki soruya geç
                $nextQuestionNumber = $currentQuestionNumber + 1;
                $nextQuestion = $this->getTournamentQuestion($tournament, $nextQuestionNumber);
                
                $tournament->update([
                    'settings' => array_merge($settings, [
                        'current_question_number' => $nextQuestionNumber,
                        'current_question_id' => $nextQuestion->id,
                        'question_start_time' => now()
                    ])
                ]);
                
                // Tüm aktif katılımcıların soru numarasını güncelle
                TournamentUser::where('tournament_id', $tournament->id)
                    ->where('status', 'active')
                    ->update(['current_question_number' => $nextQuestionNumber]);
                
                // Sonraki soruyu broadcast et
                $this->broadcastNextQuestion($tournament, $nextQuestion);
                
                // Soru sayısına göre turnuva bitiş kontrolü
                if ($nextQuestionNumber > $tournament->max_questions) {
                    // Tüm sorular bitti, turnuvayı bitir
                    $this->finishTournament($tournament);
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_option' => $question->correct_answer,
            'score_change' => $scoreChange,
            'current_score' => $newScore,
            'status' => $status,
            'leaderboard' => $this->getLeaderboard($tournament),
            'next_question' => $nextQuestion
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
    public function getTournamentResults(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id'
        ]);
        
        $tournament = Tournament::find($request->tournament_id);
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
            
        $leaderboard = $this->getLeaderboard($tournament);
        
        return response()->json([
            'success' => true,
            'tournament' => $tournament,
            'user_participation' => $userParticipation,
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
        
        // İlk soruyu getir
        $question = $this->getTournamentQuestion($tournament, 1);
        
        return response()->json([
            'success' => true,
            'tournament' => $tournament,
            'question' => $question,
            'question_number' => 1
        ]);
    }
    
    /**
     * Turnuva sorularını getir - Herkes aynı soruyu görür
     */
    private function getTournamentQuestion(Tournament $tournament, int $questionNumber): ?Question
    {
        // Basit soru seçimi - ilk aktif soruyu al
        return Question::where('is_active', true)->first();
    }

    /**
     * Turnuva bitiş kontrolü
     */
    private function checkTournamentEnd(Tournament $tournament): void
    {
        if ($tournament->status !== 'active') {
            return;
        }

        $shouldEnd = false;
        $endReason = '';

        if ($tournament->tournament_type === 'time_based') {
            // Süreli turnuva - süre doldu mu kontrol et (test için geçici olarak devre dışı)
            // if (now()->isAfter($tournament->end_time)) {
            //     $shouldEnd = true;
            //     $endReason = 'time_up';
            // }
        } else {
            // Soru sayısına göre turnuva - tüm sorular bitti mi kontrol et
            $settings = $tournament->settings ?? [];
            $currentQuestionNumber = $settings['current_question_number'] ?? 1;
            
            if ($currentQuestionNumber > $tournament->question_count) {
                $shouldEnd = true;
                $endReason = 'all_questions_answered';
            }
        }

        // Aktif katılımcı kaldı mı kontrol et
        $activeParticipants = TournamentUser::where('tournament_id', $tournament->id)
            ->where('status', 'participating')
            ->count();

        if ($activeParticipants <= 1) {
            $shouldEnd = true;
            $endReason = 'only_one_participant_left';
        }

        if ($shouldEnd) {
            $this->endTournament($tournament, $endReason);
        }
    }

    /**
     * Turnuvayı bitir ve kazananı belirle
     */
    private function endTournament(Tournament $tournament, string $reason): void
    {
        // Turnuva durumunu güncelle
        $tournament->update([
            'status' => 'completed',
            'end_time' => now()
        ]);

        // Final sıralamayı al
        $finalRankings = TournamentUser::where('tournament_id', $tournament->id)
            ->with('user')
            ->orderBy('score', 'desc')
            ->orderBy('correct_answers', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get();

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
        $tournament->update([
            'status' => 'completed',
            'end_time' => now()
        ]);
        
        // Tüm aktif katılımcıları tamamlandı olarak işaretle
        TournamentUser::where('tournament_id', $tournament->id)
            ->where('status', 'active')
            ->update(['status' => 'completed']);
        
        // Socket ile turnuva bitiş bildirimi gönder
        $this->broadcastTournamentEnd($tournament);
        
        // Socket.IO webhook ile turnuva bitiş bildirimi
        $this->sendTournamentEndWebhook($tournament);
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
            
            Http::post("{$socketUrl}/webhook/tournament-started", [
                'tournament_id' => $tournament->id,
                'participants' => $participants,
                'question_count' => $tournament->question_count,
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
    private function sendTournamentAnswerWebhook(Tournament $tournament, TournamentUser $tournamentUser, $question, bool $isCorrect, int $scoreChange): void
    {
        try {
            $socketUrl = config('app.socket_url', 'http://socket-server:3001');
            
            Http::post("{$socketUrl}/webhook/tournament-answer-submitted", [
                'tournament_id' => $tournament->id,
                'user_id' => $tournamentUser->user_id,
                'question_id' => $question->id,
                'is_correct' => $isCorrect,
                'score_change' => $scoreChange,
                'current_score' => $tournamentUser->score,
                'status' => $tournamentUser->status,
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
            
            Http::post("{$socketUrl}/webhook/tournament-finished", [
                'tournament_id' => $tournament->id,
                'final_leaderboard' => $this->getLeaderboard($tournament),
                'winner' => $this->getLeaderboard($tournament)->first(),
                'total_participants' => $tournament->current_participants,
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
            
            $currentParticipants = $tournament->participants()->where('status', 'registered')->count();
            
            Http::post("{$socketUrl}/webhook/user-joined-tournament", [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'current_participants' => $currentParticipants,
                'min_participants' => $tournament->min_participants,
                'waiting_message' => "Diğer oyuncular bekleniyor... ({$currentParticipants}/{$tournament->min_participants})",
                'timestamp' => now()->toISOString()
            ]);
            
            Log::info('Tournament join webhook sent', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'current_participants' => $currentParticipants
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send tournament join webhook', [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
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
                ->where('status', 'participating')
                ->count();
            
            Http::post("{$socketUrl}/webhook/tournament-player-eliminated", [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'reason' => 'coins_zero',
                'remaining_players' => $remainingPlayers,
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
            
            Http::post("{$socketUrl}/webhook/tournament-next-question", [
                'tournament_id' => $tournament->id,
                'question' => $question,
                'question_number' => $tournament->settings['current_question_number'] ?? 1,
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
        return TournamentUser::where('tournament_id', $tournament->id)
            ->with('user:id,name,avatar')
            ->orderBy('score', 'desc')
            ->orderBy('total_time_seconds', 'asc')
            ->get()
            ->map(function ($participant) {
                return [
                    'user' => $participant->user,
                    'score' => $participant->score,
                    'correct_answers' => $participant->correct_answers,
                    'wrong_answers' => $participant->wrong_answers,
                    'status' => $participant->status,
                    'rank' => $participant->rank
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
            $endTime = $tournament->start_time->addMinutes($tournament->duration_minutes);
            $remaining = $endTime->diffInSeconds(now());
            return max(0, $remaining);
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
        // Socket.io ile skor güncellemesi gönder
        Log::info('Score updated', [
            'tournament_id' => $tournament->id
        ]);
    }
}
