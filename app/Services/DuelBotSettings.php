<?php

namespace App\Services;

use App\Models\Duel;
use App\Models\GeneralSetting;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class DuelBotSettings
{
    public const DEFAULT_BOT_USER_ID = 128;
    public const ALLOWED_ADMIN_USER_ID = 15;

    public const KEY_USER_ID = 'duel_bot_user_id';
    public const KEY_ACTIVE = 'duel_bot_active';
    public const KEY_DIFFICULTY = 'duel_bot_difficulty';
    public const KEY_MATCH_WAIT_SECONDS = 'duel_bot_match_wait_seconds';
    public const KEY_BOTS = 'duel_bots';
    public const KEY_MATCH_BANDS = 'duel_bot_match_bands';
    public const KEY_REMATCH_COOLDOWN = 'duel_bot_rematch_cooldown_seconds';
    public const KEY_NEW_PLAYER_DUELS = 'duel_bot_new_player_max_duels';
    public const KEY_SKILL_SAMPLE = 'duel_bot_skill_sample_answers';
    public const KEY_SOFT_CAP_STREAK = 'duel_bot_soft_cap_streak';
    public const KEY_SOFT_CAP_EXTRA = 'duel_bot_soft_cap_extra_seconds';
    public const KEY_SOFT_CAP_WAIT_BUMP = 'duel_bot_soft_cap_wait_bump';

    public const CACHE_BOTS = 'duel:bots_list';
    public const CACHE_MM_SETTINGS = 'duel:mm_settings';
    public const CACHE_LIVE = 'duel:live_snapshots';
    public const CACHE_MATCH_MIX = 'duel:match_mix_24h';
    public const CACHE_ADMIN_MM = 'duel:admin_mm_dash';
    public const CACHE_BOT_MM_CONFIG = 'duel:bot_mm_config';

    /** @var list<array{user_id:int,difficulty:string,is_active:bool}>|null */
    private static ?array $botsMemo = null;

    private static int $botsMemoAt = 0;

    public const DIFFICULTIES = ['easy', 'medium', 'hard', 'professor'];

    /** @deprecated BotAnswerEngine::TIERS */
    public const DIFFICULTY_ACCURACY = [
        'easy' => 0.22,
        'medium' => 0.50,
        'hard' => 0.775,
        'professor' => 0.925,
    ];

    public static function allowedAdminIds(): array
    {
        return [self::ALLOWED_ADMIN_USER_ID];
    }

    public static function canManage(?User $user): bool
    {
        return $user !== null && $user->can('view duel bot');
    }

    public static function defaults(): array
    {
        return [
            'user_id' => self::DEFAULT_BOT_USER_ID,
            'is_active' => false,
            'difficulty' => 'medium',
            'match_wait_seconds' => 3,
        ];
    }

    public static function matchWaitSeconds(): int
    {
        return max(1, (int) GeneralSetting::get(
            self::KEY_MATCH_WAIT_SECONDS,
            self::defaults()['match_wait_seconds']
        ));
    }

    /**
     * Kayıtlı bot listesi. Yoksa eski tek-bot ayarlarından seed eder.
     *
     * @return list<array{user_id:int,difficulty:string,is_active:bool}>
     */
    public static function bots(): array
    {
        if (self::$botsMemo !== null && (time() - self::$botsMemoAt) < 5) {
            return self::$botsMemo;
        }

        $list = Cache::remember(self::CACHE_BOTS, 8, static function () {
            $raw = GeneralSetting::get(self::KEY_BOTS);
            $list = [];

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $row) {
                        $uid = (int) ($row['user_id'] ?? 0);
                        if ($uid <= 0) {
                            continue;
                        }
                        $list[] = [
                            'user_id' => $uid,
                            'difficulty' => BotAnswerEngine::normalizeTier((string) ($row['difficulty'] ?? 'medium')),
                            'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        ];
                    }
                }
            }

            if ($list === []) {
                $legacy = self::legacySingleBot();
                $list = [$legacy];
                // Persist without going through Cache::remember again
                GeneralSetting::set(
                    self::KEY_BOTS,
                    json_encode(array_values($list), JSON_UNESCAPED_UNICODE),
                    'json',
                    'Düello bot havuzu [{user_id,difficulty,is_active}]'
                );
            }

            return $list;
        });

        self::$botsMemo = $list;
        self::$botsMemoAt = time();

        return $list;
    }

    /** Admin/config kısa TTL cache'lerini düşür (ayar değişince). */
    public static function forgetPerfCaches(): void
    {
        self::$botsMemo = null;
        self::$botsMemoAt = 0;
        Cache::forget(self::CACHE_BOTS);
        Cache::forget(self::CACHE_MM_SETTINGS);
        Cache::forget(self::CACHE_LIVE);
        Cache::forget(self::CACHE_MATCH_MIX);
        Cache::forget(self::CACHE_ADMIN_MM);
        Cache::forget(self::CACHE_BOT_MM_CONFIG);
    }

    /** @return array{user_id:int,difficulty:string,is_active:bool} */
    private static function legacySingleBot(): array
    {
        $defaults = self::defaults();

        return [
            'user_id' => (int) GeneralSetting::get(self::KEY_USER_ID, $defaults['user_id']),
            'difficulty' => BotAnswerEngine::normalizeTier(
                (string) GeneralSetting::get(self::KEY_DIFFICULTY, $defaults['difficulty'])
            ),
            'is_active' => filter_var(
                GeneralSetting::get(self::KEY_ACTIVE, $defaults['is_active'] ? '1' : '0'),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    /** @param list<array{user_id:int,difficulty:string,is_active:bool}> $bots */
    public static function persistBots(array $bots): void
    {
        GeneralSetting::set(
            self::KEY_BOTS,
            json_encode(array_values($bots), JSON_UNESCAPED_UNICODE),
            'json',
            'Düello bot havuzu [{user_id,difficulty,is_active}]'
        );
        self::forgetPerfCaches();
    }

    public static function findBotConfig(int $userId): ?array
    {
        foreach (self::bots() as $bot) {
            if ((int) $bot['user_id'] === $userId) {
                return $bot;
            }
        }

        return null;
    }

    /**
     * Türkçe insan adı-soyadı (bot kimliği için).
     *
     * @return array{name:string,first:string,last:string}
     */
    public static function generateHumanName(?array $excludeNames = null): array
    {
        $first = [
            'Ahmet', 'Mehmet', 'Mustafa', 'Ali', 'Hüseyin', 'Hasan', 'İbrahim', 'Yusuf', 'Ömer', 'Murat',
            'Emre', 'Can', 'Burak', 'Cem', 'Deniz', 'Ege', 'Kerem', 'Onur', 'Serkan', 'Tolga',
            'Ayşe', 'Fatma', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Merve', 'Selin', 'Büşra', 'Esra',
            'Ceren', 'Deniz', 'Gizem', 'İrem', 'Melisa', 'Nazlı', 'Pınar', 'Seda', 'Yasemin', 'Derya',
        ];
        $last = [
            'Yılmaz', 'Kaya', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir',
            'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek',
            'Erdoğan', 'Güneş', 'Aksoy', 'Polat', 'Bulut', 'Acar', 'Tekin', 'Korkmaz', 'Çakır', 'Ünal',
        ];

        $exclude = array_map('mb_strtolower', $excludeNames ?? []);
        for ($i = 0; $i < 80; $i++) {
            $f = $first[array_rand($first)];
            $l = $last[array_rand($last)];
            $full = $f . ' ' . $l;
            if (!in_array(mb_strtolower($full), $exclude, true)) {
                return ['name' => $full, 'first' => $f, 'last' => $l];
            }
        }

        $f = $first[array_rand($first)];
        $l = $last[array_rand($last)];

        return ['name' => $f . ' ' . $l . ' ' . random_int(1, 99), 'first' => $f, 'last' => $l];
    }

    private static function slugifyBotEmailLocal(string $name): string
    {
        $map = [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ];
        $s = strtr($name, $map);
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '', $s) ?? 'bot');

        return $s !== '' ? $s : 'bot';
    }

    /**
     * Hızlı bot: sadece zorluk seçilir; isim/email/avatar/jeton hazır, havuza pasif eklenir.
     *
     * @return array{user:User,config:array{user_id:int,difficulty:string,is_active:bool}}
     */
    public static function createQuickBot(string $difficulty): array
    {
        $difficulty = BotAnswerEngine::normalizeTier($difficulty);
        $existingNames = User::query()->where('is_bot', true)->pluck('name')->all();
        $nameParts = self::generateHumanName($existingNames);
        $name = $nameParts['name'];

        $local = self::slugifyBotEmailLocal($name);
        $email = $local . random_int(10, 99) . '@bilbakalim.local';
        $n = 0;
        while (User::query()->where('email', $email)->exists()) {
            $n++;
            $email = $local . random_int(100, 999) . $n . '@bilbakalim.local';
        }

        do {
            $phone = '9055' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (User::query()->where('phone', $phone)->exists());

        $avatar = \App\Models\Avatar::query()->active()->inRandomOrder()->first();

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->phone = $phone;
        $user->password = bcrypt(\Illuminate\Support\Str::random(32));
        $user->role_id = 2;
        $user->coins = 1000;
        $user->total_coins = 1000;
        $user->duel_earned_coins = 0;
        $user->account_status = 'active';
        $user->is_bot = true;
        $user->is_premium = false;
        $user->auto_question = false;
        $user->game_sound = true;
        $user->referral_code = User::generateReferralCode();
        $user->has_used_referral = false;
        $user->email_verified_at = now();
        if ($avatar) {
            $user->avatar = (string) $avatar->id;
            $user->profile_image = $avatar->image_path;
        }
        $user->save();

        $bots = self::bots();
        $config = [
            'user_id' => (int) $user->id,
            'difficulty' => $difficulty,
            'is_active' => false,
        ];
        $bots[] = $config;
        self::persistBots($bots);

        self::log("YENİ BOT · #{$user->id} {$user->name} · {$difficulty} · PASİF · jeton 1000");

        return ['user' => $user, 'config' => $config];
    }

    /** Mevcut botları insan adı-soyadına çevir (bir kerelik / bakım). */
    public static function renamePoolBotsToHumanNames(): int
    {
        $used = [];
        $n = 0;
        foreach (self::bots() as $bot) {
            $user = User::query()->find((int) $bot['user_id']);
            if (!$user) {
                continue;
            }
            $parts = self::generateHumanName($used);
            $used[] = $parts['name'];
            $old = $user->name;
            $user->name = $parts['name'];
            $user->is_bot = true;
            // Email okunabilir kalsın; çakışmazsa güncelle
            $local = self::slugifyBotEmailLocal($parts['name']);
            $email = $local . '@bilbakalim.local';
            if (!User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                $user->email = $email;
            }
            $user->save();
            self::log("İSİM · bot #{$user->id} «{$old}» → «{$user->name}»");
            $n++;
        }

        return $n;
    }

    /** Tek bot ayarları (admin seçili bot / geriye uyumluluk) */
    public static function all(?int $userId = null): array
    {
        $bots = self::bots();
        $wait = self::matchWaitSeconds();

        $row = null;
        if ($userId) {
            $row = self::findBotConfig($userId);
        }
        if (!$row) {
            $row = $bots[0] ?? self::legacySingleBot();
        }

        $difficulty = BotAnswerEngine::normalizeTier($row['difficulty']);

        return [
            'user_id' => (int) $row['user_id'],
            'is_active' => (bool) $row['is_active'],
            'difficulty' => $difficulty,
            'match_wait_seconds' => $wait,
            'target_accuracy' => BotAnswerEngine::targetAccuracy($difficulty),
            'tier_meta' => BotAnswerEngine::tierMeta($difficulty),
        ];
    }

    public static function botUser(?int $userId = null): ?User
    {
        $id = $userId ?: self::all()['user_id'];

        return User::query()->find($id);
    }

    /** @return list<int> */
    public static function allBotUserIds(): array
    {
        return array_values(array_unique(array_map(
            fn ($b) => (int) $b['user_id'],
            self::bots()
        )));
    }

    public static function isBotBusy(int $userId): bool
    {
        return Duel::query()
            ->whereIn('status', ['waiting', 'active'])
            ->where(function ($q) use ($userId) {
                $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
            })
            ->exists();
    }

    /** Varsayılan isabet → bot zorluk bantları */
    public static function defaultMatchBands(): array
    {
        return [
            ['max_pct' => 40, 'tiers' => ['easy', 'medium'], 'label' => 'Düşük'],
            ['max_pct' => 65, 'tiers' => ['medium', 'hard'], 'label' => 'Orta'],
            ['max_pct' => 85, 'tiers' => ['hard', 'professor'], 'label' => 'Yüksek'],
            ['max_pct' => 100, 'tiers' => ['professor', 'hard'], 'label' => 'Çok yüksek'],
        ];
    }

    /** @return list<array{max_pct:int,tiers:list<string>,label:string}> */
    public static function matchBands(): array
    {
        $raw = GeneralSetting::get(self::KEY_MATCH_BANDS);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded) || $decoded === []) {
            return self::defaultMatchBands();
        }

        $out = [];
        foreach ($decoded as $row) {
            $max = max(1, min(100, (int) ($row['max_pct'] ?? 0)));
            $tiers = [];
            foreach ((array) ($row['tiers'] ?? []) as $t) {
                $t = BotAnswerEngine::normalizeTier((string) $t);
                if (!in_array($t, $tiers, true)) {
                    $tiers[] = $t;
                }
            }
            if ($tiers === []) {
                continue;
            }
            $out[] = [
                'max_pct' => $max,
                'tiers' => $tiers,
                'label' => (string) ($row['label'] ?? ''),
            ];
        }

        usort($out, static fn ($a, $b) => $a['max_pct'] <=> $b['max_pct']);

        return $out !== [] ? $out : self::defaultMatchBands();
    }

    public static function rematchCooldownSeconds(): int
    {
        return max(0, min(120, (int) GeneralSetting::get(self::KEY_REMATCH_COOLDOWN, 5)));
    }

    public static function newPlayerMaxDuels(): int
    {
        return max(0, min(50, (int) GeneralSetting::get(self::KEY_NEW_PLAYER_DUELS, 5)));
    }

    public static function skillSampleAnswers(): int
    {
        return max(5, min(100, (int) GeneralSetting::get(self::KEY_SKILL_SAMPLE, 25)));
    }

    /**
     * @return array{
     *   rematch_cooldown_seconds:int,
     *   new_player_max_duels:int,
     *   skill_sample_answers:int,
     *   soft_cap_streak:int,
     *   soft_cap_extra_seconds:int,
     *   soft_cap_wait_bump:int,
     *   bands:list<array{max_pct:int,tiers:list<string>,label:string}>
     * }
     */
    public static function matchmakingSettings(): array
    {
        return Cache::remember(self::CACHE_MM_SETTINGS, 10, static function () {
            return [
                'rematch_cooldown_seconds' => self::rematchCooldownSeconds(),
                'new_player_max_duels' => self::newPlayerMaxDuels(),
                'skill_sample_answers' => self::skillSampleAnswers(),
                'soft_cap_streak' => self::softCapStreak(),
                'soft_cap_extra_seconds' => self::softCapExtraSeconds(),
                'soft_cap_wait_bump' => self::softCapWaitBump(),
                'bands' => self::matchBands(),
            ];
        });
    }

    public static function softCapStreak(): int
    {
        return max(0, min(20, (int) GeneralSetting::get(self::KEY_SOFT_CAP_STREAK, 3)));
    }

    public static function softCapExtraSeconds(): int
    {
        return max(0, min(300, (int) GeneralSetting::get(self::KEY_SOFT_CAP_EXTRA, 10)));
    }

    /** Soft cap varken kuyruk beklemesine ek sn (socket wait) */
    public static function softCapWaitBump(): int
    {
        return max(0, min(30, (int) GeneralSetting::get(self::KEY_SOFT_CAP_WAIT_BUMP, 2)));
    }

    public static function saveMatchmaking(array $data): array
    {
        $cooldown = max(0, min(120, (int) ($data['rematch_cooldown_seconds'] ?? 5)));
        $newPlayer = max(0, min(50, (int) ($data['new_player_max_duels'] ?? 5)));
        $sample = max(5, min(100, (int) ($data['skill_sample_answers'] ?? 25)));
        $softStreak = max(0, min(20, (int) ($data['soft_cap_streak'] ?? 3)));
        $softExtra = max(0, min(300, (int) ($data['soft_cap_extra_seconds'] ?? 10)));
        $softWait = max(0, min(30, (int) ($data['soft_cap_wait_bump'] ?? 2)));

        $bandsIn = $data['bands'] ?? null;
        if (!is_array($bandsIn) || $bandsIn === []) {
            $bandsIn = self::defaultMatchBands();
        }
        $bands = [];
        foreach ($bandsIn as $i => $row) {
            $tiers = [];
            foreach ((array) ($row['tiers'] ?? []) as $t) {
                $t = BotAnswerEngine::normalizeTier((string) $t);
                if (!in_array($t, $tiers, true)) {
                    $tiers[] = $t;
                }
            }
            if ($tiers === []) {
                $tiers = ['medium'];
            }
            $bands[] = [
                'max_pct' => max(1, min(100, (int) ($row['max_pct'] ?? 100))),
                'tiers' => $tiers,
                'label' => (string) ($row['label'] ?? ('Band ' . ($i + 1))),
            ];
        }
        usort($bands, static fn ($a, $b) => $a['max_pct'] <=> $b['max_pct']);

        GeneralSetting::set(self::KEY_REMATCH_COOLDOWN, (string) $cooldown, 'number', 'Bot rematch cooldown (sn)');
        GeneralSetting::set(self::KEY_NEW_PLAYER_DUELS, (string) $newPlayer, 'number', 'Yeni oyuncu: bu kadar düelloya kadar medium');
        GeneralSetting::set(self::KEY_SKILL_SAMPLE, (string) $sample, 'number', 'İsabet için son N cevap');
        GeneralSetting::set(self::KEY_SOFT_CAP_STREAK, (string) $softStreak, 'number', 'Peş peşe bot maçı soft cap eşiği');
        GeneralSetting::set(self::KEY_SOFT_CAP_EXTRA, (string) $softExtra, 'number', 'Soft cap ek cooldown (sn)');
        GeneralSetting::set(self::KEY_SOFT_CAP_WAIT_BUMP, (string) $softWait, 'number', 'Soft cap kuyruk bekleme +sn');
        GeneralSetting::set(
            self::KEY_MATCH_BANDS,
            json_encode($bands, JSON_UNESCAPED_UNICODE),
            'json',
            'İsabet bantları → bot zorlukları'
        );

        self::log(
            "MATCHMAKING · cooldown={$cooldown}s · yeni≤{$newPlayer} · sample={$sample}"
            . " · soft≥{$softStreak}/+{$softExtra}s/wait+{$softWait} · bant×" . count($bands)
        );

        self::forgetPerfCaches();

        return self::matchmakingSettings();
    }

    /**
     * Oyuncu isabet bandı + tercih edilen bot zorlukları.
     *
     * @return array{band:string,accuracy_pct:?int,duel_count:int,sample:int,tiers:list<string>}
     */
    public static function humanSkillSnapshot(int $userId): array
    {
        $duelCount = (int) Duel::query()
            ->where('status', 'finished')
            ->where(function ($q) use ($userId) {
                $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
            })
            ->count();

        $newMax = self::newPlayerMaxDuels();
        if ($duelCount < $newMax) {
            return [
                'band' => 'new',
                'accuracy_pct' => null,
                'duel_count' => $duelCount,
                'sample' => 0,
                'tiers' => ['medium'],
            ];
        }

        $limit = self::skillSampleAnswers();
        $answers = \App\Models\DuelAnswer::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['is_correct']);

        $sample = $answers->count();
        if ($sample < 5) {
            return [
                'band' => 'default',
                'accuracy_pct' => null,
                'duel_count' => $duelCount,
                'sample' => $sample,
                'tiers' => ['medium'],
            ];
        }

        $correct = $answers->where('is_correct', true)->count();
        $pct = (int) round(100 * $correct / $sample);
        $tiers = self::tiersForAccuracyPct($pct);
        $bandLabel = 'band';
        foreach (self::matchBands() as $b) {
            if ($pct <= (int) $b['max_pct']) {
                $bandLabel = $b['label'] !== '' ? $b['label'] : ('≤' . $b['max_pct'] . '%');
                break;
            }
        }

        return [
            'band' => $bandLabel,
            'accuracy_pct' => $pct,
            'duel_count' => $duelCount,
            'sample' => $sample,
            'tiers' => $tiers,
        ];
    }

    /** @return list<string> */
    public static function tiersForAccuracyPct(int $pct): array
    {
        $pct = max(0, min(100, $pct));
        foreach (self::matchBands() as $band) {
            if ($pct <= (int) $band['max_pct']) {
                return array_values($band['tiers']);
            }
        }

        return ['medium'];
    }

    /** Son bot maçından beri rematch cooldown içinde mi? */
    public static function isHumanInBotRematchCooldown(int $userId): bool
    {
        return (bool) (self::rematchCooldownStatus($userId)['active'] ?? false);
    }

    /**
     * Peş peşe bitmiş bot maçı sayısı (en yeniden geriye; insan-insan kırar).
     * Soft-cap için: AFK / disconnect / timeout / requeue bitişleri sayılmaz (streak kırılır).
     */
    public static function consecutiveBotMatchStreak(int $userId): int
    {
        $botIds = self::allBotUserIds();
        if ($botIds === [] || $userId <= 0) {
            return 0;
        }

        $recent = Duel::query()
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->where(function ($q) use ($userId) {
                $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
            })
            ->orderByDesc('finished_at')
            ->limit(20)
            ->get(['challenger_id', 'opponent_id', 'settings']);

        $skipReasons = ['afk_streak', 'disconnect', 'answer_timeout', 'requeue'];
        $streak = 0;
        foreach ($recent as $duel) {
            $c = (int) $duel->challenger_id;
            $o = (int) ($duel->opponent_id ?? 0);
            $vsBot = in_array($c, $botIds, true) || in_array($o, $botIds, true);
            if (!$vsBot) {
                break;
            }
            $reason = (string) (($duel->settings ?? [])['forfeit_reason'] ?? '');
            if (in_array($reason, $skipReasons, true)) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * @return array{
     *   active:bool,
     *   base_seconds:int,
     *   effective_seconds:int,
     *   remaining_seconds:int,
     *   soft_cap:bool,
     *   streak:int,
     *   wait_bump:int,
     *   last_finished_at:?string
     * }
     */
    public static function rematchCooldownStatus(int $userId): array
    {
        $base = self::rematchCooldownSeconds();
        $streak = self::consecutiveBotMatchStreak($userId);
        $threshold = self::softCapStreak();
        $softCap = $threshold > 0 && $streak >= $threshold;
        $effective = $base;
        if ($softCap) {
            $effective = $base + self::softCapExtraSeconds();
        }
        $waitBump = $softCap ? self::softCapWaitBump() : 0;

        $empty = [
            'active' => false,
            'base_seconds' => $base,
            'effective_seconds' => $effective,
            'remaining_seconds' => 0,
            'soft_cap' => $softCap,
            'streak' => $streak,
            'wait_bump' => $waitBump,
            'last_finished_at' => null,
        ];

        if ($effective <= 0) {
            return $empty;
        }

        $botIds = self::allBotUserIds();
        if ($botIds === []) {
            return $empty;
        }

        $last = Duel::query()
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->where(function ($q) use ($userId) {
                $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
            })
            ->where(function ($q) use ($botIds) {
                $q->whereIn('challenger_id', $botIds)->orWhereIn('opponent_id', $botIds);
            })
            ->orderByDesc('finished_at')
            ->first();

        if (!$last || !$last->finished_at) {
            return $empty;
        }

        // AFK / app kapat / tekrar kuyruk: ekstra bekleme yok — meydan oku hemen açılsın
        $lastReason = (string) (($last->settings ?? [])['forfeit_reason'] ?? '');
        if (in_array($lastReason, ['requeue', 'afk_streak', 'disconnect', 'answer_timeout'], true)) {
            return array_merge($empty, [
                'last_finished_at' => $last->finished_at->toDateTimeString(),
                'streak' => $streak,
            ]);
        }

        $elapsed = $last->finished_at->diffInSeconds(now());
        $remaining = max(0, $effective - $elapsed);

        return [
            'active' => $remaining > 0,
            'base_seconds' => $base,
            'effective_seconds' => $effective,
            'remaining_seconds' => $remaining,
            'soft_cap' => $softCap,
            'streak' => $streak,
            'wait_bump' => $waitBump,
            'last_finished_at' => $last->finished_at->toDateTimeString(),
        ];
    }

    public static function picksLogPath(): string
    {
        return storage_path('logs/duel-bot-picks.json');
    }

    /**
     * Admin panel: son pick özetleri.
     *
     * @param  array<string,mixed>  $row
     */
    public static function recordPickInsight(array $row): void
    {
        try {
            $path = self::picksLogPath();
            $dir = dirname($path);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $list = [];
            if (File::exists($path)) {
                $decoded = json_decode((string) File::get($path), true);
                if (is_array($decoded)) {
                    $list = $decoded;
                }
            }
            $row['at'] = $row['at'] ?? now()->toDateTimeString();
            array_unshift($list, $row);
            $list = array_slice($list, 0, 40);
            File::put($path, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            @chmod($path, 0664);
        } catch (\Throwable $e) {
            \Log::warning('DuelBotSettings::recordPickInsight failed', ['error' => $e->getMessage()]);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function recentPickInsights(int $limit = 15): array
    {
        $path = self::picksLogPath();
        if (!File::exists($path)) {
            return [];
        }
        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_slice($decoded, 0, max(1, min(40, $limit))));
    }

    /**
     * Son N saat insan-insan vs bot maçı oranı.
     *
     * @return array{hours:int,human:int,bot:int,total:int,bot_pct:?int,human_pct:?int}
     */
    public static function matchMixStats(int $hours = 24): array
    {
        $hours = max(1, min(168, $hours));
        $cacheKey = $hours === 24 ? self::CACHE_MATCH_MIX : ('duel:match_mix_' . $hours);

        return Cache::remember($cacheKey, 30, static function () use ($hours) {
            $since = now()->subHours($hours);
            $botIds = self::allBotUserIds();

            $q = Duel::query()
                ->where('status', 'finished')
                ->whereNotNull('finished_at')
                ->where('finished_at', '>=', $since)
                ->whereNotNull('opponent_id');

            $total = (clone $q)->count();
            $bot = 0;
            if ($botIds !== []) {
                $bot = (clone $q)->where(function ($qq) use ($botIds) {
                    $qq->whereIn('challenger_id', $botIds)->orWhereIn('opponent_id', $botIds);
                })->count();
            }
            $human = max(0, $total - $bot);

            return [
                'hours' => $hours,
                'human' => $human,
                'bot' => $bot,
                'total' => $total,
                'bot_pct' => $total > 0 ? (int) round(100 * $bot / $total) : null,
                'human_pct' => $total > 0 ? (int) round(100 * $human / $total) : null,
            ];
        });
    }

    /**
     * Admin dashboard özeti (canlı poll).
     *
     * @return array{match_mix:array,recent_picks:list<array>,tier_coverage:array,ops_stats:array}
     */
    public static function adminMatchmakingDashboard(): array
    {
        return Cache::remember(self::CACHE_ADMIN_MM, 30, static function () {
            return [
                'match_mix' => self::matchMixStats(24),
                'recent_picks' => self::recentPickInsights(12),
                'tier_coverage' => self::tierCoverage(),
                'ops_stats' => self::opsStats24h(),
            ];
        });
    }

    /**
     * Tier başına aktif / boşta bot (yedek ihtiyacı).
     *
     * @return array{tiers:list<array<string,mixed>>,tips:list<string>}
     */
    public static function tierCoverage(): array
    {
        $busyIds = [];
        foreach (self::allBotUserIds() as $uid) {
            if (self::isBotBusy($uid)) {
                $busyIds[$uid] = true;
            }
        }

        $byTier = [];
        foreach (self::DIFFICULTIES as $tier) {
            $byTier[$tier] = ['tier' => $tier, 'active' => 0, 'idle' => 0, 'total' => 0];
        }

        foreach (self::bots() as $bot) {
            $tier = BotAnswerEngine::normalizeTier((string) ($bot['difficulty'] ?? 'medium'));
            if (!isset($byTier[$tier])) {
                $byTier[$tier] = ['tier' => $tier, 'active' => 0, 'idle' => 0, 'total' => 0];
            }
            $byTier[$tier]['total']++;
            if (empty($bot['is_active'])) {
                continue;
            }
            $uid = (int) $bot['user_id'];
            $byTier[$tier]['active']++;
            if (empty($busyIds[$uid])) {
                $byTier[$tier]['idle']++;
            }
        }

        $tips = [];
        $tierLabels = [
            'easy' => 'Kolay',
            'medium' => 'Orta',
            'hard' => 'Zor',
            'professor' => 'Terminatör',
        ];
        foreach ($byTier as $row) {
            $tierName = $tierLabels[$row['tier']] ?? strtoupper((string) $row['tier']);
            if ($row['active'] === 1) {
                $tips[] = $tierName . ' seviyesinde tek aktif bot — pasiften 2. bot aç (yedek)';
            } elseif ($row['active'] === 0 && $row['total'] > 0) {
                $tips[] = $tierName . ' seviyesinde aktif bot yok';
            }
        }

        return [
            'tiers' => array_values($byTier),
            'tips' => $tips,
        ];
    }

    /**
     * Son 24s forfeit + worker restart özeti.
     *
     * @return array<string,mixed>
     */
    public static function opsStats24h(): array
    {
        $since = now()->subHours(24);
        $reasons = [
            'answer_timeout' => 0,
            'disconnect' => 0,
            'afk_streak' => 0,
            'leave' => 0,
            'requeue' => 0,
            'other' => 0,
        ];

        $rows = Duel::query()
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', $since)
            ->limit(2000)
            ->get(['settings']);

        $forfeitTotal = 0;
        foreach ($rows as $row) {
            $reason = (string) (($row->settings ?? [])['forfeit_reason'] ?? '');
            if ($reason === '') {
                continue;
            }
            $forfeitTotal++;
            if (isset($reasons[$reason])) {
                $reasons[$reason]++;
            } else {
                $reasons['other']++;
            }
        }

        return [
            'hours' => 24,
            'forfeit_total' => $forfeitTotal,
            'forfeit_reasons' => $reasons,
            'worker_restarts' => self::countWorkerStartsInLog(24),
        ];
    }

    /** duel-bot.log içinde son N saatte "Worker başladı" sayısı */
    public static function countWorkerStartsInLog(int $hours = 24): int
    {
        $path = self::logPath();
        if (!File::exists($path)) {
            return 0;
        }

        $cutoff = now()->subHours(max(1, $hours));
        $lines = self::recentLogs(400, null);
        $n = 0;
        foreach ($lines as $line) {
            if (!str_contains((string) $line, 'Worker başladı')) {
                continue;
            }
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', (string) $line, $m)) {
                try {
                    if (\Carbon\Carbon::parse($m[1])->lt($cutoff)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    // ignore parse
                }
            }
            $n++;
        }

        return $n;
    }

    /**
     * Tercih edilen tier listesini komşu bantlarla genişlet (yedek).
     *
     * @param  list<string>  $preferred
     * @return list<string>
     */
    public static function expandTiersWithNeighbors(array $preferred): array
    {
        $order = self::DIFFICULTIES;
        $out = [];
        foreach ($preferred as $t) {
            $t = BotAnswerEngine::normalizeTier((string) $t);
            if (!in_array($t, $out, true)) {
                $out[] = $t;
            }
            $idx = array_search($t, $order, true);
            if ($idx === false) {
                continue;
            }
            foreach ([(int) $idx - 1, (int) $idx + 1] as $ni) {
                if (isset($order[$ni]) && !in_array($order[$ni], $out, true)) {
                    $out[] = $order[$ni];
                }
            }
        }

        return $out;
    }

    /**
     * Eşleşme için boştaki aktif bot.
     * $preferredTiers verilirse önce o banda göre; yoksa eski genel sıra (medium→hard→easy→professor).
     *
     * @param  list<string>|null  $preferredTiers
     */
    public static function pickIdleBot(?int $excludeUserId = null, ?array $preferredTiers = null): ?array
    {
        $legacyPriority = ['medium' => 1, 'hard' => 2, 'easy' => 3, 'professor' => 4];
        $candidates = [];

        foreach (self::bots() as $bot) {
            if (empty($bot['is_active'])) {
                continue;
            }
            $uid = (int) $bot['user_id'];
            if ($excludeUserId && $uid === $excludeUserId) {
                continue;
            }
            $user = User::query()->find($uid);
            if (!$user || (int) $user->coins <= 0 || $user->trashed()) {
                continue;
            }
            if (self::isBotBusy($uid)) {
                continue;
            }
            $candidates[] = $bot;
        }

        if ($candidates === []) {
            return null;
        }

        $pickFromTierList = static function (array $pool, array $tierOrder) {
            foreach ($tierOrder as $tier) {
                $tier = BotAnswerEngine::normalizeTier((string) $tier);
                $group = array_values(array_filter(
                    $pool,
                    static fn ($b) => ($b['difficulty'] ?? '') === $tier
                ));
                if ($group !== []) {
                    return $group[array_rand($group)];
                }
            }

            return null;
        };

        if ($preferredTiers !== null && $preferredTiers !== []) {
            $picked = $pickFromTierList($candidates, $preferredTiers);
            if ($picked) {
                return $picked;
            }
            $expanded = self::expandTiersWithNeighbors($preferredTiers);
            $picked = $pickFromTierList($candidates, $expanded);
            if ($picked) {
                return $picked;
            }
        }

        usort($candidates, function ($a, $b) use ($legacyPriority) {
            $pa = $legacyPriority[$a['difficulty']] ?? 99;
            $pb = $legacyPriority[$b['difficulty']] ?? 99;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return random_int(-1, 1);
        });

        $best = $legacyPriority[$candidates[0]['difficulty']] ?? 99;
        $top = array_values(array_filter(
            $candidates,
            fn ($c) => ($legacyPriority[$c['difficulty']] ?? 99) === $best
        ));

        return $top[array_rand($top)];
    }

    /**
     * İnsan için skill-aware bot seçimi (cooldown ayrı kontrol edilir).
     *
     * @return array{bot:array,skill:array}|null
     */
    public static function pickIdleBotForHuman(int $challengerId): ?array
    {
        $skill = self::humanSkillSnapshot($challengerId);
        $bot = self::pickIdleBot($challengerId, $skill['tiers']);
        if (!$bot) {
            return null;
        }

        return ['bot' => $bot, 'skill' => $skill];
    }

    /** Havuzda en az 1 aktif bot var mı? (idle olmasa bile matchmaking kapısı) */
    public static function anyBotActiveInPool(): bool
    {
        foreach (self::bots() as $bot) {
            if (!empty($bot['is_active'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Admin: tek düello soru detayı.
     *
     * @return array<string,mixed>|null
     */
    public static function botDuelDetail(int $botUserId, int $duelId): ?array
    {
        $duel = Duel::query()
            ->with(['challenger:id,name,coins,duel_earned_coins', 'opponent:id,name,coins,duel_earned_coins', 'answers.question'])
            ->find($duelId);

        if (!$duel) {
            return null;
        }
        if ((int) $duel->challenger_id !== $botUserId && (int) $duel->opponent_id !== $botUserId) {
            return null;
        }

        $humanId = (int) $duel->challenger_id === $botUserId
            ? (int) $duel->opponent_id
            : (int) $duel->challenger_id;

        $byQuestion = [];
        foreach ($duel->answers->sortBy('id') as $ans) {
            $qid = (int) $ans->question_id;
            if (!isset($byQuestion[$qid])) {
                $qv = max(1, (int) ($ans->question_value ?? 0));
                if ((int) ($ans->question_value ?? 0) <= 0 && (int) $ans->coins_change !== 0) {
                    $qv = max(1, abs((int) $ans->coins_change));
                }
                $byQuestion[$qid] = [
                    'question_id' => $qid,
                    'question' => null,
                    'correct_answer' => null,
                    'multiplier' => $qv,
                    'multiplier_label' => 'x' . $qv,
                    'bot' => null,
                    'human' => null,
                    'first_id' => (int) $ans->id,
                ];
            } else {
                // Eksik/0 ise diğer cevaptan tamamla
                $qv = max(1, (int) ($ans->question_value ?? 0));
                if ($qv > (int) ($byQuestion[$qid]['multiplier'] ?? 0)) {
                    $byQuestion[$qid]['multiplier'] = $qv;
                    $byQuestion[$qid]['multiplier_label'] = 'x' . $qv;
                }
            }
            $q = $ans->question;
            if ($byQuestion[$qid]['question'] === null) {
                if ($q && ! $q->trashed()) {
                    $byQuestion[$qid]['question'] = method_exists($q, 'getTranslation')
                        ? (string) $q->getTranslation('question', 'tr')
                        : (string) ($q->question ?? '');
                    $byQuestion[$qid]['correct_answer'] = (string) ($q->correct_answer ?? '');
                    $byQuestion[$qid]['question_deleted'] = false;
                } else {
                    $byQuestion[$qid]['question'] = Question::DELETED_LABEL_TR;
                    $byQuestion[$qid]['question_deleted'] = true;
                }
            }
            $row = [
                'selected' => (string) $ans->selected_answer,
                'is_correct' => (bool) $ans->is_correct,
                'coins_change' => (int) $ans->coins_change,
                'question_value' => (int) ($ans->question_value ?? 0),
                'answered_at' => optional($ans->answered_at)->format('H:i:s'),
            ];
            if ((int) $ans->user_id === $botUserId) {
                $byQuestion[$qid]['bot'] = $row;
            } elseif ((int) $ans->user_id === $humanId) {
                $byQuestion[$qid]['human'] = $row;
            }
        }

        uasort($byQuestion, static fn ($a, $b) => $a['first_id'] <=> $b['first_id']);
        $questions = [];
        $n = 0;
        foreach ($byQuestion as $row) {
            $n++;
            unset($row['first_id']);
            $row['n'] = $n;
            $questions[] = $row;
        }

        $opp = (int) $duel->challenger_id === $botUserId ? $duel->opponent : $duel->challenger;
        $botIsChallenger = (int) $duel->challenger_id === $botUserId;

        $sumSide = static function (int $uid) use ($duel): array {
            $rows = $duel->answers->where('user_id', $uid);
            $gained = (int) $rows->filter(fn ($a) => (int) $a->coins_change > 0)->sum('coins_change');
            $lost = (int) abs($rows->filter(fn ($a) => (int) $a->coins_change < 0)->sum('coins_change'));

            return [
                'correct' => (int) $rows->where('is_correct', true)->count(),
                'wrong' => (int) $rows->where('is_correct', false)->count(),
                'answered' => (int) $rows->count(),
                'coins_gained' => $gained,
                'coins_lost' => $lost,
                'coins_net' => $gained - $lost,
            ];
        };

        $botStats = $sumSide($botUserId);
        $botStats['coins_before'] = $botIsChallenger
            ? $duel->challenger_coins_before
            : $duel->opponent_coins_before;
        $botStats['coins_after'] = $botIsChallenger
            ? $duel->challenger_coins_after
            : $duel->opponent_coins_after;

        $humanStats = $sumSide($humanId);
        $humanStats['coins_before'] = $botIsChallenger
            ? $duel->opponent_coins_before
            : $duel->challenger_coins_before;
        $humanStats['coins_after'] = $botIsChallenger
            ? $duel->opponent_coins_after
            : $duel->challenger_coins_after;
        $humanStats['coins_now'] = $opp ? (int) $opp->coins : null;
        $humanStats['duel_earned_coins'] = $opp ? (int) ($opp->duel_earned_coins ?? 0) : null;

        return [
            'duel_id' => $duel->id,
            'status' => $duel->status,
            'multiplier' => $duel->multiplier,
            'winner_id' => $duel->winner_id,
            'forfeit_reason' => ($duel->settings ?? [])['forfeit_reason'] ?? null,
            'opponent_id' => $opp?->id,
            'opponent_name' => $opp?->name,
            'finished_at' => optional($duel->finished_at)->format('Y-m-d H:i:s'),
            'bot_stats' => $botStats,
            'opponent_stats' => $humanStats,
            'questions' => $questions,
        ];
    }

    /**
     * Admin sol panel kart listesi.
     *
     * @return list<array{id:string|int,user_id:?int,name:string,subtitle:string,avatar_url:?string,is_active:bool,difficulty:?string,is_dummy:bool,coins:?int,busy?:bool}>
     */
    public static function catalog(): array
    {
        $items = [];

        foreach (self::bots() as $botCfg) {
            $user = User::query()->find($botCfg['user_id']);
            if (!$user) {
                continue;
            }
            $busy = self::isBotBusy((int) $user->id);
            $tier = BotAnswerEngine::tierMeta($botCfg['difficulty']);
            $ex8 = BotAnswerEngine::discreteExamples($botCfg['difficulty'], 8)[0] ?? null;
            $items[] = [
                'id' => (string) $user->id,
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'subtitle' => '#' . $user->id . ' · ' . ($tier['label'] ?? $botCfg['difficulty'])
                    . ($ex8 ? " · ~{$ex8['correct']}/8 (%{$ex8['pct']})" : ''),
                'avatar_url' => $user->resolveAvatarUrl(),
                'is_active' => (bool) $botCfg['is_active'],
                'difficulty' => $botCfg['difficulty'],
                'is_dummy' => false,
                'coins' => (int) $user->coins,
                'busy' => $busy,
            ];
        }

        $tierOrder = ['easy' => 1, 'medium' => 2, 'hard' => 3, 'professor' => 4];
        usort($items, static function ($a, $b) use ($tierOrder) {
            $pa = $tierOrder[$a['difficulty'] ?? ''] ?? 99;
            $pb = $tierOrder[$b['difficulty'] ?? ''] ?? 99;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ((int) ($a['user_id'] ?? 0)) <=> ((int) ($b['user_id'] ?? 0));
        });

        return $items;
    }

    /**
     * Admin canlı durum: maçta mı, rakip, isabet.
     *
     * @return list<array<string,mixed>>
     */
    public static function liveSnapshots(): array
    {
        return Cache::remember(self::CACHE_LIVE, 3, static function () {
            $out = [];

            foreach (self::bots() as $botCfg) {
                $uid = (int) $botCfg['user_id'];
                $user = User::query()->find($uid);
                if (!$user) {
                    continue;
                }

                $duel = Duel::query()
                    ->whereIn('status', ['waiting', 'active'])
                    ->where(function ($q) use ($uid) {
                        $q->where('challenger_id', $uid)->orWhere('opponent_id', $uid);
                    })
                    ->with(['challenger', 'opponent'])
                    ->orderByDesc('id')
                    ->first();

                $row = [
                    'user_id' => $uid,
                    'name' => (string) $user->name,
                    'difficulty' => $botCfg['difficulty'],
                    'is_active' => (bool) $botCfg['is_active'],
                    'coins' => (int) $user->coins,
                    'busy' => $duel !== null,
                    'duel_id' => $duel?->id,
                    'question_number' => $duel ? (int) $duel->current_question_number : null,
                    'multiplier' => $duel?->multiplier,
                    'opponent_id' => null,
                    'opponent_name' => null,
                    'correct' => 0,
                    'answered' => 0,
                    'accuracy_pct' => null,
                    'pending_bet' => null,
                ];

                if ($duel) {
                    $opp = (int) $duel->challenger_id === $uid ? $duel->opponent : $duel->challenger;
                    $row['opponent_id'] = $opp?->id;
                    $row['opponent_name'] = $opp?->name;

                    $answers = \App\Models\DuelAnswer::query()
                        ->where('duel_id', $duel->id)
                        ->where('user_id', $uid)
                        ->get(['is_correct']);
                    $row['answered'] = $answers->count();
                    $row['correct'] = $answers->where('is_correct', true)->count();
                    $row['accuracy_pct'] = $row['answered'] > 0
                        ? (int) round(100 * $row['correct'] / $row['answered'])
                        : null;

                    $bet = $duel->settings['current_bet'] ?? null;
                    if ($bet && ($bet['status'] ?? null) === 'pending') {
                        $row['pending_bet'] = (int) ($bet['multiplier'] ?? 0) . 'x';
                    }
                }

                $out[] = $row;
            }

            $tierOrder = ['easy' => 1, 'medium' => 2, 'hard' => 3, 'professor' => 4];
            usort($out, static function ($a, $b) use ($tierOrder) {
                $pa = $tierOrder[$a['difficulty'] ?? ''] ?? 99;
                $pb = $tierOrder[$b['difficulty'] ?? ''] ?? 99;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return ((int) ($a['user_id'] ?? 0)) <=> ((int) ($b['user_id'] ?? 0));
            });

            return $out;
        });
    }

    /**
     * Botun düello geçmişi (admin modal).
     *
     * @return array{items: list<array<string,mixed>>, pagination: array{total:int,per_page:int,current_page:int,last_page:int}}
     */
    public static function botDuelHistory(int $botUserId, int $perPage = 50, int $page = 1): array
    {
        $perPage = max(10, min(100, $perPage));
        $page = max(1, $page);

        $base = Duel::query()
            ->where(function ($q) use ($botUserId) {
                $q->where('challenger_id', $botUserId)->orWhere('opponent_id', $botUserId);
            });

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $duels = (clone $base)
            ->with(['challenger:id,name', 'opponent:id,name', 'winner:id,name'])
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        if ($duels->isEmpty()) {
            return [
                'items' => [],
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                ],
            ];
        }

        $duelIds = $duels->pluck('id')->all();
        $stats = \App\Models\DuelAnswer::query()
            ->whereIn('duel_id', $duelIds)
            ->where('user_id', $botUserId)
            ->selectRaw('duel_id, COUNT(*) as answered, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct, SUM(CASE WHEN coins_change > 0 THEN coins_change ELSE 0 END) as gained, SUM(CASE WHEN coins_change < 0 THEN ABS(coins_change) ELSE 0 END) as lost, SUM(coins_change) as net')
            ->groupBy('duel_id')
            ->get()
            ->keyBy('duel_id');

        $rows = [];
        foreach ($duels as $duel) {
            $isChallenger = (int) $duel->challenger_id === $botUserId;
            $opp = $isChallenger ? $duel->opponent : $duel->challenger;
            $before = $isChallenger ? (int) $duel->challenger_coins_before : (int) $duel->opponent_coins_before;
            $after = $isChallenger ? (int) $duel->challenger_coins_after : (int) $duel->opponent_coins_after;
            $st = $stats->get($duel->id);
            $answered = (int) ($st->answered ?? 0);
            $correct = (int) ($st->correct ?? 0);
            $wrong = max(0, $answered - $correct);
            $gained = (int) ($st->gained ?? 0);
            $lost = (int) ($st->lost ?? 0);
            $netAnswers = (int) ($st->net ?? 0);
            $forfeitReason = (string) (($duel->settings ?? [])['forfeit_reason'] ?? '');

            $result = 'devam';
            if ($duel->status === 'finished') {
                if (!$duel->winner_id) {
                    // Gerçek berabere yok; winner=null = iptal / eski cleanup
                    $result = match ($forfeitReason) {
                        'answer_timeout' => 'timeout',
                        'disconnect' => 'disconnect',
                        'leave' => 'leave',
                        'afk_streak' => 'afk',
                        'requeue' => 'requeue',
                        default => 'iptal',
                    };
                } elseif ((int) $duel->winner_id === $botUserId) {
                    $result = match ($forfeitReason) {
                        'answer_timeout', 'disconnect', 'leave', 'afk_streak', 'requeue' => 'galibiyet_' . (
                            $forfeitReason === 'answer_timeout' ? 'timeout'
                                : ($forfeitReason === 'afk_streak' ? 'afk'
                                : ($forfeitReason === 'requeue' ? 'requeue' : $forfeitReason))
                        ),
                        default => 'galibiyet',
                    };
                } else {
                    $result = match ($forfeitReason) {
                        'answer_timeout' => 'maglubiyet_timeout',
                        'disconnect' => 'maglubiyet_disconnect',
                        'leave' => 'maglubiyet_leave',
                        'afk_streak' => 'maglubiyet_afk',
                        default => 'mağlubiyet',
                    };
                }
            }

            // Eski bug: winner yok + coins_after=0 yazılmamış → net/bakiye gösterme
            $snapshotOk = true;
            if ($duel->status === 'finished' && !$duel->winner_id && $forfeitReason === '' && $after === 0 && $before > 0) {
                $snapshotOk = false;
            }
            $netBalance = ($duel->status === 'finished' && $snapshotOk) ? ($after - $before) : null;
            $displayAfter = $snapshotOk ? $after : null;

            $rows[] = [
                'duel_id' => $duel->id,
                'status' => $duel->status,
                'result' => $result,
                'forfeit_reason' => $forfeitReason !== '' ? $forfeitReason : null,
                'multiplier' => $duel->multiplier,
                'opponent_id' => $opp?->id,
                'opponent_name' => $opp?->name,
                'winner_id' => $duel->winner_id,
                'answered' => $answered,
                'correct' => $correct,
                'wrong' => $wrong,
                'accuracy_pct' => $answered > 0 ? (int) round(100 * $correct / $answered) : null,
                'coins_gained' => $gained,
                'coins_lost' => $lost,
                'coins_net_answers' => $netAnswers,
                'coins_before' => $before,
                'coins_after' => $displayAfter,
                'coins_net' => $netBalance,
                'started_at' => optional($duel->started_at)->format('Y-m-d H:i:s'),
                'finished_at' => optional($duel->finished_at)->format('Y-m-d H:i:s'),
                'created_at' => optional($duel->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'items' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    public static function save(array $data): array
    {
        $defaults = self::defaults();
        $userId = (int) ($data['user_id'] ?? $defaults['user_id']);
        $isActive = !empty($data['is_active']);
        $difficulty = BotAnswerEngine::normalizeTier((string) ($data['difficulty'] ?? $defaults['difficulty']));
        $wait = max(1, min(30, (int) ($data['match_wait_seconds'] ?? self::matchWaitSeconds())));

        $bots = self::bots();
        $found = false;
        foreach ($bots as &$bot) {
            if ((int) $bot['user_id'] === $userId) {
                $bot['is_active'] = $isActive;
                $bot['difficulty'] = $difficulty;
                $found = true;
                break;
            }
        }
        unset($bot);

        if (!$found) {
            $bots[] = [
                'user_id' => $userId,
                'difficulty' => $difficulty,
                'is_active' => $isActive,
            ];
        }

        self::persistBots($bots);

        // Geriye uyumluluk (eski tek-bot key'leri — son kaydedilen)
        GeneralSetting::set(self::KEY_USER_ID, (string) $userId, 'number', 'Düello bot kullanıcı ID (legacy)');
        GeneralSetting::set(self::KEY_ACTIVE, $isActive ? '1' : '0', 'boolean', 'Düello bot aktif (legacy)');
        GeneralSetting::set(self::KEY_DIFFICULTY, $difficulty, 'text', 'Düello bot zorluk (legacy)');
        GeneralSetting::set(self::KEY_MATCH_WAIT_SECONDS, (string) $wait, 'number', 'Bot eşleşme bekleme süresi (sn)');

        self::log($isActive
            ? "Ayarlar: AKTİF · #{$userId} · {$difficulty} · bekleme={$wait}s"
            : "Ayarlar: PASİF · #{$userId} · {$difficulty} · bekleme={$wait}s"
        );

        return self::all($userId);
    }

    /** Botu havuza ekle / güncelle (kurulum) */
    public static function registerBot(int $userId, string $difficulty, bool $isActive = true): void
    {
        $bots = self::bots();
        $found = false;
        foreach ($bots as &$bot) {
            if ((int) $bot['user_id'] === $userId) {
                $bot['difficulty'] = BotAnswerEngine::normalizeTier($difficulty);
                $bot['is_active'] = $isActive;
                $found = true;
                break;
            }
        }
        unset($bot);

        if (!$found) {
            $bots[] = [
                'user_id' => $userId,
                'difficulty' => BotAnswerEngine::normalizeTier($difficulty),
                'is_active' => $isActive,
            ];
        }

        self::persistBots($bots);
    }

    /**
     * Belirli zorluktaki tüm botları toplu aktif/pasif yap.
     *
     * @return array{updated:int,difficulty:string,is_active:bool}
     */
    public static function bulkSetActiveByDifficulty(string $difficulty, bool $isActive): array
    {
        $difficulty = BotAnswerEngine::normalizeTier($difficulty);
        $bots = self::bots();
        $updated = 0;

        foreach ($bots as &$bot) {
            if (BotAnswerEngine::normalizeTier((string) ($bot['difficulty'] ?? 'medium')) !== $difficulty) {
                continue;
            }
            if ((bool) ($bot['is_active'] ?? false) === $isActive) {
                continue;
            }
            $bot['is_active'] = $isActive;
            $updated++;
        }
        unset($bot);

        if ($updated > 0) {
            self::persistBots($bots);
            $label = match ($difficulty) {
                'easy' => 'Kolay',
                'medium' => 'Orta',
                'hard' => 'Zor',
                'professor' => 'Terminatör',
                default => $difficulty,
            };
            self::log(
                ($isActive ? 'TOPLU AÇ' : 'TOPLU KAPAT')
                . " · {$label} · {$updated} bot"
            );
        }

        return [
            'updated' => $updated,
            'difficulty' => $difficulty,
            'is_active' => $isActive,
        ];
    }

    public static function logPath(): string
    {
        return storage_path('logs/duel-bot.log');
    }

    public static function log(string $message): void
    {
        try {
            $path = self::logPath();
            $dir = dirname($path);
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0775, true);
            }

            $line = '[' . now()->format('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

            if (!File::exists($path)) {
                File::put($path, $line);
                @chmod($path, 0664);
            } else {
                if (!is_writable($path)) {
                    @unlink($path);
                    File::put($path, $line);
                    @chmod($path, 0664);
                } else {
                    File::append($path, $line);
                }
            }

            if (File::exists($path) && File::size($path) > 400_000) {
                // Full file() yerine sondan oku — heap spike önle
                $size = (int) File::size($path);
                $fh = @fopen($path, 'rb');
                if ($fh !== false) {
                    fseek($fh, max(0, $size - 80_000));
                    $chunk = stream_get_contents($fh);
                    fclose($fh);
                    $lines = preg_split("/\r\n|\n|\r/", (string) $chunk) ?: [];
                    if ($size > 80_000 && $lines !== []) {
                        array_shift($lines);
                    }
                    $lines = array_values(array_filter($lines, static fn ($l) => $l !== '' && $l !== false));
                    if (count($lines) > 400) {
                        $lines = array_slice($lines, -400);
                    }
                    File::put($path, implode(PHP_EOL, $lines) . PHP_EOL);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('DuelBotSettings::log failed', ['error' => $e->getMessage()]);
        }
    }

    public static function clearLogs(): void
    {
        try {
            $path = self::logPath();
            File::put($path, '');
            @chmod($path, 0664);
        } catch (\Throwable $e) {
            \Log::warning('DuelBotSettings::clearLogs failed', ['error' => $e->getMessage()]);
        }
    }

    /** @return list<string> */
    public static function recentLogs(int $limit = 120, ?int $botUserId = null): array
    {
        $path = self::logPath();
        if (!File::exists($path)) {
            return [];
        }

        $size = (int) File::size($path);
        if ($size <= 0) {
            return [];
        }

        // Filtre varken daha geniş kuyruk oku; sonra limit uygula
        $bytes = ($botUserId && $botUserId > 0) ? 160_000 : 64_000;
        $start = max(0, $size - $bytes);

        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        if ($start > 0) {
            fseek($fh, $start);
        }
        $chunk = stream_get_contents($fh);
        fclose($fh);
        if ($chunk === false || $chunk === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $chunk) ?: [];
        // Ortadan başladıysa ilk yarım satırı at
        if ($start > 0 && $lines !== []) {
            array_shift($lines);
        }
        $lines = array_values(array_filter($lines, static fn ($l) => $l !== '' && $l !== false));

        if ($botUserId && $botUserId > 0) {
            $lines = array_values(array_filter(
                $lines,
                fn ($line) => self::logLineMentionsBot((string) $line, $botUserId)
            ));
        }

        return array_values(array_slice($lines, -$limit));
    }

    /** Log satırı bu botu mu ilgilendiriyor? */
    public static function logLineMentionsBot(string $line, int $botUserId): bool
    {
        $id = (string) $botUserId;

        // Açık bot işaretleri
        if (preg_match('/\bbot\s*#\s*' . $id . '\b/i', $line)) {
            return true;
        }
        if (preg_match('/\bbot:\s*#\s*' . $id . '\b/i', $line)) {
            return true;
        }
        // " #128 Name[medium] " / "bot #128[easy]"
        if (preg_match('/#' . $id . '\b[^\n]{0,40}\[(?:easy|medium|hard|professor)\]/i', $line)) {
            return true;
        }
        // Ayarlar: AKTİF · #128 · medium
        if (preg_match('/(?:AKTİF|PASİF)\s*·\s*#' . $id . '\b/u', $line)) {
            return true;
        }
        // Genel: #128 geçiyor ve bot aksiyonu satırı
        if (preg_match('/#' . $id . '\b/', $line)
            && preg_match('/(PICK|EŞLEŞME|CEVAP|SONUÇ|DÜŞÜN|BAHİS|MAÇ İSTAT|Profil|Avatar|Ayarlar|coin|Worker|TEKLİF)/ui', $line)
        ) {
            return true;
        }

        return false;
    }
}
