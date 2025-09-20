<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    //for dev
   // private $socketServerUrl = 'http://socket-server:3001';

   //prod
   private $socketServerUrl = 'http://bilbakalim.online:3001';


    /**
     * Soru oluşturulduğunda Socket.IO'ya bildir
     */
    public function questionCreated($question, $categoryId = null, $tournamentId = null)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/question-created', [
                'question' => $question,
                'categoryId' => $categoryId,
                'tournamentId' => $tournamentId
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Socket.IO webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Soru güncellendiğinde Socket.IO'ya bildir
     */
    public function questionUpdated($question, $categoryId = null, $tournamentId = null)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/question-updated', [
                'question' => $question,
                'categoryId' => $categoryId,
                'tournamentId' => $tournamentId
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Socket.IO webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Soru silindiğinde Socket.IO'ya bildir
     */
    public function questionDeleted($questionId, $categoryId = null, $tournamentId = null)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/question-deleted', [
                'questionId' => $questionId,
                'categoryId' => $categoryId,
                'tournamentId' => $tournamentId
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Socket.IO webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kategori güncellendiğinde Socket.IO'ya bildir
     */
    public function categoryUpdated($category)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/category-updated', [
                'category' => $category
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Socket.IO webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Turnuva güncellendiğinde Socket.IO'ya bildir
     */
    public function tournamentUpdated($tournament)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/tournament-updated', [
                'tournament' => $tournament
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Socket.IO webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== TURNUVA WEBHOOK'LARI =====

    /**
     * Turnuva başlatıldığında Socket.IO'ya bildir
     */
    public function tournamentStarted($tournament, $participants = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/tournament-started', [
                'tournament' => $tournament,
                'participants' => $participants,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Turnuva başlatma webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Turnuva bittiğinde Socket.IO'ya bildir
     */
    public function tournamentFinished($tournament, $finalRankings = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/tournament-finished', [
                'tournament' => $tournament,
                'finalRankings' => $finalRankings,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Turnuva bitiş webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kullanıcı turnuvaya katıldığında Socket.IO'ya bildir
     */
    public function userJoinedTournament($tournamentId, $userId, $userData = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/user-joined-tournament', [
                'tournamentId' => $tournamentId,
                'userId' => $userId,
                'userData' => $userData,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Kullanıcı katılım webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kullanıcı turnuvadan ayrıldığında Socket.IO'ya bildir
     */
    public function userLeftTournament($tournamentId, $userId)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/user-left-tournament', [
                'tournamentId' => $tournamentId,
                'userId' => $userId,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Kullanıcı ayrılma webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== BİREYSEL OYUN WEBHOOK'LARI =====

    /**
     * Bireysel oyun başlatıldığında Socket.IO'ya bildir
     */
    public function individualGameStarted($gameId, $userId, $gameData = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/individual-game-started', [
                'gameId' => $gameId,
                'userId' => $userId,
                'gameData' => $gameData,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Bireysel oyun başlatma webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Bireysel oyun bittiğinde Socket.IO'ya bildir
     */
    public function individualGameFinished($gameId, $userId, $finalScore = 0)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/individual-game-finished', [
                'gameId' => $gameId,
                'userId' => $userId,
                'finalScore' => $finalScore,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Bireysel oyun bitiş webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== CEVAP WEBHOOK'LARI =====

    /**
     * Cevap verildiğinde Socket.IO'ya bildir
     */
    public function answerSubmitted($gameId, $userId, $questionId, $isCorrect, $pointsEarned, $timeTaken = 0)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/answer-submitted', [
                'gameId' => $gameId,
                'userId' => $userId,
                'questionId' => $questionId,
                'isCorrect' => $isCorrect,
                'pointsEarned' => $pointsEarned,
                'timeTaken' => $timeTaken,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Cevap gönderme webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sıralama güncellendiğinde Socket.IO'ya bildir
     */
    public function rankingUpdated($tournamentId, $rankings = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/ranking-updated', [
                'tournamentId' => $tournamentId,
                'rankings' => $rankings,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Sıralama güncelleme webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== ÖDEME WEBHOOK'LARI =====

    /**
     * Ödeme tamamlandığında Socket.IO'ya bildir
     */
    public function paymentCompleted($userId, $paymentData = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/payment-completed', [
                'userId' => $userId,
                'paymentData' => $paymentData,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Ödeme tamamlama webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Jeton satın alındığında Socket.IO'ya bildir
     */
    public function coinPurchased($userId, $coinAmount, $totalCoins)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/coin-purchased', [
                'userId' => $userId,
                'coinAmount' => $coinAmount,
                'totalCoins' => $totalCoins,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Jeton satın alma webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== ARKADAŞ DAVET WEBHOOK'LARI =====

    /**
     * Arkadaş davet kabul edildiğinde Socket.IO'ya bildir
     */
    public function friendInviteAccepted($inviterId, $invitedId, $rewardCoins = 0)
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/friend-invite-accepted', [
                'inviterId' => $inviterId,
                'invitedId' => $invitedId,
                'rewardCoins' => $rewardCoins,
                'timestamp' => now()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Arkadaş davet kabul webhook hatası: ' . $e->getMessage());
            return false;
        }
    }

    // ===== GENEL WEBHOOK METODU =====

    /**
     * Genel webhook gönderme metodu
     */
    public function sendWebhook($endpoint, $data = [])
    {
        try {
            $response = Http::post($this->socketServerUrl . '/webhook/' . $endpoint, array_merge($data, [
                'timestamp' => now()
            ]));

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Webhook gönderme hatası (' . $endpoint . '): ' . $e->getMessage());
            return false;
        }
    }
}
