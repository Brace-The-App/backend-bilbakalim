<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Support\ClientPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    /**
     * Destek / iletişim / şikayet / öneri kaydı.
     * Public (landing) veya Sanctum token ile (app / web_player).
     */
    public function store(Request $request)
    {
        // Honeypot — botlar doldurursa sessizce "başarılı" dön
        if (filled($request->input('website')) || filled($request->input('company_url'))) {
            return response()->json([
                'success' => true,
                'message' => 'Mesajınız alındı. Teşekkürler.',
            ]);
        }

        // Landing alias: source göndermeden de çalışsın
        if (!$request->filled('source') && $request->is('api/landing/support')) {
            $request->merge(['source' => 'landing', 'type' => $request->input('type', 'contact')]);
        }

        $validated = $request->validate([
            'source' => 'required|in:landing,app,web_player',
            'type' => 'required|in:contact,complaint,suggestion,job,other',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email:filter|max:190',
            'phone' => 'nullable|string|max:40',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|min:10|max:4000',
            'platform' => 'nullable|string|max:32',
        ], [
            'source.required' => 'Kaynak gerekli.',
            'source.in' => 'Geçersiz kaynak.',
            'type.required' => 'Konu seçin.',
            'type.in' => 'Geçersiz konu.',
            'name.max' => 'Ad soyad en fazla 120 karakter olabilir.',
            'email.email' => 'Geçerli bir e-posta girin.',
            'email.max' => 'E-posta en fazla 190 karakter olabilir.',
            'phone.max' => 'Telefon en fazla 40 karakter olabilir.',
            'subject.max' => 'Konu en fazla 200 karakter olabilir.',
            'message.required' => 'Mesaj yazın.',
            'message.min' => 'Mesaj en az 10 karakter olmalı.',
            'message.max' => 'Mesaj en fazla 4000 karakter olabilir.',
        ], [
            'name' => 'ad soyad',
            'email' => 'e-posta',
            'phone' => 'telefon',
            'subject' => 'konu',
            'message' => 'mesaj',
            'type' => 'konu',
        ]);

        $user = Auth::guard('sanctum')->user();

        if (in_array($validated['source'], ['app', 'web_player'], true) && !$user) {
            return response()->json([
                'success' => false,
                'message' => 'Bu kaynak için giriş yapmanız gerekir.',
            ], 401);
        }

        if ($validated['source'] === 'landing') {
            if (blank($validated['email'] ?? null) && blank($validated['name'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'İsim veya e-posta gerekli.',
                ], 422);
            }
        }

        $message = $this->sanitizeText($validated['message']);
        if (mb_strlen($message) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'Mesaj en az 10 karakter olmalı.',
            ], 422);
        }

        $platform = ClientPlatform::normalizePlatform(
            $validated['platform'] ?? $request->header('X-Platform')
        );
        if ($platform === null) {
            $platform = ClientPlatform::guessFromUserAgent((string) $request->userAgent());
        }

        $row = SupportMessage::query()->create([
            'user_id' => $user?->id,
            'source' => $validated['source'],
            'type' => $validated['type'],
            'name' => $this->sanitizeNullable($validated['name'] ?? null, 120)
                ?: ($user?->name ? Str::limit((string) $user->name, 120, '') : null),
            'email' => $this->sanitizeNullable($validated['email'] ?? null, 190)
                ?: ($user?->email ? Str::limit((string) $user->email, 190, '') : null),
            'phone' => $this->sanitizeNullable($validated['phone'] ?? null, 40)
                ?: ($user?->phone ? Str::limit((string) $user->phone, 40, '') : null),
            'subject' => $this->sanitizeNullable($validated['subject'] ?? null, 200),
            'message' => $message,
            'status' => 'new',
            'platform' => $platform,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'ip_address' => Str::limit((string) $request->ip(), 45, ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mesajınız alındı. Teşekkürler.',
            'id' => $row->id,
        ], 201);
    }

    private function sanitizeText(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace("\0", '', $value);
        $value = preg_replace("/[ \t]+/u", ' ', $value) ?? $value;
        $value = trim($value);

        return Str::limit($value, 4000, '');
    }

    private function sanitizeNullable(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(strip_tags($value));
        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }
}
