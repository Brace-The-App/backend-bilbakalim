<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AdWatchStatsService;
use App\Support\AdVideoDuration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    /** Yaygın video uzantıları (mime tarayıcı/OS'a göre değişebilir). */
    private const VIDEO_EXTENSIONS = [
        'mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v', '3gp', '3g2',
        'ogv', 'ogg', 'mpeg', 'mpg', 'mpe', 'wmv', 'flv', 'f4v',
        'ts', 'mts', 'm2ts', 'vob', 'asf', 'rm', 'rmvb', 'divx',
    ];

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

    public function watchStats(AdWatchStatsService $statsService)
    {
        return response()->json([
            'success' => true,
            'data' => $statsService->summary(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'media_type' => 'required|in:image,video',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'video' => 'nullable|file|max:20480',
            'link' => 'nullable|url|max:500',
            'cta_text' => 'nullable|string|max:120',
            'reward_coins' => 'nullable|integer|min:1|max:1000',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $mediaType = $request->input('media_type');
        $imagePath = null;
        $videoPath = null;

        if ($mediaType === 'image') {
            if (!$request->hasFile('image')) {
                return $this->fail($request, 'Görsel reklam için görsel zorunlu.', 422);
            }
            $imagePath = $request->file('image')->store('ads', 'public');
            if (!$imagePath) {
                return $this->fail($request, 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.', 500);
            }
        } else {
            if (!$request->hasFile('video')) {
                return $this->fail($request, 'Video reklam için video zorunlu.', 422);
            }
            $videoPath = $this->storeValidatedVideo($request);
            $imagePath = $this->ensureVideoPlaceholder();
        }

        $link = trim((string) $request->input('link', ''));
        if ($link === '') {
            $link = 'https://yudengames.com/';
        }

        $ctaText = trim((string) $request->input('cta_text', ''));
        if ($ctaText === '') {
            $ctaText = Ad::DEFAULT_CTA_TEXT;
        }

        Ad::create([
            'title' => $request->input('title'),
            'image_path' => $imagePath,
            'link' => $link,
            'cta_text' => $ctaText,
            'video_path' => $videoPath,
            'is_active' => $request->has('is_active'),
            'sort_order' => $request->integer('sort_order', 0),
            'reward_coins' => max(1, min(1000, (int) $request->input('reward_coins', Ad::DEFAULT_REWARD_COINS))),
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
            'media_type' => 'required|in:image,video',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'video' => 'nullable|file|max:20480',
            'link' => 'nullable|url|max:500',
            'cta_text' => 'nullable|string|max:120',
            'reward_coins' => 'nullable|integer|min:1|max:1000',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $mediaType = $request->input('media_type');
        $ad->title = $request->input('title');
        $ad->sort_order = $request->integer('sort_order', $ad->sort_order);
        $ad->is_active = $request->has('is_active');
        $ad->reward_coins = max(1, min(1000, (int) $request->input('reward_coins', $ad->reward_coins ?: Ad::DEFAULT_REWARD_COINS)));

        if ($request->filled('link')) {
            $ad->link = trim((string) $request->input('link'));
        }

        if ($request->has('cta_text')) {
            $ctaText = trim((string) $request->input('cta_text', ''));
            $ad->cta_text = $ctaText !== '' ? $ctaText : Ad::DEFAULT_CTA_TEXT;
        }

        if ($mediaType === 'image') {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('ads', 'public');
                if (!$imagePath) {
                    return $this->fail($request, 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.', 500);
                }
                $this->deletePublicFile($ad->image_path, true);
                $ad->image_path = $imagePath;
            }

            if (!$ad->image_path || $ad->image_path === '0' || $ad->image_path === Ad::VIDEO_PLACEHOLDER_PATH) {
                return $this->fail($request, 'Görsel reklam için geçerli bir görsel gerekli.', 422);
            }

            if ($ad->video_path) {
                $this->deletePublicFile($ad->video_path);
                $ad->video_path = null;
            }
        } else {
            if ($request->hasFile('video')) {
                $videoPath = $this->storeValidatedVideo($request);
                $this->deletePublicFile($ad->video_path);
                $ad->video_path = $videoPath;
            }

            if (!$ad->video_path) {
                return $this->fail($request, 'Video reklam için video zorunlu.', 422);
            }

            // Görsel yüklenirse gerçek poster; yoksa placeholder (liste için)
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('ads', 'public');
                if (!$imagePath) {
                    return $this->fail($request, 'Görsel yüklenemedi. Storage yazma iznini kontrol edin.', 500);
                }
                $this->deletePublicFile($ad->image_path, true);
                $ad->image_path = $imagePath;
            } elseif (!$ad->image_path || $ad->image_path === '0') {
                $ad->image_path = $this->ensureVideoPlaceholder();
            }
        }

        $ad->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Reklam güncellendi.']);
        }

        return redirect()->route('admin.ads.index')->with('success', 'Reklam güncellendi.');
    }

    public function destroy(Ad $ad)
    {
        $this->deletePublicFile($ad->image_path, true);
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
        $this->assertAcceptedVideo($file);

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

        $path = $file->store('ads/videos', 'public');
        if (!$path) {
            throw ValidationException::withMessages([
                'video' => 'Video yüklenemedi. Storage yazma iznini kontrol edin.',
            ]);
        }

        return $path;
    }

    private function assertAcceptedVideo(UploadedFile $file): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: ''));

        $extOk = $ext !== '' && in_array($ext, self::VIDEO_EXTENSIONS, true);
        $mimeOk = $mime !== '' && (
            str_starts_with($mime, 'video/')
            || in_array($mime, [
                'application/octet-stream',
                'application/mp4',
                'application/ogg',
            ], true)
        );

        if (!$extOk && !$mimeOk) {
            throw ValidationException::withMessages([
                'video' => 'Desteklenmeyen dosya. Video formatı yükleyin (MP4, MOV, WebM, MKV, AVI vb., max 20 MB).',
            ]);
        }

        if ($file->getSize() > 20 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'video' => 'Video en fazla 20 MB olabilir.',
            ]);
        }
    }

    private function ensureVideoPlaceholder(): string
    {
        $path = Ad::VIDEO_PLACEHOLDER_PATH;
        if (!Storage::disk('public')->exists($path)) {
            $dir = dirname($path);
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            // Minimal 1x1 PNG
            $png = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            );
            Storage::disk('public')->put($path, $png);
        }

        return $path;
    }

    private function deletePublicFile(?string $path, bool $protectPlaceholder = false): void
    {
        if (!$path || $path === '0' || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        if ($protectPlaceholder && $path === Ad::VIDEO_PLACEHOLDER_PATH) {
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
