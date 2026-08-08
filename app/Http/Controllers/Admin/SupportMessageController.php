<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SupportReplyMail;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupportMessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view support')
            ->only(['index', 'unreadCount', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit support')
            ->only(['updateStatus', 'reply']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete support')
            ->only(['destroy']);
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

        $mailAccounts = config('support.mail_accounts', []);
        $defaultAccount = (string) config('support.default_account', 'destek');

        return view('admin.support.show', compact('message', 'mailAccounts', 'defaultAccount'));
    }

    public function reply(Request $request, int $id)
    {
        $accountIds = collect(config('support.mail_accounts', []))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'reply_body' => 'required|string|min:2|max:5000',
            'mail_account' => 'required|string|in:'.implode(',', $accountIds ?: ['destek']),
            'mark_read' => 'nullable|boolean',
        ]);

        $message = SupportMessage::query()
            ->with(['user:id,name,email'])
            ->findOrFail($id);

        $to = $message->recipientEmail();
        if (!$to) {
            return back()
                ->withInput()
                ->with('error', 'Bu talepte geçerli bir e-posta adresi yok.');
        }

        $account = collect(config('support.mail_accounts', []))
            ->firstWhere('id', $validated['mail_account']);

        if (!$account || empty($account['from_address'])) {
            return back()
                ->withInput()
                ->with('error', 'Gönderen mail hesabı yapılandırılmamış.');
        }

        $body = trim(strip_tags($validated['reply_body']));
        if ($body === '') {
            return back()
                ->withInput()
                ->with('error', 'Mesaj boş olamaz.');
        }

        try {
            Mail::to($to)->send(new SupportReplyMail(
                ticket: $message,
                replyBody: $body,
                account: $account,
                adminName: $request->user()?->name,
            ));
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Mail gönderilemedi: '.$e->getMessage());
        }

        $message->email_replied_at = now();
        $message->last_email_reply = $body;
        $message->last_email_from = $account['from_address'];
        if ($request->boolean('mark_read', true)) {
            $message->status = 'read';
            if (!$message->read_at) {
                $message->read_at = now();
            }
        }
        $message->save();

        return redirect()
            ->route('admin.support.show', $message->id)
            ->with('success', 'Cevap '.$to.' adresine gönderildi.');
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
