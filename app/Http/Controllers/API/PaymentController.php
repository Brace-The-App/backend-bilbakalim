<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Payment;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     *     description="Mobil ödeme tamamlandıktan sonra çağrılır. Ödeme detayını coin_purchases'e kaydeder, coin_history'ye ekler ve kullanıcının coins alanına ekler.",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="coin_package_id", type="integer", description="Jeton paketi ID", example=1),
     *                 @OA\Property(property="payment_method", type="string", enum={"credit_card","paypal","apple_pay","google_pay"}, description="Ödeme yöntemi", example="credit_card"),
     *                 @OA\Property(property="payment_provider", type="string", enum={"stripe","paypal","iyzico","paytr"}, description="Ödeme sağlayıcısı", example="stripe"),
     *                 @OA\Property(property="status", type="string", enum={"completed","failed"}, description="Ödeme durumu", example="completed"),
     *                 @OA\Property(property="transaction_id", type="string", description="İşlem ID", example="txn_123456"),
     *                 @OA\Property(property="payment_data", type="object", description="Ödeme verileri (opsiyonel)")
     *             )
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
     *                 @OA\Property(property="coin_purchase", type="object"),
     *                 @OA\Property(property="amount", type="string", example="39.99 TRY"),
     *                 @OA\Property(property="coin_amount", type="integer", example=500),
     *                 @OA\Property(property="bonus_coins", type="integer", example=100),
     *                 @OA\Property(property="total_coins", type="integer", example=600),
     *                 @OA\Property(property="user_coins", type="integer", example=1600)
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
            'coin_package_id' => 'required|exists:coin_packages,id',
            'payment_method' => 'required|in:credit_card,paypal,apple_pay,google_pay',
            'payment_provider' => 'required|in:stripe,paypal,iyzico,paytr',
            'transaction_id' => 'nullable|string',
            'payment_data' => 'nullable|array',
            'status' => 'required|in:completed,failed'
        ]);

        try {
            $coinPackage = CoinPackage::where('id', $request->coin_package_id)
                ->where('is_active', true)
                ->first();

            if (!$coinPackage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jeton paketi bulunamadı veya aktif değil.'
                ], 404);
            }

            DB::beginTransaction();

            $user = Auth::user();

            // Ödeme kaydı oluştur
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_id' => Str::uuid(),
                'payment_method' => $request->payment_method,
                'payment_provider' => $request->payment_provider,
                'amount' => $coinPackage->price,
                'currency' => $coinPackage->currency,
                'status' => $request->status,
                'transaction_id' => $request->transaction_id,
                'payment_data' => $request->payment_data,
                'paid_at' => $request->status === 'completed' ? now() : null,
                'metadata' => [
                    'coin_package_id' => $coinPackage->id,
                    'coin_amount' => $coinPackage->coin_amount,
                    'bonus_coins' => $coinPackage->bonus_coins,
                    'total_coins' => $coinPackage->total_coins
                ]
            ]);

            // Coin purchase kaydını oluştur
            $coinPurchase = CoinPurchase::create([
                'user_id' => $user->id,
                'coin_package_id' => $coinPackage->id,
                'payment_id' => $payment->id,
                'coin_amount' => $coinPackage->coin_amount,
                'bonus_coins' => $coinPackage->bonus_coins,
                'price' => $coinPackage->price,
                'currency' => $coinPackage->currency,
                'status' => $request->status === 'completed' ? 'completed' : 'failed',
                'completed_at' => $request->status === 'completed' ? now() : null
            ]);

            // Ödeme başarılıysa coin işlemlerini yap
            if ($request->status === 'completed') {
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
                        'bonus_coins' => $coinPackage->bonus_coins,
                        'price' => $coinPackage->price,
                        'currency' => $coinPackage->currency
                    ],
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $request->status === 'completed' ? 'Ödeme başarıyla tamamlandı.' : 'Ödeme başarısız oldu.',
                'data' => [
                    'payment' => $payment,
                    'coin_purchase' => $coinPurchase,
                    'amount' => $coinPackage->formatted_price,
                    'coin_amount' => $coinPackage->coin_amount,
                    'bonus_coins' => $coinPackage->bonus_coins,
                    'total_coins' => $coinPackage->total_coins,
                    'user_coins' => $user->fresh()->coins
                ]
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