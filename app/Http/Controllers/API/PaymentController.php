<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Diamond;
use App\Models\DiamondPackage;
use App\Models\JokerPackage;
use App\Models\Payment;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\PremiumPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Ödeme işlemleri"
 * )
 */
class PaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/payments/initiate",
     *     summary="Ödeme işle",
     *     description="RevenueCat onayı sonrası çağrılır. type değerine göre coin/elmas/premium/joker kullanıcı hesabına tanımlanır.",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="type", type="string", enum={"coin","diamond","premium","joker"}, description="Satın alma tipi", example="coin"),
     *                 @OA\Property(property="package_id", type="integer", description="Paket ID", example=1),
     *                 @OA\Property(property="status", type="string", enum={"completed","failed"}, description="Ödeme durumu", example="completed"),
     *                 @OA\Property(property="transaction_id", type="string", description="İşlem ID", example="txn_123456"),
     *                 @OA\Property(property="payment_data", type="object", description="Ödeme verileri (opsiyonel)")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"coin","diamond","premium","joker"}, example="coin"),
     *             @OA\Property(property="package_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"completed","failed"}, example="completed"),
     *             @OA\Property(property="transaction_id", type="string", example="txn_123456"),
     *             @OA\Property(property="payment_data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ödeme işlendi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ödeme başarıyla tamamlandı."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment", type="object"),
     *                 @OA\Property(property="grant_type", type="string", example="coin"),
     *                 @OA\Property(property="amount", type="string", example="39.99 TRY"),
     *                 @OA\Property(property="user_coins", type="integer", example=1600),
     *                 @OA\Property(property="user_diamond_balance", type="integer", example=50),
     *                 @OA\Property(property="is_premium", type="boolean", example=true),
     *                 @OA\Property(property="premium_expires_at", type="string", nullable=true, example="2026-04-08T12:00:00Z")
     *             )
     *         )
     *     )
     * )
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        // payment_data JSON string ise array'e çevir
        if ($request->has('payment_data') && is_string($request->payment_data)) {
            $decoded = json_decode($request->payment_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['payment_data' => $decoded]);
            } else {
                $request->merge(['payment_data' => null]);
            }
        }

        $request->validate([
            'type' => 'required|in:coin,diamond,premium,joker',
            'package_id' => 'required|integer',
            'transaction_id' => 'nullable|string',
            'payment_data' => 'nullable|array',
            'status' => 'required|in:completed,failed',
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $type = $request->type;
            $packageId = (int) $request->package_id;
            $selectedPackage = null;
            $amount = 0;
            $currency = 'TRY';

            switch ($type) {
                case 'coin':
                    $selectedPackage = CoinPackage::where('id', $packageId)->where('is_active', true)->first();
                    break;
                case 'diamond':
                    $selectedPackage = DiamondPackage::where('id', $packageId)->where('is_active', true)->first();
                    break;
                case 'premium':
                    $selectedPackage = PremiumPackage::where('id', $packageId)->where('is_active', true)->first();
                    break;
                case 'joker':
                    $selectedPackage = JokerPackage::where('id', $packageId)->where('is_active', true)->first();
                    break;
            }

            if (!$selectedPackage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket bulunamadı veya aktif değil.',
                ], 404);
            }

            $amount = (float) ($selectedPackage->price ?? 0);
            $currency = $selectedPackage->currency ?? 'TRY';

            // Ödeme kaydı oluştur
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_id' => Str::uuid(),
                // RevenueCat ile bu alanlar istemciden alınmıyor
                'payment_method' => 'mobile_store',
                'payment_provider' => 'revenuecat',
                'amount' => $amount,
                'currency' => $currency,
                'status' => $request->status,
                'transaction_id' => $request->transaction_id,
                'payment_data' => $request->payment_data,
                'paid_at' => $request->status === 'completed' ? now() : null,
                'metadata' => [
                    'type' => $type,
                    'package_id' => $selectedPackage->id,
                    'package_snapshot' => $selectedPackage->toArray(),
                ],
            ]);

            $coinPurchase = null;

            if ($request->status === 'completed') {
                if ($type === 'coin') {
                    /** @var CoinPackage $selectedPackage */
                    $coinPurchase = CoinPurchase::create([
                        'user_id' => $user->id,
                        'coin_package_id' => $selectedPackage->id,
                        'payment_id' => (string) $payment->id,
                        'coin_amount' => $selectedPackage->coin_amount,
                        'bonus_coins' => $selectedPackage->bonus_coins,
                        'price' => $selectedPackage->price,
                        'currency' => $selectedPackage->currency,
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    $balanceBefore = (int) $user->coins;
                    $user->increment('coins', $selectedPackage->coin_amount);
                    $balanceAfter = (int) $user->fresh()->coins;

                    $user->coinHistory()->create([
                        'coin_amount' => $selectedPackage->coin_amount,
                        'transaction_type' => 'purchase',
                        'status' => 'completed',
                        'description' => 'Jeton satın alma',
                        'metadata' => [
                            'type' => 'coin',
                            'coin_package_id' => $selectedPackage->id,
                            'coin_purchase_id' => $coinPurchase->id,
                            'payment_id' => $payment->id,
                            'coin_amount' => $selectedPackage->coin_amount,
                            'bonus_coins' => $selectedPackage->bonus_coins,
                            'price' => $selectedPackage->price,
                            'currency' => $selectedPackage->currency,
                        ],
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                    ]);
                } elseif ($type === 'diamond') {
                    /** @var DiamondPackage $selectedPackage */
                    $diamond = Diamond::firstOrCreate(
                        ['user_id' => $user->id],
                        ['balance' => 0]
                    );
                    $diamond->add((int) $selectedPackage->diamond_amount);
                } elseif ($type === 'premium') {
                    /** @var PremiumPackage $selectedPackage */
                    $premiumExpiresAt = $user->premium_expires_at
                        ? Carbon::parse($user->premium_expires_at)
                        : null;

                    $baseTime = $premiumExpiresAt && $premiumExpiresAt->isFuture()
                        ? $premiumExpiresAt
                        : now();
                    $newExpireAt = $baseTime->copy()->addDays((int) $selectedPackage->duration_days);

                    $user->forceFill([
                        'is_premium' => true,
                        'premium_expires_at' => $newExpireAt,
                    ])->save();

                    if ((int) $selectedPackage->gift_coins > 0) {
                        $user->increment('coins', (int) $selectedPackage->gift_coins);
                    }

                    $jokerUpdates = [];
                    if ((int) $selectedPackage->fifty_fifty_jokers > 0) {
                        $jokerUpdates['fifty_fifty_jokers'] = DB::raw('COALESCE(fifty_fifty_jokers, 0) + ' . (int) $selectedPackage->fifty_fifty_jokers);
                    }
                    if ((int) $selectedPackage->double_answer_jokers > 0) {
                        $jokerUpdates['double_answer_jokers'] = DB::raw('COALESCE(double_answer_jokers, 0) + ' . (int) $selectedPackage->double_answer_jokers);
                    }
                    if ((int) $selectedPackage->hint_jokers > 0) {
                        $jokerUpdates['hint_jokers'] = DB::raw('COALESCE(hint_jokers, 0) + ' . (int) $selectedPackage->hint_jokers);
                    }
                    if (!empty($jokerUpdates)) {
                        $user->newQuery()->where('id', $user->id)->update($jokerUpdates);
                    }
                } elseif ($type === 'joker') {
                    /** @var JokerPackage $selectedPackage */
                    if ((int) $selectedPackage->coin_amount > 0) {
                        $user->increment('coins', (int) $selectedPackage->coin_amount);
                    }
                    $jokerUpdates = [
                        'fifty_fifty_jokers' => DB::raw('COALESCE(fifty_fifty_jokers, 0) + ' . (int) $selectedPackage->fifty_fifty_jokers),
                        'double_answer_jokers' => DB::raw('COALESCE(double_answer_jokers, 0) + ' . (int) $selectedPackage->double_answer_jokers),
                        'hint_jokers' => DB::raw('COALESCE(hint_jokers, 0) + ' . (int) $selectedPackage->hint_jokers),
                    ];
                    $user->newQuery()->where('id', $user->id)->update($jokerUpdates);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->status === 'completed' ? 'Ödeme başarıyla tamamlandı.' : 'Ödeme başarısız oldu.',
                'data' => [
                    'payment' => $payment,
                    'coin_purchase' => $coinPurchase,
                    'grant_type' => $type,
                    'amount' => number_format((float) $amount, 2) . ' ' . $currency,
                    'user_coins' => (int) $user->fresh()->coins,
                    'user_diamond_balance' => (int) (Diamond::where('user_id', $user->id)->value('balance') ?? 0),
                    'is_premium' => (bool) $user->fresh()->is_premium,
                    'premium_expires_at' => optional($user->fresh()->premium_expires_at)->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ödeme işlenirken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ödeme durumunu kontrol et
     */
    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,payment_id'
        ]);

        $payment = Payment::where('payment_id', $request->payment_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Ödeme bulunamadı.'
            ], 404);
        }

        // Ödeme sağlayıcısından durumu kontrol et
        $status = $this->checkPaymentProviderStatus($payment);

        if ($status !== $payment->status) {
            $payment->update(['status' => $status]);
            
            if ($status === 'completed') {
                $this->completePayment($payment);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $payment,
                'status' => $payment->status
            ]
        ]);
    }


    /**
     * Ödeme geçmişi
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');

        $query = Payment::where('user_id', Auth::id())
            ->with(['coinPurchases.coinPackage']);

        if ($status) {
            $query->where('status', $status);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Ödeme iptal et
     */
    public function cancelPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,payment_id'
        ]);

        $payment = Payment::where('payment_id', $request->payment_id)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Ödeme bulunamadı veya iptal edilemez.'
            ], 404);
        }

        $payment->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ödeme iptal edildi.',
            'data' => $payment
        ]);
    }

    /**
     * Ödeme sağlayıcısı URL'si oluştur
     */
    private function createPaymentUrl(Payment $payment, string $provider): string
    {
        // Bu kısım ödeme sağlayıcısına göre implement edilecek
        switch ($provider) {
            case 'stripe':
                return "https://checkout.stripe.com/pay/{$payment->payment_id}";
            case 'paypal':
                return "https://www.paypal.com/checkoutnow?token={$payment->payment_id}";
            case 'iyzico':
                return "https://sandbox-payment.iyzipay.com/pay/{$payment->payment_id}";
            case 'paytr':
                return "https://www.paytr.com/odeme/{$payment->payment_id}";
            default:
                return '';
        }
    }

    /**
     * Ödeme sağlayıcısından durumu kontrol et
     */
    private function checkPaymentProviderStatus(Payment $payment): string
    {
        // Bu kısım ödeme sağlayıcısına göre implement edilecek
        // Şimdilik pending döndürüyoruz
        return 'pending';
    }

    /**
     * Ödemeyi tamamla (checkPaymentStatus için)
     */
    private function completePayment(Payment $payment): void
    {
        $coinPurchase = $payment->coinPurchases()->first();
        
        if ($coinPurchase) {
            $this->processCompletedPayment($payment, $coinPurchase);
        }
    }


    /**
     * Tamamlanan ödemeyi işle (eski metod - geriye dönük uyumluluk için)
     */
    private function processCompletedPayment(Payment $payment, CoinPurchase $coinPurchase): void
    {
        $user = $payment->user;
        $coinPackage = $coinPurchase->coinPackage;
        
        // Kullanıcının mevcut coin bakiyesini al
        $balanceBefore = $user->coins;
        
        // Kullanıcının coins alanına coin_package'daki coin_amount'ı ekle
        $user->increment('coins', $coinPackage->coin_amount);
        
        // Güncel bakiyeyi al
        $balanceAfter = $user->coins;
        
        // Coin alım geçmişini coin_history tablosuna kaydet
        $user->coinHistory()->create([
            'coin_amount' => $coinPackage->coin_amount,
            'transaction_type' => 'purchase',
            'status' => 'completed',
            'description' => 'Jeton satın alma',
            'metadata' => [
                'coin_package_id' => $coinPackage->id,
                'coin_purchase_id' => $coinPurchase->id,
                'payment_id' => $payment->id,
                'coin_amount' => $coinPackage->coin_amount,
                'bonus_coins' => $coinPurchase->bonus_coins,
                'price' => $coinPurchase->price,
                'currency' => $coinPurchase->currency
            ],
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ]);
    }

}