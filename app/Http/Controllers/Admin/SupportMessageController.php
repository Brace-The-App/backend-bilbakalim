<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportMessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!SupportMessage::canAccess($request->user())) {
                abort(403, 'Bu sayfaya erişim yetkiniz yok.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $source = (string) $request->query('source', '');
        $type = (string) $request->query('type', '');
        $q = trim((string) $request->query('q', ''));

        $query = SupportMessage::query()
            ->with(['user:id,name,email,phone'])
            ->orderByRaw("CASE WHEN status = 'new' THEN 0 WHEN status = 'later' THEN 1 WHEN status = 'read' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        if ($status !== '' && isset(SupportMessage::STATUSES[$status])) {
            $query->where('status', $status);
        }
        if ($source !== '' && in_array($source, SupportMessage::SOURCES, true)) {
            $query->where('source', $source);
        }
        if ($type !== '' && isset(SupportMessage::TYPES[$type])) {
            $query->where('type', $type);
        }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $messages = $query->paginate(30)->withQueryString();

        $counts = [
            'all' => SupportMessage::query()->count(),
            'new' => SupportMessage::query()->where('status', 'new')->count(),
            'read' => SupportMessage::query()->where('status', 'read')->count(),
            'later' => SupportMessage::query()->where('status', 'later')->count(),
            'archived' => SupportMessage::query()->where('status', 'archived')->count(),
        ];

        return view('admin.support.index', compact('messages', 'counts', 'status', 'source', 'type', 'q'));
    }

    /** Okunmamış (yeni) sayısı — sayfa yenilemede sidebar için de kullanılabilir */
    public function unreadCount()
    {
        return response()->json([
            'success' => true,
            'unread' => SupportMessage::query()->where('status', 'new')->count(),
            'later' => SupportMessage::query()->where('status', 'later')->count(),
        ]);
    }

    public function show(int $id)
    {
        $message = SupportMessage::query()
            ->with(['user:id,name,email,phone,coins'])
            ->findOrFail($id);

        // Açılınca sadece "yeni" otomatik okundu olur; "sonra bak" elle kalır
        if ($message->status === 'new') {
            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
            $message->refresh();
        }

        return view('admin.support.show', compact('message'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,read,later,archived',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $message = SupportMessage::query()->findOrFail($id);
        $message->status = $validated['status'];
        if (array_key_exists('admin_note', $validated)) {
            $note = trim(strip_tags((string) ($validated['admin_note'] ?? '')));
            $message->admin_note = $note !== '' ? $note : null;
        }
        if (in_array($validated['status'], ['read', 'later', 'archived'], true) && !$message->read_at) {
            $message->read_at = now();
        }
        if ($validated['status'] === 'new') {
            $message->read_at = null;
        }
        $message->save();

        return redirect()
            ->route('admin.support.index')
            ->with('success', 'Durum güncellendi.');
    }

    public function destroy(int $id)
    {
        $message = SupportMessage::query()->findOrFail($id);
        $message->delete();

        return redirect()
            ->route('admin.support.index')
            ->with('success', 'Mesaj silindi.');
    }
}
