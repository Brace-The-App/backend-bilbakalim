<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\Notification as NotificationModel;
use App\Notifications\FCMNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send notification to users based on type
     */
    public function sendNotification($title, $content, $type, $targetUsers = null)
    {
        try {
            $notification = NotificationModel::create([
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'target_users' => $targetUsers ? json_encode($targetUsers) : null,
                'send_at' => now(),
            ]);

            $sentCount = 0;
     
            switch ($type) {
                case 'email':
                    $sentCount = $this->sendEmailNotifications($title, $content, $targetUsers);
                    break;
                case 'sms':
                    $sentCount = $this->sendSMSNotifications($title, $content, $targetUsers);
                    break;
                case 'fcm':
                    $sentCount = $this->sendFCMNotifications($title, $content, $targetUsers);
                    break;
                default:
                    throw new \Exception("Unsupported notification type: {$type}");
            }

            // Update notification record with sent count
            $notification->update([
                'sent_count' => $sentCount,
                'send_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Bildirim başarıyla gönderildi.',
                'sent_count' => $sentCount,
                'notification_id' => $notification->id
            ];

        } catch (\Exception $e) {
            Log::error('Notification sending failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bildirim gönderilirken bir hata oluştu: ' . $e->getMessage(),
                'sent_count' => 0
            ];
        }
    }

    /**
     * Send email notifications
     */
    private function sendEmailNotifications($title, $content, $targetUsers)
    {
        $users = $this->getTargetUsers($targetUsers);
        $sentCount = 0;

        foreach ($users as $user) {
            if ($user->email) {
                try {
                    Mail::send('emails.notification', [
                        'title' => $title,
                        'content' => $content,
                        'type' => 'email',
                        'sentAt' => now()->format('d.m.Y H:i'),
                        'user' => $user
                    ], function ($message) use ($user, $title) {     
                        $message->to($user->email)
                                ->subject($title);
                    });
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("Email sending failed for user {$user->id}: " . $e->getMessage());
                }
            }
        }

        return $sentCount;
    }

    /**
     * Send SMS notifications (placeholder - implement SMS service)
     */
    private function sendSMSNotifications($title, $content, $targetUsers)
    {
        $users = $this->getTargetUsers($targetUsers);
        $sentCount = 0;

        foreach ($users as $user) {
            if ($user->phone) {
                try {
                    // TODO: Implement SMS service integration
                    Log::info("SMS would be sent to {$user->phone}: {$title} - {$content}");
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("SMS sending failed for user {$user->id}: " . $e->getMessage());
                }
            }
        }

        return $sentCount;
    }

    /**
     * Send FCM notifications to users with role 3 and device_id
     */
    private function sendFCMNotifications($title, $content, $targetUsers)
    {
        $users = $this->getTargetUsers($targetUsers, true); // Only role 3 with device_id
        $sentCount = 0;

        foreach ($users as $user) {
            if ($user->device_id && $user->role_id == 3) {
                try {
                    Notification::send($user, new FCMNotification($title, $content, [
                        'title' => $title,
                        'body' => $content,
                        'user_id' => $user->id,
                        'timestamp' => now()->toISOString(),
                    ]));
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("FCM sending failed for user {$user->id}: " . $e->getMessage());
                }
            }
        }

        return $sentCount;
    }

    /**
     * Get target users based on criteria
     */
    private function getTargetUsers($targetUsers = null, $fcmOnly = false)
    {
        $query = User::query();
    
        if ($fcmOnly) {
            // Sadece FCM device_id olan kullanıcılar
            $query->whereNotNull('device_id');
        }
    
        // Role 3 filtrele
        $query->where('role_id', 3);
    
        // Limit uygula
        if ($targetUsers && is_numeric($targetUsers)) {
            $query->limit((int) $targetUsers);
        }
    
        return $query->get();
    }
    

    /**
     * Get notification statistics
     */
    public function getNotificationStats()
    {
        return NotificationModel::selectRaw('
            type,
            COUNT(*) as total_count,
            SUM(sent_count) as total_sent,
            AVG(sent_count) as avg_sent_per_notification,
            MAX(created_at) as last_sent
        ')
        ->groupBy('type')
        ->get();
    }

    /**
     * Get recent notifications
     */
    public function getRecentNotifications($limit = 10)
    {
        return NotificationModel::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
