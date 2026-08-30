<?php

namespace App\Services;

use App\Models\User;

class NotificationFlowHelper
{
    public static function liveFlowUserId(): int
    {
        return (int) config('notifications.live_flow_user_id', 15);
    }

    public static function canAccessLiveFlow(?User $user): bool
    {
        return $user !== null && (int) $user->id === self::liveFlowUserId();
    }

    public static function timezone(): string
    {
        return (string) config('notifications.timezone', 'Europe/Istanbul');
    }
}
