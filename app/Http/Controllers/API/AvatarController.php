<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use Illuminate\Http\JsonResponse;

class AvatarController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/avatars",
     *     summary="Aktif avatarları listele",
     *     tags={"Avatars"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/storage/avatars/avatar1.jpg"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="sort_order", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $avatars = Avatar::active()
            ->ordered()
            ->get()
            ->map(function ($avatar) {
                return [
                    'id' => $avatar->id,
                    'image_url' => $avatar->image_url,
                    'is_active' => $avatar->is_active,
                    'sort_order' => $avatar->sort_order
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $avatars
        ]);
    }
}
