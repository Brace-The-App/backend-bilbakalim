<?php

namespace App\Console\Commands;

use App\Http\Services\NotificationService;
use App\Models\NotificationSchedule;
use App\Services\NotificationFlowHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessNotificationSchedules extends Command
{
    protected $signature = 'notifications:process-schedules';

    protected $description = 'Zamanlanmış bildirim şablonlarını gönder (Europe/Istanbul)';

    public function handle(NotificationService $notificationService): int
    {
        $tz = NotificationFlowHelper::timezone();
        $nowLocal = Carbon::now($tz);
        $todayLocal = $nowLocal->toDateString();
        $currentHm = $nowLocal->format('H:i');

        $schedules = NotificationSchedule::query()
            ->with('template')
            ->where('is_active', true)
            ->whereHas('template', fn ($q) => $q->where('is_active', true))
            ->get();

        $processed = 0;

        foreach ($schedules as $schedule) {
            $template = $schedule->template;
            if (! $template) {
                continue;
            }

            $shouldSend = false;

            if ($schedule->schedule_type === 'once') {
                if ($schedule->send_at && $schedule->send_at->lte(now()) && $schedule->last_sent_at === null) {
                    $shouldSend = true;
                }
            } elseif ($schedule->schedule_type === 'daily') {
                $sendTime = $schedule->send_time;
                $timeHm = is_string($sendTime)
                    ? substr($sendTime, 0, 5)
                    : Carbon::parse($sendTime)->format('H:i');

                $lastSentLocal = $schedule->last_sent_at
                    ? $schedule->last_sent_at->timezone($tz)->toDateString()
                    : null;

                if ($timeHm === $currentHm && $lastSentLocal !== $todayLocal) {
                    $shouldSend = true;
                }
            }

            if (! $shouldSend) {
                continue;
            }

            $targetUsers = is_array($schedule->target_users) && count($schedule->target_users) > 0
                ? array_map('intval', $schedule->target_users)
                : (is_array($template->target_users) && count($template->target_users) > 0
                    ? array_map('intval', $template->target_users)
                    : null);

            $result = $notificationService->sendNotification(
                $template->title,
                $template->content,
                $template->channel,
                $targetUsers,
                $schedule->created_by,
                $template->id
            );

            if ($result['success'] ?? false) {
                $schedule->update(['last_sent_at' => now()]);
                $processed++;
                $this->info("Schedule #{$schedule->id} sent (template #{$template->id}, count: {$result['sent_count']})");
            } else {
                Log::warning('Scheduled notification failed', [
                    'schedule_id' => $schedule->id,
                    'message' => $result['message'] ?? 'unknown',
                ]);
                $this->warn("Schedule #{$schedule->id} failed: " . ($result['message'] ?? 'unknown'));
            }
        }

        if ($processed === 0) {
            $this->line('No schedules due.');
        }

        return self::SUCCESS;
    }
}
