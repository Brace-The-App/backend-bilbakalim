<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardRequest;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RewardRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
    }

    public function index()
    {
        // surname kolonu henüz DB'de yok — select'e alma
        $requests = RewardRequest::with(['user:id,name,email,phone,coins,duel_earned_coins', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.reward-requests.index', compact('requests'));
    }

    public function approve(Request $request, RewardRequest $rewardRequest)
    {
        if ($rewardRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Bu ödül talebi zaten işleme alınmış.'
            ], 400);
        }

        $validated = $request->validate([
            'payout_method' => 'required|in:multinet,papara,havale,parsela,other',
            'payout_amount' => 'nullable|numeric|min:0|max:999999',
        ], [
            'payout_method.required' => 'Ödeme yöntemi seçin.',
        ]);

        $remainingDuelCoins = 0;

        DB::transaction(function () use ($rewardRequest, &$remainingDuelCoins, $validated) {
            $rewardRequest->refresh();
            if ($rewardRequest->status !== 'pending') {
                return;
            }

            /** @var User|null $user */
            $user = User::query()
                ->where('id', $rewardRequest->user_id)
                ->lockForUpdate()
                ->first();

            $meta = is_array($rewardRequest->metadata) ? $rewardRequest->metadata : [];
            $claimAmount = FinanceService::giftClaimMinCoins();

            // Eski talepler: claim anında düşüm yoktu — onayda bir kez eşik kadar düş
            if (
                $rewardRequest->reward_type === 'duel'
                && $user
                && !isset($meta['claimed_amount'])
                && !isset($meta['duel_earned_at_claim_after'])
            ) {
                $before = (int) ($user->duel_earned_coins ?? 0);
                $after = max(0, $before - $claimAmount);
                $user->duel_earned_coins = $after;
                $user->save();

                $meta['claimed_amount'] = $claimAmount;
                $meta['legacy_deducted_on_approve'] = true;
                $meta['duel_earned_before_legacy_deduct'] = $before;
                $meta['duel_earned_at_claim_after'] = $after;
                $rewardRequest->coins_earned = $claimAmount;
            }

            // Talep anında claimed_amount kadar duel_earned düşülmüş olmalı.
            // Onayda ekstra sıfırlama yok — kalan bakiye durur.
            $remainingDuelCoins = (int) ($user?->duel_earned_coins ?? 0);
            $meta['duel_earned_coins_at_approve'] = $remainingDuelCoins;
            $meta['wallet_coins_at_approve'] = (int) ($user?->coins ?? 0);
            $meta['approved_note'] = 'gift_delivered_without_duel_reset';
            $meta['payout_method'] = $validated['payout_method'];

            $rewardRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'coins_earned' => $rewardRequest->coins_earned,
                'metadata' => $meta,
            ]);

            $amountOverride = isset($validated['payout_amount'])
                ? (float) $validated['payout_amount']
                : null;

            $ledger = FinanceService::recordGiftPayout(
                $rewardRequest->fresh(),
                $validated['payout_method'],
                $amountOverride,
                Auth::id()
            );

            $meta['finance_ledger_id'] = $ledger->id;
            $meta['finance_payout_try'] = (float) $ledger->amount_try;
            $rewardRequest->update(['metadata' => $meta]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi onaylandı. Hediye çeki verildi.',
            'remaining_duel_coins' => $remainingDuelCoins,
        ]);
    }

    public function reject(Request $request, RewardRequest $rewardRequest)
    {
        if ($rewardRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Bu ödül talebi zaten işleme alınmış.'
            ], 400);
        }

        $didRefund = false;

        try {
            DB::transaction(function () use ($rewardRequest, &$didRefund) {
                $rewardRequest->refresh();
                if ($rewardRequest->status !== 'pending') {
                    throw new \RuntimeException('Bu ödül talebi zaten işleme alınmış.');
                }

                $meta = is_array($rewardRequest->metadata) ? $rewardRequest->metadata : [];

                // Sadece talep anında gerçekten düşülmüşse iade et
                if (
                    $rewardRequest->reward_type === 'duel'
                    && $rewardRequest->user_id
                    && (isset($meta['claimed_amount']) || isset($meta['duel_earned_at_claim_after']))
                ) {
                    $refund = (int) ($meta['claimed_amount'] ?? FinanceService::giftClaimMinCoins());
                    if ($refund > 0) {
                        /** @var User|null $user */
                        $user = User::query()
                            ->where('id', $rewardRequest->user_id)
                            ->lockForUpdate()
                            ->first();

                        if ($user) {
                            $before = (int) ($user->duel_earned_coins ?? 0);
                            $user->duel_earned_coins = $before + $refund;
                            $user->save();
                            $didRefund = true;

                            $meta['refunded_amount'] = $refund;
                            $meta['duel_earned_before_refund'] = $before;
                            $meta['duel_earned_after_refund'] = (int) $user->duel_earned_coins;
                            $meta['refunded_at'] = now()->toDateTimeString();
                        }
                    }
                }

                $rewardRequest->update([
                    'status' => 'rejected',
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                    'metadata' => $meta,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi reddedildi' . ($didRefund ? ' (jetonlar iade edildi).' : '.'),
        ]);
    }

    public function destroy(RewardRequest $rewardRequest)
    {
        $rewardRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi silindi.'
        ]);
    }
}
