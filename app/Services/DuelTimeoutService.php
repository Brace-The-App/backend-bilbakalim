<?php

namespace App\Services;

use App\Http\Controllers\API\DuelController;
use App\Models\Duel;
use App\Models\DuelAnswer;
use App\Models\User;
use Carbon\Carbon;

class DuelTimeoutService
{
    /** Rakip cevapladıktan sonra diğer tarafın AFK süresi (sn) */
    public const ANSWER_WAIT_SECONDS = 45;

    /** Socket kopunca aynı süre (sn) — socket tarafı da bu değeri kullanır */
    public const DISCONNECT_WAIT_SECONDS = 45;

    /**
     * Üst üste bu kadar süre-bitimi cevabı (selected_answer=0) → AFK forfeit.
     * Tek/çift timeout (düşünürken süre) bozmaz; bot işgalini keser.
     */
    public const AFK_ZERO_STREAK = 5;

    public static function answerWaitSeconds(): int
    {
        return self::ANSWER_WAIT_SECONDS;
    }

    /**
     * Aktif düellolarda: bir taraf cevapladı, diğeri X sn sessiz → sessiz taraf kaybeder.
     *
     * @return int Bitirilen düello sayısı
     */
    public static function sweepAnswerTimeouts(): int
    {
        $seconds = self::answerWaitSeconds();
        $cutoff = now()->subSeconds($seconds);
        $finished = 0;

        $duels = Duel::query()
            ->where('status', 'active')
            ->whereNotNull('current_question_id')
            ->whereNotNull('opponent_id')
            ->orderBy('id')
            ->get();

        foreach ($duels as $duel) {
            if (self::forfeitIfAnswerTimedOut($duel, $cutoff)) {
                $finished++;
            }
        }

        return $finished;
    }

    public static function forfeitIfAnswerTimedOut(Duel $duel, ?Carbon $cutoff = null): bool
    {
        if ($duel->status !== 'active' || !$duel->current_question_id || !$duel->opponent_id) {
            return false;
        }

        $cutoff = $cutoff ?? now()->subSeconds(self::answerWaitSeconds());

        $answers = DuelAnswer::query()
            ->where('duel_id', $duel->id)
            ->where('question_id', $duel->current_question_id)
            ->get();

        if ($answers->count() !== 1) {
            return false;
        }

        $answered = $answers->first();
        $at = $answered->answered_at ?? $answered->created_at;
        if (!$at || $at->gt($cutoff)) {
            return false;
        }

        $answeredUserId = (int) $answered->user_id;
        $loserId = (int) $duel->challenger_id === $answeredUserId
            ? (int) $duel->opponent_id
            : (int) $duel->challenger_id;

        if ($loserId <= 0) {
            return false;
        }

        $loser = User::query()->find($loserId);
        if (!$loser) {
            return false;
        }

        $result = app(DuelController::class)->forfeitAsLeave($duel->fresh(), $loser, 'answer_timeout');

        return (bool) ($result['success'] ?? false);
    }
}
