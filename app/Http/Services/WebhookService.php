<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WebhookService
{
    private string $socketUrl;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct()
    {
        $this->socketUrl = config('app.socket_url', 'http://socket-server:3001');
        $this->maxRetries = config('app.webhook_max_retries', 3);
        $this->retryDelay = config('app.webhook_retry_delay', 1000); // milliseconds
    }

    /**
     * Webhook gönder (retry mekanizması ile)
     */
    public function sendWebhook(string $endpoint, array $data, int $retryCount = 0): bool
    {
        try {
            $url = $this->socketUrl . $endpoint;

            $response = Http::timeout(5)
                ->retry(2, 100)
                ->post($url, $data);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'endpoint' => $endpoint,
                    'retry_count' => $retryCount,
                    'url' => $url,
                    'data' => $data
                ]);
                return true;
            }

            throw new \Exception('HTTP ' . $response->status() . ': ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Webhook failed', [
                'endpoint' => $endpoint,
                'retry_count' => $retryCount,
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            // Retry mekanizması
            if ($retryCount < $this->maxRetries) {
                $this->scheduleRetry($endpoint, $data, $retryCount + 1);
            } else {
                $this->logFailedWebhook($endpoint, $data, $e->getMessage());
            }

            return false;
        }
    }

    /**
     * Retry'ı zamanla
     */
    private function scheduleRetry(string $endpoint, array $data, int $retryCount): void
    {
        $delay = $this->retryDelay * $retryCount; // Exponential backoff

        // Cache ile retry'ı zamanla (basit implementasyon)
        $cacheKey = "webhook_retry_{$endpoint}_{$retryCount}_" . time();
        Cache::put($cacheKey, [
            'endpoint' => $endpoint,
            'data' => $data,
            'retry_count' => $retryCount
        ], now()->addSeconds($delay / 1000));

        Log::info('Webhook retry scheduled', [
            'endpoint' => $endpoint,
            'retry_count' => $retryCount,
            'delay_ms' => $delay
        ]);
    }

    /**
     * Başarısız webhook'u logla
     */
    private function logFailedWebhook(string $endpoint, array $data, string $error): void
    {
        Log::error('Webhook permanently failed', [
            'endpoint' => $endpoint,
            'data' => $data,
            'error' => $error,
            'timestamp' => now()->toISOString()
        ]);

        // Burada veritabanına kaydetmek veya queue'ya atmak da mümkün
        // Şimdilik sadece log'a yazıyoruz
    }

    /**
     * Quiz webhook'ları
     */
    public function sendQuizStarted(int $gameId, int $userId, string $gameType, $question, array $additionalData = []): bool
    {
        return $this->sendWebhook('/webhook/quiz-started', array_merge([
            'game_id' => $gameId,
            'user_id' => $userId,
            'game_type' => $gameType,
            'question' => $question,
            'timestamp' => now()->toISOString()
        ], $additionalData));
    }

    public function sendQuizAnswer(int $gameId, int $userId, int $questionId, bool $isCorrect, int $coinsEarned, string $gameType, array $additionalData = []): bool
    {
        return $this->sendWebhook('/webhook/quiz-answer-submitted', array_merge([
            'game_id' => $gameId,
            'user_id' => $userId,
            'question_id' => $questionId,
            'is_correct' => $isCorrect,
            'coins_earned' => $coinsEarned,
            'game_type' => $gameType,
            'timestamp' => now()->toISOString()
        ], $additionalData));
    }

    public function sendQuizJokerUsed(int $gameId, int $userId, string $jokerType, array $result, array $additionalData = []): bool
    {
        return $this->sendWebhook('/webhook/quiz-joker-used', array_merge([
            'game_id' => $gameId,
            'user_id' => $userId,
            'joker_type' => $jokerType,
            'result' => $result,
            'timestamp' => now()->toISOString()
        ], $additionalData));
    }

    public function sendQuizCompleted(int $gameId, int $userId, string $gameType, array $finalStats, array $answerDetails, array $additionalData = []): bool
    {
        return $this->sendWebhook('/webhook/quiz-completed', array_merge([
            'game_id' => $gameId,
            'user_id' => $userId,
            'game_type' => $gameType,
            'final_stats' => $finalStats,
            'answer_details' => $answerDetails,
            'timestamp' => now()->toISOString()
        ], $additionalData));
    }

    /**
     * Turnuva webhook'ları
     */
    public function sendTournamentStarted(int $tournamentId, string $tournamentType, $firstQuestion, int $participantsCount, int $durationMinutes): bool
    {
        return $this->sendWebhook('/webhook/tournament-started', [
            'tournament_id' => $tournamentId,
            'tournament_type' => $tournamentType,
            'first_question' => $firstQuestion,
            'participants_count' => $participantsCount,
            'duration_minutes' => $durationMinutes,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function sendTournamentAnswer(int $tournamentId, int $userId, int $questionId, bool $isCorrect, int $scoreChange, int $currentScore, string $status, array $leaderboard): bool
    {
        return $this->sendWebhook('/webhook/tournament-answer-submitted', [
            'tournament_id' => $tournamentId,
            'user_id' => $userId,
            'question_id' => $questionId,
            'is_correct' => $isCorrect,
            'score_change' => $scoreChange,
            'current_score' => $currentScore,
            'status' => $status,
            'leaderboard' => $leaderboard,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function sendTournamentFinished(int $tournamentId, array $finalLeaderboard, $winner, int $totalParticipants): bool
    {
        return $this->sendWebhook('/webhook/tournament-finished', [
            'tournament_id' => $tournamentId,
            'final_leaderboard' => $finalLeaderboard,
            'winner' => $winner,
            'total_participants' => $totalParticipants,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Webhook durumunu kontrol et
     */
    public function checkWebhookHealth(): array
    {
        try {
            $response = Http::timeout(5)->get($this->socketUrl . '/socket-webhooks/health');

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats?->getHandlerStat('total_time') ?? 0,
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unreachable',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Kullanıcının socket bağlantısı kontrolü
     */
    public function checkUserConnection(int $userId): bool
    {
        try {
            $response = Http::timeout(3)->post($this->socketUrl . '/socket-webhooks/check-user-connection', [
                'userId' => $userId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['isConnected'] ?? false;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Socket bağlantı kontrolü hatası', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Çoklu kullanıcı socket bağlantısı kontrolü
     */
    public function checkUsersConnection(array $userIds): array
    {
        try {
            $response = Http::timeout(5)->post($this->socketUrl . '/socket-webhooks/check-users-connection', [
                'userIds' => $userIds
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['connectionStatus'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Çoklu socket bağlantı kontrolü hatası', [
                'userIds' => $userIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Socket'e bağlı tüm kullanıcı ID'leri (admin aktif/çevrimiçi sayacı).
     * Kısa cache ile panel yükünü azaltır.
     *
     * @return int[]
     */
    public function getOnlineUserIds(): array
    {
        try {
            return Cache::remember('socket_online_user_ids', 5, function () {
                $response = Http::timeout(3)->get($this->socketUrl . '/socket-webhooks/online-users');

                if (!$response->successful()) {
                    return [];
                }

                $ids = $response->json('userIds') ?? [];

                return array_values(array_unique(array_map('intval', $ids)));
            });
        } catch (\Exception $e) {
            Log::error('Online kullanıcı listesi alınamadı', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
