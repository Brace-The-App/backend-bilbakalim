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
    private const MAX_VIEWS = 3;

    /**
     * @OA\Get(
     *     path="/api/ads/next",
     *     summary="Rastgele reklam görseli al",
     *     description="Kullanıcıya rastgele aktif bir reklam görseli döner. Kullanıcı başına en fazla 3 kez izin verilir; 3'ten sonra allowed=false döner. Coin/gösterim client tarafında yönetilir.",
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
     *             @OA\Property(property="message", type="string", example="Reklam görseli hazır."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="Reklam Kırmızı"),
     *                 @OA\Property(property="image_url", type="string", example="https://bil-bakalim.com/storage/ads/dummy-red.png")
     *             ),
     *             example={
     *                 "success": true,
     *                 "allowed": true,
     *                 "count": 1,
     *                 "max": 3,
     *                 "message": "Reklam görseli hazır.",
     *                 "data": {
     *                     "id": 1,
     *                     "title": "Reklam Kırmızı",
     *                     "image_url": "https://bil-bakalim.com/storage/ads/dummy-red.png"
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function next(): JsonResponse
    {
        $user = Auth::user();
        $max = self::MAX_VIEWS;

        return DB::transaction(function () use ($user, $max) {
            $view = UserAdView::firstOrCreate(
                ['user_id' => $user->id],
                ['view_count' => 0]
            );

            // Row lock for concurrent requests
            $view = UserAdView::where('user_id', $user->id)->lockForUpdate()->first();

            if ($view->view_count >= $max) {
                return response()->json([
                    'success' => true,
                    'allowed' => false,
                    'count' => (int) $view->view_count,
                    'max' => $max,
                    'message' => "Reklam hakkınız doldu. En fazla {$max} reklam izleyebilirsiniz.",
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
                    'message' => 'Aktif reklam bulunamadı.',
                    'data' => null,
                ], 404);
            }

            $view->view_count = (int) $view->view_count + 1;
            $view->last_viewed_at = now();
            $view->save();

            return response()->json([
                'success' => true,
                'allowed' => true,
                'count' => (int) $view->view_count,
                'max' => $max,
                'message' => 'Reklam görseli hazır.',
                'data' => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'image_url' => $ad->image_url,
                ],
            ]);
        });
    }
}
