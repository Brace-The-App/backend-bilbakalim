<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Diamond;
use App\Models\DiamondPackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DiamondController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/diamond/balance",
     *     summary="Elmas Bakiyesi",
     *     description="Kullanıcının elmas bakiyesini getirir.",
     *     tags={"Diamond"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Elmas bakiyesi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="balance", type="integer", example=100),
     *             @OA\Property(property="user_id", type="integer", example=1)
     *         )
     *     )
     * )
     */
    public function getBalance(): JsonResponse
    {
        $user = Auth::user();
        
        $diamond = Diamond::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );

        return response()->json([
            'success' => true,
            'balance' => $diamond->balance,
            'user_id' => $user->id
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/diamond/packages",
     *     summary="Elmas Paketleri",
     *     description="Satın alınabilecek elmas paketlerini listeler.",
     *     tags={"Diamond"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Elmas paketleri",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="packages", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="10 Elmas"),
     *                 @OA\Property(property="diamond_amount", type="integer", example=10),
     *                 @OA\Property(property="price", type="number", example=14.00),
     *                 @OA\Property(property="gross_price", type="number", example=10.00)
     *             ))
     *         )
     *     )
     * )
     */
    public function getPackages(): JsonResponse
    {
        $packages = DiamondPackage::active()
            ->ordered()
            ->get()
            ->map(function($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'diamond_amount' => $package->diamond_amount,
                    'price' => (float) $package->price,
                    'gross_price' => (float) $package->gross_price,
                ];
            });

        return response()->json([
            'success' => true,
            'packages' => $packages
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/diamond/purchase",
     *     summary="Elmas Satın Al",
     *     description="Mobil ödeme yapıldıktan sonra elmas satın alma işlemini tamamlar.",
     *     tags={"Diamond"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="package_id", type="integer", example=1, description="Paket ID'si"),
     *                 @OA\Property(property="payment_id", type="string", example="payment_123", description="Mobil ödeme ID'si"),
     *                 @OA\Property(property="payment_provider", type="string", enum={"google", "apple"}, example="google", description="Ödeme sağlayıcısı")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="package_id", type="integer", example=1),
     *             @OA\Property(property="payment_id", type="string", example="payment_123"),
     *             @OA\Property(property="payment_provider", type="string", enum={"google", "apple"}, example="google")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Elmas başarıyla yüklendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Elmas başarıyla yüklendi."),
     *             @OA\Property(property="diamond_amount", type="integer", example=10),
     *             @OA\Property(property="new_balance", type="integer", example=110)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu paket aktif değil.")
     *         )
     *     )
     * )
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|exists:diamond_packages,id',
            'payment_id' => 'required|string',
            'payment_provider' => 'required|in:google,apple',
        ]);

        $user = Auth::user();
        $package = DiamondPackage::findOrFail($request->package_id);

        if (!$package->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bu paket aktif değil.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $diamond = Diamond::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            $diamond->add($package->diamond_amount);

            Log::info('Diamond purchase completed', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'diamond_amount' => $package->diamond_amount,
                'payment_id' => $request->payment_id,
                'payment_provider' => $request->payment_provider,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Elmas başarıyla yüklendi.',
                'diamond_amount' => $package->diamond_amount,
                'new_balance' => $diamond->balance
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Diamond purchase error', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Elmas yüklenirken bir hata oluştu.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/diamond/transfer-to-card",
     *     summary="Elması Karta Aktar",
     *     description="Elması BilBakalım kartına aktarır (1 elmas = 100 coin).",
     *     tags={"Diamond"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="amount", type="integer", example=100, description="Aktarılacak elmas miktarı", minimum=1)
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="integer", example=100, minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Elmas başarıyla karta aktarıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Elmas başarıyla karta aktarıldı."),
     *             @OA\Property(property="diamond_amount", type="integer", example=100),
     *             @OA\Property(property="coin_amount", type="integer", example=10000),
     *             @OA\Property(property="new_diamond_balance", type="integer", example=0),
     *             @OA\Property(property="new_coin_balance", type="integer", example=10000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Hata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Yetersiz elmas bakiyesi.")
     *         )
     *     )
     * )
     */
    public function transferToCard(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $amount = $request->amount;

        $diamond = Diamond::where('user_id', $user->id)->first();

        if (!$diamond || $diamond->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Yetersiz elmas bakiyesi.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $diamond->subtract($amount);

            $coinAmount = $amount * 100; // 1 TL = 100 coin
            $user->increment('coins', $coinAmount);
            $user->increment('total_coins', $coinAmount);

            Log::info('Diamond transferred to card', [
                'user_id' => $user->id,
                'diamond_amount' => $amount,
                'coin_amount' => $coinAmount,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Elmas başarıyla karta aktarıldı.',
                'diamond_amount' => $amount,
                'coin_amount' => $coinAmount,
                'new_diamond_balance' => $diamond->balance,
                'new_coin_balance' => $user->coins
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Diamond transfer error', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Elmas aktarılırken bir hata oluştu.'
            ], 500);
        }
    }
}
