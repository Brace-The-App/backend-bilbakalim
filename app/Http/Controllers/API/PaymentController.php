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
     *     summary="Ödeme başlat",
     *     description="Jeton satın alma için ödeme işlemini başlatır",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="coin_package_id", type="integer", description="Jeton paketi ID"),
     *                 @OA\Property(property="payment_method", type="string", enum={"credit_card","paypal","apple_pay","google_pay"}, description="Ödeme yöntemi"),
     *                 @OA\Property(property="payment_provider", type="string", enum={"stripe","paypal","iyzico","paytr"}, description="Ödeme sağlayıcısı")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ödeme başlatıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ödeme başlatıldı"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment", type="object"),
     *                 @OA\Property(property="coin_purchase", type="object"),
     *                 @OA\Property(property="payment_url", type="string", example="https://checkout.stripe.com/pay/..."),
     *                 @OA\Property(property="amount", type="string", example="39.99 TRY"),
     *                 @OA\Property(property="total_coins", type="integer", example=600)
     *             )
     *         )
     *     )
     * )
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'coin_package_id' => 'required|exists:coin_packages,id',
            'payment_method' => 'required|in:credit_card,paypal,apple_pay,google_pay',
            'payment_provider' => 'required|in:stripe,paypal,iyzico,paytr'
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

            // Ödeme kaydı oluştur
            $payment = Payment::create([
                'user_id' => Auth::id(),
                'payment_id' => Str::uuid(),
                'payment_method' => $request->payment_method,
                'payment_provider' => $request->payment_provider,
                'amount' => $coinPackage->price,
                'currency' => $coinPackage->currency,
                'status' => 'pending',
                'metadata' => [
                    'coin_package_id' => $coinPackage->id,
                    'coin_amount' => $coinPackage->coin_amount,
                    'bonus_coins' => $coinPackage->bonus_coins,
                    'total_coins' => $coinPackage->total_coins
                ]
            ]);

            // Jeton satın alma kaydı oluştur
            $coinPurchase = CoinPurchase::create([
                'user_id' => Auth::id(),
                'coin_package_id' => $coinPackage->id,
                'payment_id' => $payment->id,
                'coin_amount' => $coinPackage->coin_amount,
                'bonus_coins' => $coinPackage->bonus_coins,
                'price' => $coinPackage->price,
                'currency' => $coinPackage->currency,
                'status' => 'pending'
            ]);

            DB::commit();

            // Ödeme sağlayıcısına yönlendirme URL'si oluştur
            $paymentUrl = $this->createPaymentUrl($payment, $request->payment_provider);

            return response()->json([
                'success' => true,
                'message' => 'Ödeme başlatıldı.',
                'data' => [
                    'payment' => $payment,
                    'coin_purchase' => $coinPurchase,
                    'payment_url' => $paymentUrl,
                    'amount' => $coinPackage->formatted_price,
                    'total_coins' => $coinPackage->total_coins
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ödeme başlatılırken hata oluştu.',
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
     * Ödeme webhook'u
     */
    public function paymentWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required',
            'status' => 'required|in:pending,completed,failed,refunded',
            'transaction_id' => 'nullable|string',
            'payment_data' => 'nullable|array'
        ]);

        $payment = Payment::where('payment_id', $request->payment_id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Ödeme bulunamadı.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $payment->status;
            $payment->update([
                'status' => $request->status,
                'transaction_id' => $request->transaction_id,
                'payment_data' => $request->payment_data,
                'paid_at' => $request->status === 'completed' ? now() : null,
                'refunded_at' => $request->status === 'refunded' ? now() : null
            ]);

            if ($request->status === 'completed' && $oldStatus !== 'completed') {
                $this->completePayment($payment);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ödeme durumu güncellendi.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ödeme durumu güncellenirken hata oluştu.',
                'error' => $e->getMessage()
            ], 500);
        }
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
     * Ödemeyi tamamla
     */
    private function completePayment(Payment $payment): void
    {
        $coinPurchase = $payment->coinPurchases()->first();
        
        if ($coinPurchase) {
            // Jeton satın alma işlemini tamamla
            $coinPurchase->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Kullanıcının coin'ini güncelle
            $user = $payment->user;
            $user->increment('total_coins', $coinPurchase->total_coins);

            // Coin geçmişi kaydet
            $user->coinHistory()->create([
                'coin_amount' => $coinPurchase->total_coins,
                'transaction_type' => 'purchase',
                'status' => 'completed',
                'description' => 'Jeton satın alma',
                'metadata' => [
                    'coin_package_id' => $coinPurchase->coin_package_id,
                    'payment_id' => $payment->id,
                    'coin_amount' => $coinPurchase->coin_amount,
                    'bonus_coins' => $coinPurchase->bonus_coins
                ],
                'balance_before' => $user->total_coins - $coinPurchase->total_coins,
                'balance_after' => $user->total_coins
            ]);
        }
    }
}