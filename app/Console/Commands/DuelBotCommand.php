<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\DuelController;
use App\Models\Duel;
use App\Models\DuelAnswer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DuelBotCommand extends Command
{
    protected $signature = 'duel:bot
        {--bot=127 : Bot kullanıcı ID (varsayılan: Ayşegül test)}
        {--vs= : Rakip kullanıcı ID — verilirse hemen eşleştirir}
        {--auto : Online + boştaki oyuncuyu bulup otomatik eşleştir (meydan okuda beklerken)}
        {--multiplier=x1 : x1|x2|x4|x8}
        {--delay=2 : Cevap / poll aralığı (sn)}
        {--correct : Rastgele yerine doğru şıkkı işaretle}
        {--once : Tek cevap atıp çık}
        {--timeout=600 : Süre (sn)}';

    protected $description = 'Düello test botu: otomatik eşleşir ve sorulara cevap verir';

    public function handle(): int
    {
        $botId = (int) $this->option('bot');
        $bot = User::query()->find($botId);

        if (!$bot) {
            $this->error("Bot kullanıcı bulunamadı: #{$botId}");
            return self::FAILURE;
        }

        if ((int) $bot->coins <= 0) {
            $bot->update(['coins' => 1000]);
            $this->warn('Bot coini 0 idi → 1000 yapıldı.');
        }

        $multiplier = (string) $this->option('multiplier');
        if (!in_array($multiplier, ['x1', 'x2', 'x4', 'x8'], true)) {
            $this->error('Geçersiz multiplier.');
            return self::FAILURE;
        }

        $vsId = $this->option('vs') ? (int) $this->option('vs') : null;
        $auto = (bool) $this->option('auto');
        $duelId = null;

        $this->info("Bot: #{$bot->id} {$bot->name} (coins={$bot->coins}) [{$multiplier}]");

        // Eski yarım düellolar botu kilitliyor — auto/vs başında temizle
        if ($auto || $vsId) {
            $closed = $this->closeStaleBotDuels($bot->id);
            if ($closed > 0) {
                $this->warn("Eski aktif düello kapatıldı: {$closed} adet (taze eşleşme için).");
            }
        }

        if ($vsId) {
            if ($vsId === $botId) {
                $this->error('--vs bot ile aynı olamaz.');
                return self::FAILURE;
            }
            $human = User::query()->find($vsId);
            if (!$human || (int) $human->coins <= 0) {
                $this->error('Rakip yok veya coin yok.');
                return self::FAILURE;
            }
            $this->info("Eşleştiriliyor: #{$human->id} {$human->name} ↔ bot");
            $duelId = $this->matchUsers($human->id, $bot->id, $multiplier);
            if (!$duelId) {
                return self::FAILURE;
            }
            $this->info("Düello başladı: #{$duelId}");
        } elseif ($auto) {
            $this->info('AUTO mod: meydan okuda bekleyen (socket online + boş) oyuncuya yapışır, cevaplar.');
            $this->line('Mobilde eşleşme ekranında bekle; bot seni bulunca maça sokar.');
        } else {
            $this->info('Sadece cevap modu (eşleşme yok). Otomatik için: --auto');
        }

        $deadline = time() + max(30, (int) $this->option('timeout'));
        $delay = max(0.5, (float) $this->option('delay'));

        do {
            $duel = $this->findActiveDuelForBot($bot->id, $duelId);

            // Aktif düello yoksa: auto ise online rakip ara
            if (!$duel) {
                if ($duelId) {
                    $finished = Duel::query()->find($duelId);
                    if ($finished && $finished->status === 'finished') {
                        $this->info("Düello bitti (#{$duelId}).");
                        if (!$auto) {
                            return self::SUCCESS;
                        }
                        $duelId = null;
                        $this->line('AUTO: yeni rakip aranıyor...');
                    }
                }

                if ($auto && !$this->botBusy($bot->id)) {
                    $candidateId = $this->findOnlineIdleOpponent($bot->id, $vsId);
                    if ($candidateId) {
                        $name = User::where('id', $candidateId)->value('name') ?? '?';
                        $this->info("Online rakip bulundu: #{$candidateId} {$name} → eşleşiyor");
                        $duelId = $this->matchUsers($candidateId, $bot->id, $multiplier);
                        if ($duelId) {
                            $this->info("Düello başladı: #{$duelId}");
                            continue;
                        }
                    } else {
                        $this->line('[' . now()->format('H:i:s') . '] Online boş oyuncu yok, bekleniyor...');
                    }
                } elseif (!$auto && !$vsId) {
                    $this->line('[' . now()->format('H:i:s') . '] Botun aktif düellosu yok...');
                } else {
                    $this->line('[' . now()->format('H:i:s') . '] Rakip / düello bekleniyor...');
                }

                usleep((int) ($delay * 1_000_000));
                continue;
            }

            $duelId = $duel->id;
            $duel->refresh();

            // 2x/4x/6x/8x teklifi gelmişse bot her zaman kabul eder
            if ($this->acceptPendingBetIfAny($bot, $duel)) {
                $duel->refresh();
            }

            if ($this->botAlreadyAnswered($duel, $bot->id)) {
                $this->line("[#{$duel->id} Q{$duel->current_question_number}] Bot cevapladı, senin cevabın bekleniyor...");
                usleep((int) ($delay * 1_000_000));
                continue;
            }

            if (!$duel->current_question_id) {
                $this->warn("Düello #{$duel->id} sorusu yok.");
                usleep((int) ($delay * 1_000_000));
                continue;
            }

            $choice = $this->pickAnswer($duel);
            $this->info("[#{$duel->id} Q{$duel->current_question_number}] Bot cevap: {$choice}");
            $this->submitBotAnswer($bot, $duel->id, $choice);

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            usleep((int) ($delay * 1_000_000));
        } while (time() < $deadline);

        $this->warn('Timeout — bot durdu.');
        return self::SUCCESS;
    }

    private function closeStaleBotDuels(int $botId): int
    {
        return Duel::query()
            ->whereIn('status', ['waiting', 'active'])
            ->where(function ($q) use ($botId) {
                $q->where('challenger_id', $botId)->orWhere('opponent_id', $botId);
            })
            ->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
    }

    private function botBusy(int $botId): bool
    {
        return Duel::where('status', 'active')
            ->where(function ($q) use ($botId) {
                $q->where('challenger_id', $botId)->orWhere('opponent_id', $botId);
            })
            ->exists();
    }

    /**
     * Socket online listesinden, aktif düellosu olmayan rakip seç.
     */
    private function findOnlineIdleOpponent(int $botId, ?int $preferUserId = null): ?int
    {
        $base = rtrim((string) config('app.socket_url'), '/');
        try {
            $res = Http::timeout(3)->get($base . '/socket-webhooks/online-users');
            if (!$res->successful()) {
                $this->warn('online-users alınamadı: HTTP ' . $res->status());
                return null;
            }
            $userIds = collect($res->json('userIds') ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0 && $id !== $botId)
                ->values();
        } catch (\Throwable $e) {
            $this->warn('online-users hata: ' . $e->getMessage());
            return null;
        }

        if ($userIds->isEmpty()) {
            return null;
        }

        if ($preferUserId && $userIds->contains($preferUserId)) {
            $userIds = collect([$preferUserId])->merge($userIds->reject(fn ($id) => $id === $preferUserId))->values();
        }

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (!$user || (int) $user->coins <= 0) {
                continue;
            }

            $inDuel = Duel::whereIn('status', ['waiting', 'active'])
                ->where(function ($q) use ($userId) {
                    $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
                })
                ->exists();

            if ($inDuel) {
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
            $this->error('Eşleşme başarısız: ' . ($data['message'] ?? 'bilinmeyen'));
            return null;
        }

        return (int) ($data['duel']['duelId'] ?? 0) ?: null;
    }

    /**
     * Rakibin attığı 2x/4x/6x/8x teklifini kabul et.
     */
    private function acceptPendingBetIfAny(User $bot, Duel $duel): bool
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
        if ($questionId <= 0) {
            return false;
        }

        $this->info("[#{$duel->id}] Çarpan teklifi geldi: {$mult}x → kabul ediliyor");

        Auth::login($bot);
        try {
            $request = Request::create("/api/duel/question-multiplier/respond/{$duel->id}", 'POST', [
                'question_id' => $questionId,
                'accept' => true,
            ]);
            $request->setUserResolver(fn () => $bot);
            $request->headers->set('Accept', 'application/json');

            /** @var \Illuminate\Http\JsonResponse $response */
            $response = app(DuelController::class)->respondQuestionMultiplier($request, $duel->id);
            $data = $response->getData(true);

            if (!($data['success'] ?? false)) {
                $this->warn('Teklif kabul edilemedi: ' . ($data['message'] ?? json_encode($data)));
                return false;
            }

            $this->line("  → {$mult}x kabul edildi");
            return true;
        } catch (\Throwable $e) {
            $this->error('Teklif kabul hatası: ' . $e->getMessage());
            return false;
        } finally {
            Auth::logout();
        }
    }

    private function findActiveDuelForBot(int $botId, ?int $preferId = null): ?Duel
    {
        $q = Duel::query()
            ->where('status', 'active')
            ->where(function ($q) use ($botId) {
                $q->where('challenger_id', $botId)->orWhere('opponent_id', $botId);
            })
            ->with('currentQuestion')
            ->orderByDesc('id');

        if ($preferId) {
            $preferred = (clone $q)->where('id', $preferId)->first();
            if ($preferred) {
                return $preferred;
            }
        }

        return $q->first();
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

    private function pickAnswer(Duel $duel): string
    {
        if ($this->option('correct') && $duel->currentQuestion) {
            return (string) $duel->currentQuestion->correct_answer;
        }

        return (string) random_int(1, 4);
    }

    private function submitBotAnswer(User $bot, int $duelId, string $selectedAnswer): bool
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
                $this->warn('API: ' . ($data['message'] ?? json_encode($data)));
                return false;
            }

            $correct = ($data['is_correct'] ?? false) ? 'doğru' : 'yanlış';
            $wait = ($data['waiting_for_opponent'] ?? false) ? ' (rakip bekleniyor)' : '';
            $this->line("  → {$correct}{$wait}");
            return true;
        } catch (\Throwable $e) {
            $this->error('Hata: ' . $e->getMessage());
            return false;
        } finally {
            Auth::logout();
        }
    }
}
