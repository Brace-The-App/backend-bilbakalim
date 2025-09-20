<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Services\NotificationService;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send notification to users
     * 
     * @OA\Post(
     *     path="/api/notifications/send",
     *     summary="Send notification to users",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"title", "content", "type"},
     *                 @OA\Property(property="title", type="string", description="Notification title"),
     *                 @OA\Property(property="content", type="string", description="Notification content"),
     *                 @OA\Property(property="type", type="string", enum={"email", "sms", "fcm"}, description="Notification type"),
     *                 @OA\Property(property="target_users", type="string", description="Comma-separated user IDs (optional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bildirim başarıyla gönderildi."),
     *             @OA\Property(property="sent_count", type="integer", example=150),
     *             @OA\Property(property="notification_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bildirim gönderilirken bir hata oluştu.")
     *         )
     *     )
     * )
     */
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'required|in:email,sms,fcm',
                'target_users' => 'nullable|string', // comma-separated user IDs
            ]);

            $targetUsers = null;
            if (!empty($validated['target_users'])) {
                $targetUsers = array_map('intval', explode(',', $validated['target_users']));
            }

            $result = $this->notificationService->sendNotification(
                $validated['title'],
                $validated['content'],
                $validated['type'],
                $targetUsers
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (ValidationException $e) {
            Log::error('Notification send validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification send error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bildirim gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     * 
     * @OA\Get(
     *     path="/api/notifications/stats",
     *     summary="Get notification statistics",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="stats", type="array", @OA\Items(
     *                 @OA\Property(property="type", type="string", example="email"),
     *                 @OA\Property(property="total_count", type="integer", example=10),
     *                 @OA\Property(property="total_sent", type="integer", example=1500),
     *                 @OA\Property(property="avg_sent_per_notification", type="number", example=150.0),
     *                 @OA\Property(property="last_sent", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function stats()
    {
        try {
            $stats = $this->notificationService->getNotificationStats();
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Notification stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'İstatistikler alınırken bir hata oluştu'
            ], 500);
        }
    }

    /**
     * Get recent notifications
     * 
     * @OA\Get(
     *     path="/api/notifications/recent",
     *     summary="Get recent notifications",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of notifications to retrieve",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="notifications", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Test Notification"),
     *                 @OA\Property(property="content", type="string", example="This is a test notification"),
     *                 @OA\Property(property="type", type="string", example="email"),
     *                 @OA\Property(property="sent_count", type="integer", example=150),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function recent(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $notifications = $this->notificationService->getRecentNotifications($limit);
            
            return response()->json([
                'success' => true,
                'notifications' => $notifications
            ]);

        } catch (\Exception $e) {
            Log::error('Recent notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Son bildirimler alınırken bir hata oluştu'
            ], 500);
        }
    }
}