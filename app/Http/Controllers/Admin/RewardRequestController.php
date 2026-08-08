<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceLedgerEntry;
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

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        // surname kolonu henüz DB'de yok — select'e alma
        $query = RewardRequest::with(['user:id,name,email,phone,coins,duel_earned_coins', 'approver:id,name']);

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where(function ($w) use ($q) {
                    $w->where('id', (int) $q)->orWhere('user_id', (int) $q);
                });
            } else {
                $query->whereHas('user', function ($w) use ($q) {
                    $w->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhere('phone', 'like', '%' . $q . '%');
                });
            }
        }

        // Her zaman en yeni talep önce (created_at — onay/red updated_at'i kaydırmaz)
        $requests = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $counts = [
            'all' => RewardRequest::query()->count(),
            'pending' => RewardRequest::query()->where('status', 'pending')->count(),
            'approved' => RewardRequest::query()->where('status', 'approved')->count(),
            'rejected' => RewardRequest::query()->where('status', 'rejected')->count(),
        ];

        return view('admin.reward-requests.index', compact(
            'requests',
            'status',
            'q',
            'counts'
        ));
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
            'payout_method' => 'required|in:multinet,papara,havale,other',
            'payout_amount' => 'required|numeric|min:0|max:999999',
        ], [
            'payout_method.required' => 'Ödeme yöntemi seçin.',
            'payout_amount.required' => 'Ödenen tutarı girin.',
            'payout_amount.numeric' => 'Ödenen tutar sayı olmalı.',
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

            $amountOverride = (float) $validated['payout_amount'];

            $ledger = FinanceService::recordGiftPayout(
                $rewardRequest->fresh(),
                $validated['payout_method'],
                $amountOverride,
                Auth::id()
            );

            $meta['finance_ledger_id'] = $ledger->id;
            $meta['finance_payout_try'] = (float) $ledger->amount_try;
            $meta['finance_payout_manual'] = true;
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
        $status = (string) $rewardRequest->status;
        $didRefund = false;
        $removedLedger = false;

        DB::transaction(function () use ($rewardRequest, $status, &$didRefund, &$removedLedger) {
            $rewardRequest->refresh();

            // Onaylı talep: jeton iade YOK (hediye verilmiş sayılır). Finans gider kaydını kaldır.
            if ($status === 'approved') {
                $removed = FinanceLedgerEntry::query()
                    ->where('reference_type', RewardRequest::class)
                    ->where('reference_id', $rewardRequest->id)
                    ->delete();
                $removedLedger = $removed > 0;

                // meta'daki ledger id ile yedek silme
                $meta = is_array($rewardRequest->metadata) ? $rewardRequest->metadata : [];
                if (!empty($meta['finance_ledger_id'])) {
                    $n = FinanceLedgerEntry::query()->where('id', (int) $meta['finance_ledger_id'])->delete();
                    $removedLedger = $removedLedger || $n > 0;
                }
            }

            // Bekleyen talep silinirse: claim'de düşülen düello jetonunu iade et
            if ($status === 'pending' && $rewardRequest->reward_type === 'duel' && $rewardRequest->user_id) {
                $meta = is_array($rewardRequest->metadata) ? $rewardRequest->metadata : [];
                if (isset($meta['claimed_amount']) || isset($meta['duel_earned_at_claim_after'])) {
                    $refund = (int) ($meta['claimed_amount'] ?? FinanceService::giftClaimMinCoins());
                    if ($refund > 0) {
                        $user = User::query()
                            ->where('id', $rewardRequest->user_id)
                            ->lockForUpdate()
                            ->first();
                        if ($user) {
                            $user->duel_earned_coins = (int) ($user->duel_earned_coins ?? 0) + $refund;
                            $user->save();
                            $didRefund = true;
                        }
                    }
                }
            }

            // Reddedilmiş: zaten iade edilmiş olmalı; ekstra iade yok
            $rewardRequest->delete();
        });

        $msg = 'Ödül talebi silindi.';
        if ($status === 'approved') {
            $msg = 'Onaylı talep silindi. Jeton iade edilmedi'
                . ($removedLedger ? '; finans gider kaydı kaldırıldı.' : '.');
        } elseif ($status === 'pending') {
            $msg = 'Bekleyen talep silindi'
                . ($didRefund ? ' (düello jetonları iade edildi).' : '.');
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'refunded' => $didRefund,
            'ledger_removed' => $removedLedger,
        ]);
    }
}
