<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\UserAdView;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/ads/next",
     *     summary="Rastgele reklam görseli al",
     *     description="Hak varsa rastgele aktif reklam döner. Hak düşmez; hak /api/ad-watch/reward ile düşer. 24 saatte max 3 izleme.",
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
     *             @OA\Property(property="remaining", type="integer", example=2),
     *             @OA\Property(property="resets_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="message", type="string", example="Reklam görseli hazır."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="Yuden Games"),
     *                 @OA\Property(property="image_url", type="string", example="https://bil-bakalim.com/storage/ads/yuden-games.png"),
     *                 @OA\Property(property="link", type="string", nullable=true, example="https://yudengames.com/"),
     *                 @OA\Property(property="video_url", type="string", nullable=true, example="https://bil-bakalim.com/storage/ads/videos/promo.mp4")
     *             )
     *         )
     *     )
     * )
     */
    public function next(): JsonResponse
    {
        $user = Auth::user();

        return DB::transaction(function () use ($user) {
            $view = UserAdView::forUser($user->id);
            $view = UserAdView::where('user_id', $user->id)->lockForUpdate()->first();
            $view->resetWindowIfExpired();

            $status = $view->statusPayload();

            if ($view->isExhausted()) {
                return response()->json(array_merge([
                    'success' => true,
                    'allowed' => false,
                    'message' => 'Reklam hakkınız doldu. İlk izlemeden 24 saat sonra yenilenir.',
                    'data' => null,
                ], $status));
            }

            $ad = Ad::active()->inRandomOrder()->first();

            if (!$ad) {
                return response()->json(array_merge([
                    'success' => false,
                    'allowed' => false,
                    'message' => 'Aktif reklam bulunamadı.',
                    'data' => null,
                ], $status), 404);
            }

            // Hak burada düşmez; izleme tamamlanıp reward alındığında düşer.
            return response()->json(array_merge([
                'success' => true,
                'allowed' => true,
                'message' => 'Reklam görseli hazır.',
                'data' => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'image_url' => $ad->image_url,
                    'link' => $ad->link,
                    'video_url' => $ad->video_url,
                ],
            ], $status));
        });
    }
}
