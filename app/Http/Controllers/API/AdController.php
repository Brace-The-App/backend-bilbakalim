<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\UserAdView;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{
    private const MAX_VIEWS = 3;
    private const WINDOW_HOURS = 24;

    /**
     * @OA\Get(
     *     path="/api/ads/next",
     *     summary="Rastgele reklam görseli al",
     *     description="Kullanıcıya rastgele aktif bir reklam görseli döner. İlk izlemeden itibaren 24 saat içinde en fazla 3 reklam; hak dolunca 24 saat sonunda yenilenir.",
     *     tags={"Ads"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="allowed", type="boolean", example=true),
     *             @OA\Property(property="count", type="integer", example=1),
     *             @OA\Property(property="max", type="integer", example=3),
     *             @OA\Property(property="resets_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="message", type="string", example="Reklam görseli hazır."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="Reklam Kırmızı"),
     *                 @OA\Property(property="image_url", type="string", example="https://bil-bakalim.com/storage/ads/dummy-red.png")
     *             )
     *         )
     *     )
     * )
     */
    public function next(): JsonResponse
    {
        $user = Auth::user();
        $max = self::MAX_VIEWS;

        return DB::transaction(function () use ($user, $max) {
            UserAdView::firstOrCreate(
                ['user_id' => $user->id],
                ['view_count' => 0]
            );

            $view = UserAdView::where('user_id', $user->id)->lockForUpdate()->first();
            $this->resetWindowIfExpired($view);

            if ($view->view_count >= $max) {
                $resetsAt = $this->windowResetsAt($view);

                return response()->json([
                    'success' => true,
                    'allowed' => false,
                    'count' => (int) $view->view_count,
                    'max' => $max,
                    'resets_at' => $resetsAt?->toIso8601String(),
                    'message' => 'Reklam hakkınız doldu. İlk izlemeden 24 saat sonra yenilenir.',
                    'data' => null,
                ]);
            }

            $ad = Ad::active()->inRandomOrder()->first();

            if (!$ad) {
                return response()->json([
                    'success' => false,
                    'allowed' => false,
                    'count' => (int) $view->view_count,
                    'max' => $max,
                    'resets_at' => $this->windowResetsAt($view)?->toIso8601String(),
                    'message' => 'Aktif reklam bulunamadı.',
                    'data' => null,
                ], 404);
            }

            if ((int) $view->view_count === 0 || !$view->window_started_at) {
                $view->window_started_at = now();
            }

            $view->view_count = (int) $view->view_count + 1;
            $view->last_viewed_at = now();
            $view->save();

            return response()->json([
                'success' => true,
                'allowed' => true,
                'count' => (int) $view->view_count,
                'max' => $max,
                'resets_at' => $this->windowResetsAt($view)?->toIso8601String(),
                'message' => 'Reklam görseli hazır.',
                'data' => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'image_url' => $ad->image_url,
                ],
            ]);
        });
    }

    /**
     * Pencere: ilk reklamdan itibaren 24 saat. Dolunca sayaç sıfırlanır.
     */
    private function resetWindowIfExpired(UserAdView $view): void
    {
        $windowStart = $view->window_started_at;

        // Eski kayıtlar: window_started_at yoksa created_at ile tahmin et
        if (!$windowStart && (int) $view->view_count > 0) {
            $windowStart = $view->created_at;
            $view->window_started_at = $windowStart;
            $view->save();
        }

        if ($windowStart && $windowStart->lte(now()->subHours(self::WINDOW_HOURS))) {
            $view->view_count = 0;
            $view->window_started_at = null;
            $view->save();
        }
    }

    private function windowResetsAt(UserAdView $view): ?Carbon
    {
        if (!$view->window_started_at) {
            return null;
        }

        return $view->window_started_at->copy()->addHours(self::WINDOW_HOURS);
    }
}
