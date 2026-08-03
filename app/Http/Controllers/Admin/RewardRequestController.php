<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardRequest;
use App\Models\User;
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

        $remainingDuelCoins = 0;

        DB::transaction(function () use ($rewardRequest, &$remainingDuelCoins) {
            $rewardRequest->refresh();
            if ($rewardRequest->status !== 'pending') {
                return;
            }

            /** @var User|null $user */
            $user = User::query()
                ->where('id', $rewardRequest->user_id)
                ->lockForUpdate()
                ->first();

            $before = (int) ($user?->duel_earned_coins ?? 0);

            if ($user) {
                $user->duel_earned_coins = 0;
                $user->save();
            }

            $remainingDuelCoins = 0;
            $meta = is_array($rewardRequest->metadata) ? $rewardRequest->metadata : [];
            $meta['duel_earned_coins_before'] = $before;
            $meta['duel_earned_coins_after'] = 0;
            $meta['wallet_coins_at_approve'] = (int) ($user?->coins ?? 0);

            $rewardRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'metadata' => $meta,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi onaylandı. Kullanıcının düello jeton hakkı sıfırlandı.',
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

        $rewardRequest->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi reddedildi.'
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
