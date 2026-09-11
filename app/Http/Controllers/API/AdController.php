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
     *     summary="Rastgele reklam al (görsel VEYA video)",
     *     description="Hak varsa rastgele aktif reklam döner. media_type=image ise yalnızca image_url; media_type=video ise video_url (max 10 sn) kullanılır — ikisi birden seçilmez. CTA: cta_text + link. Ödül: reward_coins — izleme bitince POST /api/ad-watch/reward (body: ad_id). Hak burada düşmez.",
     *     tags={"Ads"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Reklam hazır veya hak dolu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="allowed", type="boolean", example=true),
     *             @OA\Property(property="count", type="integer", example=1, description="Penceredeki kullanılan hak"),
     *             @OA\Property(property="max", type="integer", example=3),
     *             @OA\Property(property="remaining", type="integer", example=2),
     *             @OA\Property(property="resets_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="message", type="string", example="Reklam hazır."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="title", type="string", nullable=true, example="Test Video Reklam"),
     *                 @OA\Property(property="media_type", type="string", enum={"image","video"}, example="video"),
     *                 @OA\Property(property="has_video", type="boolean", example=true),
     *                 @OA\Property(property="image_url", type="string", nullable=true, example="https://bilbakalim.online/storage/ads/poster.jpg", description="Görsel reklamda dolu; video reklamda genelde null"),
     *                 @OA\Property(property="video_url", type="string", nullable=true, example="https://bilbakalim.online/storage/ads/videos/promo.mp4"),
     *                 @OA\Property(property="max_video_seconds", type="integer", example=10),
     *                 @OA\Property(property="link", type="string", nullable=true, example="https://yudengames.com/"),
     *                 @OA\Property(property="cta_text", type="string", example="Daha fazla bilgi al"),
     *                 @OA\Property(property="reward_coins", type="integer", example=5, description="İzleme tamamlanınca verilecek jeton")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Kimlik doğrulama gerekli",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aktif reklam yok",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="allowed", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Aktif reklam bulunamadı."),
     *             @OA\Property(property="data", nullable=true, example=null)
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

            $hasVideo = $ad->isVideoAd();
            $imageUrl = $ad->image_url;
            if ($hasVideo && ($ad->image_path === Ad::VIDEO_PLACEHOLDER_PATH || !$ad->image_path)) {
                $imageUrl = null;
            }

            // Hak burada düşmez; izleme tamamlanıp reward alındığında düşer.
            return response()->json(array_merge([
                'success' => true,
                'allowed' => true,
                'message' => 'Reklam hazır.',
                'data' => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'media_type' => $ad->mediaType(),
                    'has_video' => $hasVideo,
                    'image_url' => $hasVideo ? $imageUrl : $ad->image_url,
                    'video_url' => $hasVideo ? $ad->video_url : null,
                    'max_video_seconds' => Ad::MAX_VIDEO_SECONDS,
                    'link' => $ad->link,
                    'cta_text' => $ad->resolvedCtaText(),
                    'reward_coins' => $ad->resolvedRewardCoins(),
                ],
            ], $status));
        });
    }
}
