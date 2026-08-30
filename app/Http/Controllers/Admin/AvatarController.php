<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AvatarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view avatars')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create avatars')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit avatars')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete avatars')->only(['destroy']);
    }

    public function index()
    {
        $avatars = Avatar::ordered()->orderBy('id')->paginate(50);
        return view('admin.avatars.index', compact('avatars'));
    }

    public function create()
    {
        return redirect()->route('admin.avatars.index');
    }

    public function store(Request $request)
    {
        $rules = [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ];

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Avatar validation failed:', $e->errors());
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
            $imagePath = $request->file('image')->store('avatars', 'public');

            $avatar = new Avatar();
            $avatar->image_path = $imagePath;
            $avatar->is_active = $request->has('is_active') && $request->is_active !== null;
            $avatar->sort_order = $request->integer('sort_order', 0);
            $avatar->save();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Avatar başarıyla oluşturuldu.'
                ]);
            }

            return redirect()->route('admin.avatars.index')->with('success', 'Avatar başarıyla oluşturuldu.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Görsel yüklenemedi.'
            ], 422);
        }

        return redirect()->back()->with('error', 'Görsel yüklenemedi.');
    }

    public function show(Avatar $avatar)
    {
        return redirect()->route('admin.avatars.index');
    }

    public function edit(Avatar $avatar)
    {
        return redirect()->route('admin.avatars.index');
    }

    public function update(Request $request, Avatar $avatar)
    {
        $rules = [
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ];

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Avatar validation failed:', $e->errors());
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->validator)->withInput();
        }

        // Yeni görsel yüklendiyse: önce yeni dosyayı kaydet, başarılı olunca eskisini sil
        if ($request->hasFile('image')) {
            $oldPath = $avatar->image_path;
            $imagePath = $request->file('image')->store('avatars', 'public');

            if (! Storage::disk('public')->exists($imagePath)) {
                return $this->avatarErrorResponse($request, 'Yeni görsel kaydedilemedi.', 422);
            }

            $avatar->image_path = $imagePath;

            if ($oldPath && $oldPath !== $imagePath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $avatar->is_active = $request->has('is_active') && $request->is_active !== null;
        $avatar->sort_order = $request->integer('sort_order', $avatar->sort_order);
        $avatar->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Avatar başarıyla güncellendi.'
            ]);
        }

        return redirect()->route('admin.avatars.index')->with('success', 'Avatar başarıyla güncellendi.');
    }

    public function destroy(Avatar $avatar)
    {
        $inUse = $avatar->users()->count();
        if ($inUse > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu avatar ' . $inUse . ' kullanıcı tarafından kullanılıyor. Önce kullanıcıları başka avatara geçirin veya silmeyin.',
            ], 422);
        }

        // Görseli sil
        if ($avatar->image_path && Storage::disk('public')->exists($avatar->image_path)) {
            Storage::disk('public')->delete($avatar->image_path);
        }

        $avatar->delete();

        return response()->json([
            'success' => true,
            'message' => 'Avatar başarıyla silindi.'
        ]);
    }

    private function avatarErrorResponse(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()->with('error', $message);
    }
}
