<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\User;
use App\Services\BotAnswerEngine;
use App\Services\DuelBotSettings;
use Illuminate\Http\Request;

class DuelBotController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view duel bot');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit duel bot')
            ->only([
                'store',
                'clearLogs',
                'updateActive',
                'bulkActive',
                'updateBehavior',
                'updateMatchmaking',
                'updateProfile',
                'updateAvatar',
                'update',
                'endMatch',
                'emergencyReset',
            ]);
    }

    public function index(Request $request)
    {
        $bots = DuelBotSettings::catalog();
        $avatars = Avatar::query()->active()->ordered()->orderBy('id')->get();

        $realIds = collect($bots)->where('is_dummy', false)->pluck('id')->map(fn ($id) => (string) $id);
        $selectedId = (string) $request->query('bot', $realIds->first() ?? '');
        $selected = collect($bots)->firstWhere('id', $selectedId) ?? ($bots[0] ?? null);

        $showDetail = $selected && empty($selected['is_dummy']) && !empty($selected['user_id']);
        $bot = $showDetail ? DuelBotSettings::botUser((int) $selected['user_id']) : null;
        $settings = $showDetail
            ? DuelBotSettings::all((int) $selected['user_id'])
            : DuelBotSettings::defaults() + ['target_accuracy' => 0.5, 'tier_meta' => BotAnswerEngine::tierMeta('medium')];

        $tierHelpMap = [];
        $tierEx8Map = [];
        foreach (array_keys(BotAnswerEngine::TIERS) as $tier) {
            $tierHelpMap[$tier] = BotAnswerEngine::tierHelpText($tier);
            $ex = BotAnswerEngine::discreteExamples($tier, 8)[0] ?? null;
            $meta = BotAnswerEngine::tierMeta($tier);
            $tierEx8Map[$tier] = [
                'band' => '%' . (int) round($meta['min'] * 100) . '–' . (int) round($meta['max'] * 100),
                'ex8' => $ex ? ($ex['correct'] . '/8 (%' . $ex['pct'] . ')') : '',
            ];
        }

        $logBotFilter = $showDetail && $bot ? (int) $bot->id : 0;
        $logs = DuelBotSettings::recentLogs(150, $logBotFilter > 0 ? $logBotFilter : null);
        $matchmaking = DuelBotSettings::matchmakingSettings();

        return view('admin.duel-bot.index', compact(
            'settings',
            'bot',
            'bots',
            'selected',
            'selectedId',
            'showDetail',
            'avatars',
            'logs',
            'tierHelpMap',
            'tierEx8Map',
            'logBotFilter',
            'matchmaking'
        ));
    }

    public function logs(Request $request)
    {
        $botId = (int) $request->query('bot', 0);
        $limit = max(50, min(400, (int) $request->query('limit', 200)));

        return response()->json([
            'success' => true,
            'bot_filter' => $botId > 0 ? $botId : null,
            'lines' => DuelBotSettings::recentLogs($limit, $botId > 0 ? $botId : null),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    public function live()
    {
        return response()->json([
            'success' => true,
            'bots' => DuelBotSettings::liveSnapshots(),
            'matchmaking' => DuelBotSettings::adminMatchmakingDashboard(),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /** Bot düello geçmişi (modal) */
    public function duels(Request $request, int $userId)
    {
        $cfg = DuelBotSettings::findBotConfig($userId);
        if (!$cfg) {
            return response()->json([
                'success' => false,
                'message' => 'Bu kullanıcı bot havuzunda değil.',
            ], 404);
        }

        $bot = User::query()->find($userId);
        $perPage = (int) $request->query('per_page', 50);
        $page = (int) $request->query('page', 1);
        $history = DuelBotSettings::botDuelHistory($userId, $perPage, $page);

        return response()->json([
            'success' => true,
            'bot' => [
                'user_id' => $userId,
                'name' => $bot?->name,
                'difficulty' => $cfg['difficulty'],
                'coins' => (int) ($bot?->coins ?? 0),
            ],
            'duels' => $history['items'] ?? [],
            'pagination' => $history['pagination'] ?? null,
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /** Tek düello soru detayı (modal) */
    public function duelDetail(Request $request, int $userId, int $duelId)
    {
        $cfg = DuelBotSettings::findBotConfig($userId);
        if (!$cfg) {
            return response()->json(['success' => false, 'message' => 'Bot havuzunda değil.'], 404);
        }

        $detail = DuelBotSettings::botDuelDetail($userId, $duelId);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'Düello bulunamadı.'], 404);
        }

        // Tüm sorular döner; modal JS 50'şer sayfalar (pager üstte görünür).
        $total = count($detail['questions'] ?? []);

        return response()->json([
            'success' => true,
            'bot_user_id' => $userId,
            'detail' => $detail,
            'pagination' => [
                'total' => $total,
                'per_page' => 50,
                'current_page' => 1,
                'last_page' => max(1, (int) ceil($total / 50)),
            ],
        ]);
    }

    /** Akıllı eşleşme ayarları (bant / cooldown / yeni oyuncu) */
    public function updateMatchmaking(Request $request)
    {
        $validated = $request->validate([
            'rematch_cooldown_seconds' => 'required|integer|min:0|max:120',
            'new_player_max_duels' => 'required|integer|min:0|max:50',
            'skill_sample_answers' => 'required|integer|min:5|max:100',
            'soft_cap_streak' => 'required|integer|min:0|max:20',
            'soft_cap_extra_seconds' => 'required|integer|min:0|max:300',
            'soft_cap_wait_bump' => 'required|integer|min:0|max:30',
            'bands' => 'required|array|min:1|max:8',
            'bands.*.max_pct' => 'required|integer|min:1|max:100',
            'bands.*.tiers' => 'required|array|min:1',
            'bands.*.tiers.*' => 'required|in:easy,medium,hard,professor',
            'bands.*.label' => 'nullable|string|max:40',
        ]);

        $settings = DuelBotSettings::saveMatchmaking($validated);

        return response()->json([
            'success' => true,
            'message' => 'Akıllı eşleşme kaydedildi.',
            'matchmaking' => $settings,
        ]);
    }

    /** Sadece zorluk seç → hazır bot (pasif) oluştur */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'difficulty' => 'required|in:easy,medium,hard,professor',
        ]);

        $created = DuelBotSettings::createQuickBot($validated['difficulty']);
        $user = $created['user'];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Bot oluşturuldu: {$user->name}",
                'bot' => [
                    'user_id' => (int) $user->id,
                    'name' => $user->name,
                    'difficulty' => $created['config']['difficulty'],
                    'is_active' => false,
                    'coins' => (int) $user->coins,
                    'redirect' => route('admin.duel-bot.index', ['bot' => $user->id]) . '#duelBotWorkspace',
                ],
            ]);
        }

        return redirect()
            ->to(route('admin.duel-bot.index', ['bot' => $user->id]) . '#duelBotWorkspace')
            ->with('success', "Yeni bot eklendi (pasif): {$user->name}");
    }

    public function clearLogs(Request $request)
    {
        DuelBotSettings::clearLogs();
        DuelBotSettings::log('Log paneli temizlendi');

        $botId = (int) $request->input('bot', $request->query('bot', 0));

        return response()->json([
            'success' => true,
            'message' => 'Log temizlendi.',
            'bot_filter' => $botId > 0 ? $botId : null,
            'lines' => DuelBotSettings::recentLogs(200, $botId > 0 ? $botId : null),
            'live' => DuelBotSettings::liveSnapshots(),
            'matchmaking' => DuelBotSettings::adminMatchmakingDashboard(),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /** Eski form / cache uyumluluğu */
    public function update(Request $request)
    {
        return redirect()->route('admin.duel-bot.index')
            ->with('success', 'Lütfen sayfayı yenileyin; kayıt butonları bölümlere ayrıldı.');
    }

    /** Aktif/pasif — anında (AJAX) */
    public function updateActive(Request $request)
    {
        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0) {
            $userId = (int) DuelBotSettings::all()['user_id'];
        }
        $current = DuelBotSettings::all($userId);
        $isActive = $request->boolean('is_active');

        $settings = DuelBotSettings::save([
            'user_id' => $userId,
            'is_active' => $isActive,
            'difficulty' => $current['difficulty'],
            'match_wait_seconds' => $current['match_wait_seconds'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $isActive ? 'Bot aktif.' : 'Bot pasif.',
            'is_active' => $settings['is_active'],
            'user_id' => $userId,
        ]);
    }

    /** Zorluğa göre toplu aktif/pasif */
    public function bulkActive(Request $request)
    {
        $validated = $request->validate([
            'difficulty' => 'required|in:easy,medium,hard,professor',
            'is_active' => 'required|boolean',
        ]);

        $result = DuelBotSettings::bulkSetActiveByDifficulty(
            $validated['difficulty'],
            (bool) $validated['is_active']
        );

        $label = match ($result['difficulty']) {
            'easy' => 'Kolay',
            'medium' => 'Orta',
            'hard' => 'Zor',
            'professor' => 'Terminatör',
            default => $result['difficulty'],
        };

        return response()->json([
            'success' => true,
            'message' => $result['is_active']
                ? "{$label} botları açıldı ({$result['updated']})."
                : "{$label} botları kapatıldı ({$result['updated']}).",
            'updated' => $result['updated'],
            'difficulty' => $result['difficulty'],
            'is_active' => $result['is_active'],
        ]);
    }

    /** Seçili botun aktif maçını bitir (bot çekilir). */
    public function endMatch(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $result = DuelBotSettings::endActiveMatch((int) $validated['user_id']);

        if (! ($result['success'] ?? false)) {
            $status = (int) ($result['http_status'] ?? 400);
            if ($status < 400) {
                $status = 400;
            }

            return response()->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Maç bitirilemedi.'),
                'duel_id' => $result['duel_id'] ?? null,
                'user_id' => (int) $validated['user_id'],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Maç bitirildi.'),
            'duel_id' => $result['duel_id'] ?? null,
            'user_id' => (int) $validated['user_id'],
        ]);
    }

    /** Bot maçlarını kapat + sistem toparlama (insan–insan aktif maçlara dokunmaz). */
    public function emergencyReset(Request $request)
    {
        $includeHuman = (bool) $request->boolean('include_human');
        $result = DuelBotSettings::emergencyResetAll('admin', $includeHuman);

        $status = ($result['success'] ?? false) ? 200 : 500;

        return response()->json($result, $status);
    }

    /** Zorluk / bekleme — anında (AJAX) */
    public function updateBehavior(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'difficulty' => 'required|in:easy,medium,hard,professor',
            'match_wait_seconds' => 'required|integer|min:1|max:30',
        ]);

        $userId = (int) ($validated['user_id'] ?? 0);
        if ($userId <= 0) {
            $userId = (int) DuelBotSettings::all()['user_id'];
        }
        $current = DuelBotSettings::all($userId);
        $settings = DuelBotSettings::save([
            'user_id' => $userId,
            'is_active' => $current['is_active'],
            'difficulty' => $validated['difficulty'],
            'match_wait_seconds' => $validated['match_wait_seconds'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Davranış ayarları güncellendi.',
            'settings' => $settings,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $settings = DuelBotSettings::all();
        $userId = (int) $request->input('user_id', $settings['user_id']);
        $bot = User::query()->findOrFail($userId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:191|unique:users,email,' . $bot->id,
            'phone' => 'nullable|string|max:32',
            'coins' => 'nullable|integer|min:0|max:1000000',
            'duel_earned_coins' => 'nullable|integer|min:0|max:1000000',
        ], [
            'email.unique' => 'Bu e-posta adresi başka bir kullanıcıda kayıtlı.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
        ]);

        $bot->name = $validated['name'];
        $bot->is_bot = true;
        $bot->email = $validated['email'] ?? $bot->email;
        $bot->phone = $validated['phone'] ?? $bot->phone;
        if (array_key_exists('coins', $validated) && $validated['coins'] !== null) {
            $bot->coins = (int) $validated['coins'];
        }
        if (array_key_exists('duel_earned_coins', $validated) && $validated['duel_earned_coins'] !== null) {
            $bot->duel_earned_coins = (int) $validated['duel_earned_coins'];
        }
        $bot->save();

        DuelBotSettings::log(
            "Profil güncellendi: #{$bot->id} {$bot->name} · coins={$bot->coins} · duel_earned={$bot->duel_earned_coins}"
        );

        return redirect()
            ->to(route('admin.duel-bot.index', ['bot' => $bot->id]) . '#duelBotWorkspace')
            ->with('success', 'Profil kaydedildi.');
    }

    public function updateAvatar(Request $request)
    {
        $settings = DuelBotSettings::all();
        $userId = (int) $request->input('user_id', $settings['user_id']);
        $bot = User::query()->findOrFail($userId);

        $validated = $request->validate([
            'avatar' => 'required|integer|exists:avatars,id',
        ]);

        $avatar = Avatar::query()->findOrFail($validated['avatar']);
        $bot->avatar = (string) $avatar->id;
        $bot->profile_image = $avatar->image_path;
        $bot->is_bot = true;
        $bot->save();

        DuelBotSettings::log("Avatar güncellendi: bot #{$bot->id} → avatar #{$avatar->id}");

        return redirect()
            ->to(route('admin.duel-bot.index', ['bot' => $bot->id]) . '#duelBotWorkspace')
            ->with('success', 'Avatar kaydedildi.');
    }
}
