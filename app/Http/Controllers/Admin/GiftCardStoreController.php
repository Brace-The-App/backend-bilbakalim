<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GiftCardStoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view gift card stores')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create gift card stores')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit gift card stores')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete gift card stores')->only(['destroy']);
    }

    public function index()
    {
        $stores = GiftCardStore::ordered()->paginate(20);
        return view('admin.gift-card-stores.index', compact('stores'));
    }

    public function create()
    {
        return redirect()->route('admin.gift-card-stores.index');
    }

    public function store(Request $request)
    {
        $rules = [
            'type' => 'required|in:market,mağaza',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ];

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('GiftCardStore validation failed:', $e->errors());
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->validator)->withInput();
        }

        // Görseli yükle
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('gift-card-stores', 'public');
            
            $store = new GiftCardStore();
            $store->type = $request->type;
            $store->image_path = $imagePath;
            $store->is_active = $request->has('is_active') && $request->is_active !== null;
            $store->sort_order = $request->integer('sort_order', 0);
            $store->save();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Hediye kartı mağazası başarıyla oluşturuldu.'
                ]);
            }

            return redirect()->route('admin.gift-card-stores.index')->with('success', 'Hediye kartı mağazası başarıyla oluşturuldu.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Görsel yüklenemedi.'
            ], 422);
        }

        return redirect()->back()->with('error', 'Görsel yüklenemedi.');
    }

    public function show(GiftCardStore $giftCardStore)
    {
        return redirect()->route('admin.gift-card-stores.index');
    }

    public function edit(GiftCardStore $giftCardStore)
    {
        return redirect()->route('admin.gift-card-stores.index');
    }

    public function update(Request $request, GiftCardStore $giftCardStore)
    {
        $rules = [
            'type' => 'required|in:market,mağaza',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ];

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('GiftCardStore validation failed:', $e->errors());
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->validator)->withInput();
        }

        // Eğer yeni görsel yüklendiyse, eski görseli sil
        if ($request->hasFile('image')) {
            // Eski görseli sil
            if ($giftCardStore->image_path && Storage::disk('public')->exists($giftCardStore->image_path)) {
                Storage::disk('public')->delete($giftCardStore->image_path);
            }
            
            // Yeni görseli yükle
            $imagePath = $request->file('image')->store('gift-card-stores', 'public');
            $giftCardStore->image_path = $imagePath;
        }

        $giftCardStore->type = $request->type;
        $giftCardStore->is_active = $request->has('is_active') && $request->is_active !== null;
        $giftCardStore->sort_order = $request->integer('sort_order', $giftCardStore->sort_order);
        $giftCardStore->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Hediye kartı mağazası başarıyla güncellendi.'
            ]);
        }

        return redirect()->route('admin.gift-card-stores.index')->with('success', 'Hediye kartı mağazası başarıyla güncellendi.');
    }

    public function destroy(GiftCardStore $giftCardStore)
    {
        // Görseli sil
        if ($giftCardStore->image_path && Storage::disk('public')->exists($giftCardStore->image_path)) {
            Storage::disk('public')->delete($giftCardStore->image_path);
        }

        $giftCardStore->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Hediye kartı mağazası başarıyla silindi.'
        ]);
    }
}
