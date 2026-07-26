<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RewardRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
    }

    public function index()
    {
        $requests = RewardRequest::with(['user', 'approver'])
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

        $rewardRequest->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ödül talebi onaylandı.'
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
