<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\DuelController;
use App\Models\Duel;
use App\Models\DuelAnswer;
use App\Models\User;
use App\Services\BotAnswerEngine;
use App\Services\DuelBotSettings;
use App\Services\DuelTimeoutService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DuelBotCommand extends Command
{
    protected $signature = 'duel:bot
        {--bot=0 : Bot kullanıcı ID (0 = havuzdaki tüm botlar)}
        {--vs= : Rakip kullanıcı ID — verilirse hemen eşleştirir}
        {--auto : Sürekli çalış: maç gelince cevapla (eşleşmeyi socket yapar)}
        {--force-match : Eski davranış: kuyruktakileri zorla botla eşleştir}
        {--multiplier=x1 : x1|x2|x4|x8 (sadece --vs / --force-match)}
        {--delay=1.5 : Cevap / poll aralığı (sn)}
        {--correct : Zorluk yerine her zaman doğru}
        {--once : Tek cevap atıp çık}
        {--timeout=1800 : Süre (sn) — PM2 recycle (~30 dk; heap stall önler)}
        {--max-memory=150 : Bu MB üstünde temiz çıkış (PM2 yeniden başlatır; 0=kapalı)}';

    protected $description = 'Düello bot worker: havuzdaki bot(lar) ile cevap verir';

    /** @var array<int, BotAnswerEngine> */
    private array $engines = [];

    /** @var array<int, array{correct:int,total:int}> */
    private array $matchStats = [];

    /** @var array<int, int> botId => son işlenen düello id (istatistik sıfırlama) */
    private array $lastDuelByBot = [];

    /** @var array<string, float> duelId:questionId => unix ready_at */
    private array $answerReadyAt = [];

    /**
     * @var array<string, array{accept:bool,ready_at:float,multiplier:int,question_id:int,decided:bool,done:bool}>
     */
    private array $betPlans = [];

    public function handle(): int
    {
        $botIds = $this->resolveBotIds();
        if ($botIds === []) {
            $this->error('Havuzda bot yok.');
            return self::FAILURE;
        }

        foreach ($botIds as $botId) {
            $bot = User::query()->find($botId);
            if (!$bot) {
                $this->warn("Bot #{$botId} bulunamadı, atlandı.");
                continue;
            }
            if (!(bool) $bot->is_bot) {
                $bot->is_bot = true;
                $bot->save();
            }
            if ((int) $bot->coins <= 0) {
                $bot->update(['coins' => 1000]);
                DuelBotSettings::log("Bot #{$botId} coin 0 → 1000");
            }
            $cfg = DuelBotSettings::all($botId);
            $this->engines[$botId] = new BotAnswerEngine($cfg['difficulty']);
            $this->matchStats[$botId] = ['correct' => 0, 'total' => 0];
        }

        $multiplier = (string) $this->option('multiplier');
        if (!in_array($multiplier, ['x1', 'x2', 'x4', 'x8'], true)) {
            $this->error('Geçersiz multiplier.');
            return self::FAILURE;
        }

        $vsId = $this->option('vs') ? (int) $this->option('vs') : null;
        $auto = (bool) $this->option('auto');
        $forceMatch = (bool) $this->option('force-match');
        $preferDuelId = null;

        $names = collect($botIds)->map(function ($id) {
            $u = User::query()->find($id);
            $cfg = DuelBotSettings::all($id);

            return "#{$id} " . ($u?->name ?? '?') . "[{$cfg['difficulty']}]";
        })->implode(', ');

        $msg = "Worker başladı · botlar: {$names}";
        $this->info($msg);
        DuelBotSettings::log($msg);

        if ($auto || $vsId || $forceMatch) {
            foreach ($botIds as $botId) {
                $closed = $this->closeStaleBotDuels($botId);
                if ($closed > 0) {
                    DuelBotSettings::log("Bot #{$botId}: eski düello kapatıldı ×{$closed}");
                }
            }
        }

        if ($vsId) {
            $botId = $botIds[0];
            if ($vsId === $botId) {
                $this->error('--vs bot ile aynı olamaz.');
                return self::FAILURE;
            }
            $human = User::query()->find($vsId);
            if (!$human || (int) $human->coins <= 0) {
                $this->error('Rakip yok veya coin yok.');
                return self::FAILURE;
            }
            $preferDuelId = $this->matchUsers($human->id, $botId, $multiplier);
            if (!$preferDuelId) {
                return self::FAILURE;
            }
            DuelBotSettings::log("Manuel eşleşme: #{$vsId} ↔ bot #{$botId} düello #{$preferDuelId}");
        } elseif ($auto) {
            DuelBotSettings::log('AUTO: havuz worker — socket eşleşmesini bekliyor');
        }

        $deadline = time() + max(30, (int) $this->option('timeout'));
        $delay = max(0.5, (float) $this->option('delay'));
        $maxMemoryMb = max(0, (int) $this->option('max-memory'));
        $tick = 0;
        $lastIdleLogAt = 0;
        $lastStaleWaitingCloseAt = 0;
        $startedAt = time();

        do {
            $tick++;
            $didWork = false;

            // Bellek / heartbeat: zend_mm_heap öncesi temiz recycle
            if ($tick % 5 === 1) {
                $this->writeHeartbeat($botIds, $tick, $startedAt);
                if ($maxMemoryMb > 0) {
                    $usedMb = (int) round(memory_get_usage(true) / 1048576);
                    if ($usedMb >= $maxMemoryMb) {
                        $msg = "Worker bellek eşiği · {$usedMb}MB ≥ {$maxMemoryMb}MB · temiz recycle";
                        $this->warn($msg);
                        DuelBotSettings::log($msg);

                        return self::SUCCESS;
                    }
                }
            }

            // Uzun ömürlü PDO: periyodik reconnect (heap bozulmasını azaltır)
            if ($tick % 60 === 0) {
                try {
                    DB::reconnect();
                } catch (\Throwable) {
                    // sonraki tick yeniden dener
                }
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Yeni oluşturulan botlar worker restart beklemeden devreye girsin
            if ($auto && (int) $this->option('bot') <= 0) {
                $botIds = $this->syncBotPool($botIds);
            }

            // AFK 45s — her tick değil (~4.5s granularity yeterli)
            if ($tick % 3 === 1) {
                $timedOut = DuelTimeoutService::sweepAnswerTimeouts();
                if ($timedOut > 0) {
                    $didWork = true;
                }
            }

            // Waiting bot düellolarını periyodik temizle (isBotBusy kilidi)
            if ($auto && (time() - $lastStaleWaitingCloseAt) >= 300) {
                $lastStaleWaitingCloseAt = time();
                foreach ($botIds as $botId) {
                    $closed = $this->closeStaleBotDuels($botId);
                    if ($closed > 0) {
                        $didWork = true;
                        DuelBotSettings::log("Bot #{$botId}: waiting düello temizlendi ×{$closed}");
                    }
                }
            }

            foreach ($botIds as $botId) {
                // Boşta: User/settings yükleme (maç yoksa)
                $duel = $this->findActiveDuelForBot($botId, $preferDuelId);
                if (!$duel && !$preferDuelId && !$forceMatch) {
                    continue;
                }

                $bot = User::query()->find($botId);
                if (!$bot) {
                    continue;
                }
                if ((int) $bot->coins <= 0) {
                    $bot->update(['coins' => 1000]);
                }

                $cfg = DuelBotSettings::all($botId);
                $engine = $this->engines[$botId] ??= new BotAnswerEngine($cfg['difficulty']);
                $engine->setTier($cfg['difficulty']);

                if (!$duel) {
                    if ($preferDuelId) {
                        $finished = Duel::query()->find($preferDuelId);
                        if ($finished && $finished->status === 'finished') {
                            DuelBotSettings::log("Düello bitti: #{$preferDuelId}");
                            $this->clearPlansForDuel((int) $preferDuelId);
                            $preferDuelId = null;
                            $engine->resetMatchStats();
                            $this->matchStats[$botId] = ['correct' => 0, 'total' => 0];
                            if (!$auto) {
                                return self::SUCCESS;
                            }
                        }
                    }

                    if ($forceMatch && !empty($cfg['is_active']) && !$this->botBusy($botId)) {
                        $candidateId = $this->findQueuedOpponent($botId, $multiplier, $vsId);
                        if ($candidateId) {
                            $preferDuelId = $this->matchUsers($candidateId, $botId, $multiplier);
                            if ($preferDuelId) {
                                $didWork = true;
                                continue;
                            }
                        }
                    }
                    continue;
                }

                $didWork = true;
                $preferDuelId = $duel->id;

                // Yeni maç → isabet istatistiğini sıfırla (önceki maç sızmasın)
                if (($this->lastDuelByBot[$botId] ?? null) !== $duel->id) {
                    $engine->resetMatchStats();
                    $this->matchStats[$botId] = ['correct' => 0, 'total' => 0];
                    $this->lastDuelByBot[$botId] = $duel->id;
                    DuelBotSettings::log(
                        "MAÇ İSTAT · bot #{$botId}[{$cfg['difficulty']}] · düello #{$duel->id} sıfırlandı · hedef ~%"
                        . (int) round(100 * (float) $cfg['target_accuracy'])
                    );
                }

                $duel->refresh();

                if ($this->handlePendingBet($bot, $duel, $engine)) {
                    $duel->refresh();
                    // Red → düello biter; bir sonraki turda finished yakalanır
                    if ($duel->status !== 'active') {
                        $engine->resetMatchStats();
                        $this->matchStats[$botId] = ['correct' => 0, 'total' => 0];
                        $this->clearPlansForDuel($duel->id);
                        $preferDuelId = $auto ? null : $preferDuelId;
                        continue;
                    }
                }

                $engine->ensureOfferPlan();

                if ($this->botAlreadyAnswered($duel, $botId)) {
                    continue;
                }

                if (!$duel->current_question_id) {
                    continue;
                }

                // Bot kendi teklifini, cevaplamadan önce (soru açıkken) atsın
                if ($this->maybeOfferBet($bot, $duel, $engine)) {
                    $duel->refresh();
                }

                $readyKey = $duel->id . ':' . $duel->current_question_id;
                $questionAge = $this->currentQuestionAgeSeconds($duel);
                $opponentAnswered = $this->opponentAlreadyAnswered($duel, $botId);

                if (!isset($this->answerReadyAt[$readyKey])) {
                    $think = $engine->answerDelaySeconds();
                    // Rakip zaten cevapladıysa düşünmeyi kısalt (sessizlik / timeout riski)
                    if ($opponentAnswered) {
                        $think = min($think, 1.2);
                    }
                    $this->answerReadyAt[$readyKey] = microtime(true) + $think;
                    DuelBotSettings::log(
                        "DÜŞÜN · bot #{$botId}[{$cfg['difficulty']}] · düello #{$duel->id} Q{$duel->current_question_number} · {$think}s"
                    );
                }

                // Gecikmiş cevap: worker takılsa bile düşünme süresini zorla bitir
                if ($questionAge >= 8.0 || ($opponentAnswered && $questionAge >= 4.0)) {
                    $this->answerReadyAt[$readyKey] = min(
                        $this->answerReadyAt[$readyKey],
                        microtime(true)
                    );
                }

                if (microtime(true) < $this->answerReadyAt[$readyKey]) {
                    continue;
                }

                $choice = $engine->pickChoice($duel->currentQuestion, (bool) $this->option('correct'));
                $opponent = (int) $duel->challenger_id === $botId ? $duel->opponent : $duel->challenger;
                $oppLabel = $opponent ? "#{$opponent->id} {$opponent->name}" : '?';

                DuelBotSettings::log(
                    "CEVAP · bot #{$botId}[{$cfg['difficulty']}] · düello #{$duel->id} Q{$duel->current_question_number} · rakip {$oppLabel} · şık {$choice}"
                );
                $this->submitBotAnswer($bot, $duel->id, $choice, $oppLabel, $engine);

                if ($this->option('once')) {
                    return self::SUCCESS;
                }
            }

            if (!$didWork && $auto && (time() - $lastIdleLogAt) >= 30) {
                $this->line('[' . now()->format('H:i:s') . '] Havuz boşta, maç bekleniyor...');
                $lastIdleLogAt = time();
            }

            // Idle: orphan map + GC (uzun ömürlü process heap baskısı)
            if (!$didWork && $tick % 20 === 0) {
                $this->pruneStaleMaps();
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            usleep((int) ($delay * 1_000_000));
        } while (time() < $deadline);

        $this->warn('Timeout — bot durdu.');
        DuelBotSettings::log('Worker timeout ile durdu');

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function resolveBotIds(): array
    {
        $opt = (int) $this->option('bot');
        if ($opt > 0) {
            return [$opt];
        }

        $ids = DuelBotSettings::allBotUserIds();
        if ($ids !== []) {
            return $ids;
        }

        $legacy = (int) (DuelBotSettings::all()['user_id'] ?? 0);

        return $legacy > 0 ? [$legacy] : [];
    }

    /**
     * Havuzdaki yeni botları worker'a ekle (restart gerekmesin).
     *
     * @param  list<int>  $current
     * @return list<int>
     */
    private function syncBotPool(array $current): array
    {
        $fresh = $this->resolveBotIds();
        $added = array_values(array_diff($fresh, $current));
        if ($added === []) {
            return $fresh !== [] ? $fresh : $current;
        }

        foreach ($added as $botId) {
            $bot = User::query()->find($botId);
            if (!$bot) {
                continue;
            }
            if (!(bool) $bot->is_bot) {
                $bot->is_bot = true;
                $bot->save();
            }
            if ((int) $bot->coins <= 0) {
                $bot->update(['coins' => 1000]);
            }
            $cfg = DuelBotSettings::all($botId);
            $this->engines[$botId] = new BotAnswerEngine($cfg['difficulty']);
            $this->matchStats[$botId] = ['correct' => 0, 'total' => 0];
            DuelBotSettings::log(
                "WORKER +BOT · #{$botId} " . ($bot->name ?? '') . " [{$cfg['difficulty']}] · restart yok"
            );
        }

        return $fresh;
    }

    /**
     * Worker restart: sadece başlamamış (waiting) maçları iptal et.
     * Active maçlara dokunma — cevap AFK timeout (45s) düzgün kazanan/bakiye yazar.
     */
    private function closeStaleBotDuels(int $botId): int
    {
        $duels = Duel::query()
            ->where('status', 'waiting')
            ->where(function ($q) use ($botId) {
                $q->where('challenger_id', $botId)->orWhere('opponent_id', $botId);
            })
            ->get();

        $closed = 0;
        foreach ($duels as $duel) {
            $settings = $duel->settings ?? [];
            $settings['forfeit_reason'] = 'cancelled';
            $settings['forfeit_at'] = now()->toIso8601String();
            $duel->update([
                'status' => 'finished',
                'finished_at' => now(),
                'winner_id' => null,
                'settings' => $settings,
                'challenger_coins_after' => (int) (User::query()->find($duel->challenger_id)?->coins ?? $duel->challenger_coins_before ?? 0),
                'opponent_coins_after' => $duel->opponent_id
                    ? (int) (User::query()->find($duel->opponent_id)?->coins ?? $duel->opponent_coins_before ?? 0)
                    : $duel->opponent_coins_after,
            ]);
            $closed++;
        }

        return $closed;
    }

    private function botBusy(int $botId): bool
    {
        return DuelBotSettings::isBotBusy($botId);
    }

    private function findQueuedOpponent(int $botId, string $multiplier, ?int $preferUserId = null): ?int
    {
        $base = rtrim((string) config('app.socket_url'), '/');
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(3)->get($base . '/socket-webhooks/duel-ready-queue');
            if (!$res->successful()) {
                return null;
            }
            $userIds = collect($res->json('entries') ?? [])
                ->filter(fn ($e) => strtolower((string) ($e['multiplier'] ?? 'x1')) === strtolower($multiplier))
                ->map(fn ($e) => (int) ($e['userId'] ?? 0))
                ->filter(fn ($id) => $id > 0 && $id !== $botId)
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            return null;
        }

        if ($preferUserId && $userIds->contains($preferUserId)) {
            $userIds = collect([$preferUserId])->merge($userIds->reject(fn ($id) => $id === $preferUserId))->values();
        }

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (!$user || (int) $user->coins <= 0 || $user->is_bot) {
                continue;
            }
            if (DuelBotSettings::isBotBusy($userId)) {
                continue;
            }

            return $userId;
        }

        return null;
    }

    private function matchUsers(int $challengerId, int $opponentId, string $multiplier): ?int
    {
        $request = Request::create('/api/duel/socket-match', 'POST', [
            'challenger_id' => $challengerId,
            'opponent_id' => $opponentId,
            'multiplier' => $multiplier,
        ]);
        $request->headers->set('X-Socket-Secret', (string) config('app.socket_internal_secret'));
        $request->headers->set('Accept', 'application/json');

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = app(DuelController::class)->socketMatch($request);
        $data = $response->getData(true);

        if (!($data['success'] ?? false)) {
            DuelBotSettings::log('Eşleşme başarısız: ' . ($data['message'] ?? 'bilinmeyen'));
            return null;
        }

        return (int) ($data['duel']['duelId'] ?? 0) ?: null;
    }

    /**
     * Bot bahis teklifi: hep 2→4→6→8 sırası, sorular arası boşlukla.
     */
    private function maybeOfferBet(User $bot, Duel $duel, BotAnswerEngine $engine): bool
    {
        $settings = $duel->settings ?? [];
        $bet = $settings['current_bet'] ?? null;
        if ($bet && ($bet['status'] ?? null) === 'pending') {
            return false;
        }

        $qNum = (int) ($duel->current_question_number ?? 0);
        $currentApplied = (int) ($settings['current_question_multiplier'] ?? 1);
        if ($currentApplied < 1) {
            $currentApplied = 1;
        }

        $mult = $engine->offerMultiplierForQuestion($qNum, $currentApplied);
        if (!$mult || !$duel->current_question_id) {
            return false;
        }

        DuelBotSettings::log(
            "BAHİS TEKLİF · düello #{$duel->id} · bot #{$bot->id} · Q{$qNum} → {$mult}x "
            . "(önceki x{$currentApplied})"
        );

        Auth::login($bot);
        try {
            $request = Request::create("/api/duel/question-multiplier/{$duel->id}", 'POST', [
                'question_id' => (int) $duel->current_question_id,
                'multiplier' => $mult,
            ]);
            $request->setUserResolver(fn () => $bot);
            $request->headers->set('Accept', 'application/json');
            /** @var \Illuminate\Http\JsonResponse $response */
            $response = app(DuelController::class)->offerQuestionMultiplier($request, $duel->id);
            $data = $response->getData(true);
            $engine->recordOfferMade($qNum);

            if (!($data['success'] ?? false)) {
                DuelBotSettings::log('BAHİS TEKLİF hata: ' . ($data['message'] ?? ''));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            DuelBotSettings::log('BAHİS TEKLİF exception: ' . $e->getMessage());
            $engine->recordOfferMade($qNum);
            return false;
        } finally {
            Auth::logout();
        }
    }

    /**
     * Bekleyen bahis: bir kez karar ver, düşün, sonra kabul/red.
     * @return bool İşlem yapıldı mı (kabul veya red gönderildi)
     */
    private function handlePendingBet(User $bot, Duel $duel, BotAnswerEngine $engine): bool
    {
        $settings = $duel->settings ?? [];
        $bet = $settings['current_bet'] ?? null;

        if (!$bet || ($bet['status'] ?? null) !== 'pending') {
            return false;
        }

        if ((int) ($bet['opponent_id'] ?? 0) !== (int) $bot->id) {
            return false;
        }

        $questionId = (int) ($bet['question_id'] ?? 0);
        $mult = (int) ($bet['multiplier'] ?? 0);
        if ($questionId <= 0 || $mult <= 0) {
            return false;
        }

        $key = $duel->id . ':bet:' . $questionId . ':' . $mult;
        if (!isset($this->betPlans[$key])) {
            $accept = $engine->decideBetAccept($mult);
            $think = $engine->betThinkDelaySeconds();
            $stats = $engine->getStats();
            $ratePct = $stats['bets_seen'] > 0
                ? (int) round(100 * $stats['bets_accepted'] / $stats['bets_seen'])
                : 0;
            $targetPct = (int) round(100 * (float) $stats['bet_accept_target']);
            $this->betPlans[$key] = [
                'accept' => $accept,
                'ready_at' => microtime(true) + $think,
                'multiplier' => $mult,
                'question_id' => $questionId,
                'decided' => true,
                'done' => false,
            ];
            DuelBotSettings::log(
                "BAHİS KARAR · düello #{$duel->id} · bot #{$bot->id} · {$mult}x → "
                . ($accept ? 'KABUL' : 'RED')
                . " · düşün {$think}s · oran {$stats['bets_accepted']}/{$stats['bets_seen']} (%{$ratePct}) hedef ~%{$targetPct}"
            );
        }

        $plan = &$this->betPlans[$key];
        if (!empty($plan['done'])) {
            return false;
        }
        if (microtime(true) < (float) $plan['ready_at']) {
            return false;
        }

        $accept = (bool) $plan['accept'];
        DuelBotSettings::log(
            "BAHİS · düello #{$duel->id}: {$mult}x "
            . ($accept ? 'kabul' : 'RED (maçı kaybeder)')
            . " (bot #{$bot->id})"
        );

        Auth::login($bot);
        try {
            $request = Request::create("/api/duel/question-multiplier/respond/{$duel->id}", 'POST', [
                'question_id' => $questionId,
                'accept' => $accept,
            ]);
            $request->setUserResolver(fn () => $bot);
            $request->headers->set('Accept', 'application/json');
            /** @var \Illuminate\Http\JsonResponse $response */
            $response = app(DuelController::class)->respondQuestionMultiplier($request, $duel->id);
            $data = $response->getData(true);
            $plan['done'] = true;

            return (bool) ($data['success'] ?? false);
        } catch (\Throwable $e) {
            DuelBotSettings::log('Teklif hata: ' . $e->getMessage());
            return false;
        } finally {
            Auth::logout();
        }
    }

    private function clearPlansForDuel(int $duelId): void
    {
        $prefix = $duelId . ':';
        foreach (array_keys($this->answerReadyAt) as $k) {
            if (str_starts_with((string) $k, $prefix)) {
                unset($this->answerReadyAt[$k]);
            }
        }
        foreach (array_keys($this->betPlans) as $k) {
            if (str_starts_with((string) $k, $prefix)) {
                unset($this->betPlans[$k]);
            }
        }
    }

    /** Bitmiş düellolara ait düşünme/bahis planlarını temizle */
    private function pruneStaleMaps(): void
    {
        if ($this->answerReadyAt === [] && $this->betPlans === []) {
            return;
        }

        $activeIds = Duel::query()
            ->where('status', 'active')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $activeSet = array_fill_keys($activeIds, true);

        foreach (array_keys($this->answerReadyAt) as $k) {
            $duelId = (int) explode(':', (string) $k, 2)[0];
            if ($duelId > 0 && !isset($activeSet[$duelId])) {
                unset($this->answerReadyAt[$k]);
            }
        }
        foreach (array_keys($this->betPlans) as $k) {
            $duelId = (int) explode(':', (string) $k, 2)[0];
            if ($duelId > 0 && !isset($activeSet[$duelId])) {
                unset($this->betPlans[$k]);
            }
        }
    }

    private function findActiveDuelForBot(int $botId, ?int $preferId = null): ?Duel
    {
        $q = Duel::query()
            ->where('status', 'active')
            ->where(function ($q) use ($botId) {
                $q->where('challenger_id', $botId)->orWhere('opponent_id', $botId);
            })
            ->with(['currentQuestion', 'challenger', 'opponent'])
            ->orderByDesc('id');

        if ($preferId) {
            $preferred = (clone $q)->where('id', $preferId)->first();
            if ($preferred) {
                return $preferred;
            }
        }

        return $q->first();
    }

    /** @param list<int> $botIds */
    private function writeHeartbeat(array $botIds, int $tick, int $startedAt): void
    {
        try {
            $path = storage_path('framework/cache/duel-bot-heartbeat.json');
            $dir = dirname($path);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0775, true);
            }
            File::put($path, json_encode([
                'pid' => getmypid(),
                'at' => now()->toIso8601String(),
                'unix' => time(),
                'tick' => $tick,
                'uptime_s' => max(0, time() - $startedAt),
                'memory_mb' => (int) round(memory_get_usage(true) / 1048576),
                'bot_ids' => array_values($botIds),
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // heartbeat opsiyonel
        }
    }

    private function currentQuestionAgeSeconds(Duel $duel): float
    {
        $settings = $duel->settings ?? [];
        $raw = $settings['current_question_started_at'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                return max(0.0, now()->floatDiffInSeconds(\Carbon\Carbon::parse($raw)));
            } catch (\Throwable) {
                // fall through
            }
        }

        if ($duel->updated_at) {
            return max(0.0, now()->floatDiffInSeconds($duel->updated_at));
        }

        return 0.0;
    }

    private function opponentAlreadyAnswered(Duel $duel, int $botId): bool
    {
        if (! $duel->current_question_id) {
            return false;
        }

        $opponentId = (int) $duel->challenger_id === $botId
            ? (int) ($duel->opponent_id ?? 0)
            : (int) $duel->challenger_id;

        if ($opponentId <= 0) {
            return false;
        }

        return DuelAnswer::where('duel_id', $duel->id)
            ->where('user_id', $opponentId)
            ->where('question_id', $duel->current_question_id)
            ->exists();
    }

    private function botAlreadyAnswered(Duel $duel, int $botId): bool
    {
        if (!$duel->current_question_id) {
            return true;
        }

        return DuelAnswer::where('duel_id', $duel->id)
            ->where('user_id', $botId)
            ->where('question_id', $duel->current_question_id)
            ->exists();
    }

    private function submitBotAnswer(User $bot, int $duelId, string $selectedAnswer, string $oppLabel, BotAnswerEngine $engine): bool
    {
        Auth::login($bot);

        $request = Request::create("/api/duel/answer/{$duelId}", 'POST', [
            'selected_answer' => $selectedAnswer,
        ]);
        $request->setUserResolver(fn () => $bot);
        $request->headers->set('Accept', 'application/json');

        try {
            /** @var \Illuminate\Http\JsonResponse $response */
            $response = app(DuelController::class)->submitAnswer($request, $duelId);
            $data = $response->getData(true);

            if (!($data['success'] ?? false)) {
                DuelBotSettings::log('Cevap API hata: ' . ($data['message'] ?? ''));
                return false;
            }

            $isCorrect = (bool) ($data['is_correct'] ?? false);
            $engine->recordResult($isCorrect);
            $stats = $engine->getStats();
            $label = $isCorrect ? 'DOĞRU' : 'YANLIŞ';
            $rate = $stats['total'] > 0 ? round(100 * $stats['correct'] / $stats['total']) : 0;
            $targetPct = (int) round(100 * (float) ($stats['target'] ?? 0.5));
            $wait = ($data['waiting_for_opponent'] ?? false) ? ' · rakip bekleniyor' : '';

            DuelBotSettings::log(
                "SONUÇ · bot #{$bot->id} · düello #{$duelId} · {$label}{$wait} · vs {$oppLabel} · isabet {$stats['correct']}/{$stats['total']} (%{$rate}) · hedef ~%{$targetPct}"
            );

            return true;
        } catch (\Throwable $e) {
            DuelBotSettings::log('Cevap exception: ' . $e->getMessage());
            return false;
        } finally {
            Auth::logout();
        }
    }
}
