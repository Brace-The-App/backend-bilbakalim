<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':admin|personel');
    }

    public function index()
    {
        $ads = Ad::ordered()->paginate(20);

        return view('admin.ads.index', compact('ads'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $imagePath = $request->file('image')->store('ads', 'public');

        if (!$imagePath) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Görsel yüklenemedi.');
        }

        Ad::create([
            'title' => $request->input('title'),
            'image_path' => $imagePath,
            'is_active' => $request->has('is_active'),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam eklendi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam eklendi.');
    }

    public function update(Request $request, Ad $ad)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $ad->title = $request->input('title');
        $ad->sort_order = $request->integer('sort_order', $ad->sort_order);
        $ad->is_active = $request->has('is_active');

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ads', 'public');

            if (!$imagePath) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.',
                    ], 500);
                }

                return redirect()->back()->with('error', 'Görsel yüklenemedi.');
            }

            if ($ad->image_path && !filter_var($ad->image_path, FILTER_VALIDATE_URL) && $ad->image_path !== '0') {
                Storage::disk('public')->delete($ad->image_path);
            }
            $ad->image_path = $imagePath;
        }

        if (!$ad->image_path || $ad->image_path === '0') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geçerli bir görsel gerekli.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Geçerli bir görsel gerekli.');
        }

        $ad->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam güncellendi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam güncellendi.');
    }

    public function destroy(Ad $ad)
    {
        if ($ad->image_path && !filter_var($ad->image_path, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($ad->image_path);
        }

        $ad->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam silindi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam silindi.');
    }
}
