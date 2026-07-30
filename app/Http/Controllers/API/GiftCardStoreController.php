<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GiftCardStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardStoreController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/gift-card-stores",
     *     summary="Hediye kartı geçen mağaza ve market logolarını listele",
     *     description="Her marka için id + image_url döner. Ödül talebinde (POST /api/reward/claim) kullanıcının seçtiği markanın id değeri gift_card_store_id olarak gönderilmelidir.",
     *     tags={"Gift Card Stores"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Tip filtresi: market veya mağaza",
     *         required=false,
     *         @OA\Schema(type="string", enum={"market", "mağaza"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="duel_earned_coins", type="integer", example=120, description="Kullanıcının meydan okumadan kazandığı jeton"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1, description="Marka id — claim isteğinde gift_card_store_id olarak gönderilir"),
     *                     @OA\Property(property="type", type="string", example="market"),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/storage/gift-card-stores/store1.jpg"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="sort_order", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = GiftCardStore::active()->ordered();

        // Type filtresi
        if ($request->has('type') && in_array($request->type, ['market', 'mağaza'])) {
            $query->byType($request->type);
        }

        $stores = $query->get()->map(function ($store) {
            return [
                'id' => $store->id,
                'type' => $store->type,
                'image_url' => $store->image_url,
                'is_active' => $store->is_active,
                'sort_order' => $store->sort_order
            ];
        });

        $user = $request->user();

        return response()->json([
            'success' => true,
            'duel_earned_coins' => (int) ($user?->duel_earned_coins ?? 0),
            'data' => $stores
        ]);
    }
}
