<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view notifications')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create notifications')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit notifications')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete notifications')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Notification::with('creator');
        
        // Arama
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }
        
        // Tip filtresi
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        // Durum filtresi
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $notifications = $query->latest()->paginate(10);
        
        return view('admin.notifications.index', compact('notifications'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'required|in:info,success,warning,error',
                'send_at' => 'nullable|date|after:now',
                'is_active' => 'boolean',
            ]);

            $notification = Notification::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'type' => $validated['type'],
                'send_at' => $validated['send_at'] ?? null,
                'is_active' => $validated['is_active'] ?? true,         
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Bildirim başarıyla oluşturuldu!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Notification validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bildirim oluşturulurken bir hata oluştu!'
            ], 500);
        }
    }

    public function show(Notification $notification)
    {
        $notification->load('creator');
        return response()->json([
            'success' => true,
            'notification' => $notification
        ]);
    }

    public function update(Request $request, Notification $notification)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'required|in:info,success,warning,error',
                'send_at' => 'nullable|date|after:now',
                'is_active' => 'boolean',
            ]);

            $notification->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'type' => $validated['type'],
                'send_at' => $validated['send_at'] ?? null,
                'is_active' => $validated['is_active'] ?? true
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Bildirim başarıyla güncellendi!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Notification validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bildirim güncellenirken bir hata oluştu!'
            ], 500);
        }
    }

    public function destroy(Notification $notification)
    {
        try {
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bildirim başarıyla silindi!'
            ]);

        } catch (\Exception $e) {
            Log::error('Notification deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bildirim silinirken bir hata oluştu!'
            ], 500);
        }
    }
}