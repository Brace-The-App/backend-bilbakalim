<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
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
}
