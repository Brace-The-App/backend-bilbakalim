<?php

namespace App\Services;

class NotificationFlowHelper
{
    public static function timezone(): string
    {
        return (string) config('notifications.timezone', 'Europe/Istanbul');
    }
}
