<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Support\AdVideoDuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'link' => 'nullable|url|max:500',
            // Video: alt yapı hazır (panel UI yorumda). Max 10 sn.
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|mimes:mp4,mov,webm|max:20480',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $imagePath = $request->file('image')->store('ads', 'public');

        if (!$imagePath) {
            return $this->fail($request, 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.', 500);
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $this->storeValidatedVideo($request);
        }

        Ad::create([
            'title' => $request->input('title'),
            'image_path' => $imagePath,
            'link' => $request->input('link') ?: null,
            'video_path' => $videoPath,
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
            'link' => 'nullable|url|max:500',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|mimes:mp4,mov,webm|max:20480',
            'remove_video' => 'nullable|in:on,1,true',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $ad->title = $request->input('title');
        $ad->link = $request->input('link') ?: null;
        $ad->sort_order = $request->integer('sort_order', $ad->sort_order);
        $ad->is_active = $request->has('is_active');

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ads', 'public');

            if (!$imagePath) {
                return $this->fail($request, 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.', 500);
            }

            $this->deletePublicFile($ad->image_path);
            $ad->image_path = $imagePath;
        }

        if (!$ad->image_path || $ad->image_path === '0') {
            return $this->fail($request, 'Geçerli bir görsel gerekli.', 422);
        }

        if ($request->boolean('remove_video') && !$request->hasFile('video')) {
            $this->deletePublicFile($ad->video_path);
            $ad->video_path = null;
        }

        if ($request->hasFile('video')) {
            $videoPath = $this->storeValidatedVideo($request);
            $this->deletePublicFile($ad->video_path);
            $ad->video_path = $videoPath;
        }

        $ad->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam güncellendi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam güncellendi.');
    }

    public function destroy(Ad $ad)
    {
        $this->deletePublicFile($ad->image_path);
        $this->deletePublicFile($ad->video_path);

        $ad->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam silindi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam silindi.');
    }

    private function storeValidatedVideo(Request $request): string
    {
        $file = $request->file('video');
        $tmp = $file->getRealPath();

        $tooLong = AdVideoDuration::exceedsLimit($tmp, AdVideoDuration::MAX_SECONDS);
        if ($tooLong === true) {
            $seconds = AdVideoDuration::seconds($tmp);
            $msg = sprintf(
                'Video en fazla %d saniye olabilir. Yüklenen video yaklaşık %.1f saniye.',
                AdVideoDuration::MAX_SECONDS,
                $seconds ?? 0
            );
            throw ValidationException::withMessages(['video' => $msg]);
        }

        if ($tooLong === null) {
            // Süre okunamadıysa (webm vb.) yine de yükle; istemci tarafı 10 sn kontrolü yorumda hazır.
            // İleride ffprobe kurulursa sunucu tarafı kesinleşir.
        }

        $path = $file->store('ads/videos', 'public');
        if (!$path) {
            throw ValidationException::withMessages([
                'video' => 'Video yüklenemedi. Storage yazma iznini kontrol edin.',
            ]);
        }

        return $path;
    }

    private function deletePublicFile(?string $path): void
    {
        if (!$path || $path === '0' || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function fail(Request $request, string $message, int $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->back()->with('error', $message);
    }
}
