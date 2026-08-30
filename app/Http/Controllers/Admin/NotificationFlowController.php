<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\NotificationPresetService;
use App\Http\Services\NotificationService;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\NotificationFlowHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class NotificationFlowController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected NotificationPresetService $presetService,
    ) {
        $this->middleware(function ($request, $next) {
            if (! NotificationFlowHelper::canAccessLiveFlow($request->user())) {
                abort(403, 'Bu sayfaya erişim yetkiniz yok.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $templates = NotificationTemplate::with('creator')
            ->where('is_active', true)
            ->latest()
            ->limit(150)
            ->get();

        $schedules = NotificationSchedule::with(['template', 'creator'])
            ->latest()
            ->limit(50)
            ->get();

        $timezone = NotificationFlowHelper::timezone();

        return view('admin.notifications.live-flow', compact(
            'templates',
            'schedules',
            'timezone'
        ));
    }

    public function feed()
    {
        $recent = Notification::with('creator')
            ->whereIn('type', ['email', 'sms', 'fcm'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'type' => $n->type,
                'type_label' => $n->type_label,
                'sent_count' => $n->sent_count,
                'created_at' => $n->created_at?->timezone(NotificationFlowHelper::timezone())->format('d.m.Y H:i'),
                'creator' => $n->creator?->name,
            ]);

        $upcoming = NotificationSchedule::with('template')
            ->where('is_active', true)
            ->whereHas('template', fn ($q) => $q->where('is_active', true))
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (NotificationSchedule $s) => [
                'id' => $s->id,
                'label' => $s->schedule_label,
                'template_name' => $s->template?->name,
                'channel' => $s->template?->channel,
                'is_active' => $s->is_active,
                'last_sent_at' => $s->last_sent_at?->timezone(NotificationFlowHelper::timezone())->format('d.m.Y H:i'),
            ]);

        return response()->json([
            'success' => true,
            'recent' => $recent,
            'upcoming' => $upcoming,
            'server_time' => now(NotificationFlowHelper::timezone())->format('d.m.Y H:i:s'),
        ]);
    }

    public function templates(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $selectedId = $request->input('selected_id');

        $query = NotificationTemplate::query()
            ->where('is_active', true)
            ->latest();

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('name', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('content', 'like', $like);

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $templates = $query->limit(150)->get();

        $html = view('admin.notifications.live-flow._templates', [
            'templates' => $templates,
            'search' => $search,
            'selectedId' => $selectedId,
        ])->render();

        return response()->json([
            'success' => true,
            'count' => $templates->count(),
            'html' => $html,
        ]);
    }

    public function schedules(Request $request)
    {
        $query = NotificationSchedule::with(['template', 'creator'])->latest();

        if ($request->filled('template_id')) {
            $query->where('notification_template_id', (int) $request->input('template_id'));
        }

        $schedules = $query->limit(50)->get();

        $timezone = NotificationFlowHelper::timezone();
        $activeCount = $schedules->where('is_active', true)->count();

        $html = view('admin.notifications.live-flow._schedules', [
            'schedules' => $schedules,
            'timezone' => $timezone,
            'emptyMessage' => $request->filled('template_id')
                ? 'Bu şablona ait zamanlama yok.'
                : 'Zamanlama yok',
        ])->render();

        return response()->json([
            'success' => true,
            'count' => $schedules->count(),
            'active_count' => $activeCount,
            'html' => $html,
        ]);
    }

    public function showTemplate(NotificationTemplate $template)
    {
        $schedules = $template->schedules()->orderByDesc('id')->get();
        $tz = NotificationFlowHelper::timezone();

        $dailyTimes = $schedules
            ->where('schedule_type', 'daily')
            ->map(fn (NotificationSchedule $s) => is_string($s->send_time)
                ? substr($s->send_time, 0, 5)
                : $s->send_time?->format('H:i'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $once = $schedules->firstWhere('schedule_type', 'once');
        $targetIds = is_array($template->target_users) && count($template->target_users) > 0
            ? array_map('intval', $template->target_users)
            : (is_array($schedules->first()?->target_users) ? array_map('intval', $schedules->first()->target_users) : []);

        $sendHistory = Notification::query()
            ->where('notification_template_id', $template->id)
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'sent_count' => (int) ($n->sent_count ?? 0),
                'status' => $n->delivery_status,
                'status_label' => $n->delivery_status_label,
                'created_at' => $n->created_at?->timezone($tz)->format('d.m.Y H:i'),
            ])
            ->values()
            ->all();

        $schedulesStatus = $schedules->map(function (NotificationSchedule $s) use ($tz) {
            if (! $s->is_active) {
                $status = 'paused';
                $statusLabel = 'Duraklatıldı';
            } elseif ($s->last_sent_at) {
                $status = 'sent';
                $statusLabel = 'Gönderildi';
            } else {
                $status = 'pending';
                $statusLabel = 'Bekliyor';
            }

            return [
                'id' => $s->id,
                'label' => $s->schedule_label,
                'is_active' => $s->is_active,
                'last_sent_at' => $s->last_sent_at?->timezone($tz)->format('d.m.Y H:i'),
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'channel' => $template->channel,
                'title' => $template->title,
                'content' => $template->content,
                'preset_key' => $template->preset_key,
                'source' => $template->source,
            ],
            'schedule_type' => $once ? 'once' : 'daily',
            'daily_times' => $dailyTimes,
            'send_at' => $once && $once->send_at
                ? $once->send_at->timezone($tz)->format('Y-m-d\TH:i')
                : null,
            'target_users' => $targetIds,
            'target_users_detail' => $this->resolveTargetUsersDetail($targetIds),
            'audience_mode' => count($targetIds) > 0 ? 'specific' : 'all',
            'send_history' => $sendHistory,
            'schedules_status' => $schedulesStatus,
        ]);
    }

    public function saveFlow(Request $request)
    {
        try {
            $validated = $request->validate([
                'template_id' => 'nullable|exists:notification_templates,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'channel' => 'required|in:email,sms,fcm',
                'template_name' => 'nullable|string|max:120',
                'preset_key' => 'nullable|string|max:120',
                'target_users' => 'nullable|string',
                'schedule_type' => 'required|in:daily,once',
                'daily_times' => 'required_if:schedule_type,daily|nullable|array|min:1',
                'daily_times.*' => 'date_format:H:i',
                'send_at' => 'required_if:schedule_type,once|nullable|date',
            ]);

            $template = $this->upsertTemplate([
                'template_id' => $validated['template_id'] ?? null,
                'name' => $validated['template_name'] ?? null,
                'channel' => $validated['channel'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'preset_key' => $validated['preset_key'] ?? null,
            ]);

            $targetUsers = null;
            if (! empty($validated['target_users'])) {
                $targetUsers = array_map('intval', explode(',', $validated['target_users']));
            }

            $template->update(['target_users' => $targetUsers]);

            $tz = NotificationFlowHelper::timezone();

            $template->schedules()->delete();

            $created = 0;

            if ($validated['schedule_type'] === 'once') {
                $sendAt = Carbon::parse($validated['send_at'], $tz)->utc();
                if ($sendAt->lte(now())) {
                    throw ValidationException::withMessages([
                        'send_at' => ['Gönderim zamanı gelecekte olmalı (TR saati).'],
                    ]);
                }

                NotificationSchedule::create([
                    'notification_template_id' => $template->id,
                    'schedule_type' => 'once',
                    'send_at' => $sendAt,
                    'target_users' => $targetUsers,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
                $created = 1;
            } else {
                $times = array_values(array_unique($validated['daily_times'] ?? []));
                foreach ($times as $time) {
                    NotificationSchedule::create([
                        'notification_template_id' => $template->id,
                        'schedule_type' => 'daily',
                        'send_time' => $time . ':00',
                        'target_users' => $targetUsers,
                        'is_active' => true,
                        'created_by' => auth()->id(),
                    ]);
                    $created++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Şablon kaydedildi. ' . $created . ' zamanlama aktif — belirlenen saatlerde otomatik gönderilir.',
                'template_id' => $template->id,
                'schedules_count' => $created,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification flow save error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Kayıt başarısız: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function randomPreset(Request $request)
    {
        try {
            $validated = $request->validate([
                'channel' => 'nullable|in:email,sms,fcm',
            ]);

            $preset = $this->presetService->randomPreset($validated['channel'] ?? null);

            return response()->json([
                'success' => true,
                'preset' => $preset,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function storeTemplate(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:120',
                'channel' => 'required|in:email,sms,fcm',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'source' => 'nullable|in:preset,admin',
                'preset_key' => 'nullable|string|max:120',
            ]);

            $source = $validated['source'] ?? 'admin';

            if ($source === 'preset' && filled($validated['preset_key'] ?? null)) {
                $existing = NotificationTemplate::query()
                    ->where('preset_key', $validated['preset_key'])
                    ->where('channel', $validated['channel'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'name' => $validated['name'],
                        'title' => $validated['title'],
                        'content' => $validated['content'],
                        'is_active' => true,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'JSON şablon güncellendi.',
                        'template' => $existing->fresh(),
                    ]);
                }
            }

            $template = NotificationTemplate::create([
                'name' => $validated['name'],
                'channel' => $validated['channel'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'source' => $source,
                'preset_key' => $validated['preset_key'] ?? null,
                'created_by' => auth()->id(),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => $source === 'preset' ? 'JSON şablon kaydedildi.' : 'Şablon oluşturuldu.',
                'template' => $template,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Doğrulama hatası',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification template store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Şablon kaydedilemedi.',
            ], 500);
        }
    }

    public function destroyTemplate(NotificationTemplate $template)
    {
        try {
            $template->schedules()->delete();
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Şablon silindi.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Şablon silinemedi.',
            ], 500);
        }
    }

    public function sendNow(Request $request)
    {
        try {
            $validated = $request->validate([
                'template_id' => 'nullable|exists:notification_templates,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'channel' => 'required|in:email,sms,fcm',
                'target_users' => 'nullable|string',
                'template_name' => 'nullable|string|max:120',
                'preset_key' => 'nullable|string|max:120',
            ]);

            $template = $this->upsertTemplate([
                'template_id' => $validated['template_id'] ?? null,
                'name' => $validated['template_name'] ?? null,
                'channel' => $validated['channel'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'preset_key' => $validated['preset_key'] ?? null,
            ]);

            $targetUsers = null;
            if (! empty($validated['target_users'])) {
                $targetUsers = array_map('intval', explode(',', $validated['target_users']));
            }

            $result = $this->notificationService->sendNotification(
                $template->title,
                $template->content,
                $template->channel,
                $targetUsers,
                auth()->id(),
                $template->id
            );

            $result['template_id'] = $template->id;

            return response()->json($result, ($result['success'] ?? false) ? 200 : 500);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification flow send error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'template_id' => 'nullable|exists:notification_templates,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'channel' => 'required|in:email,sms,fcm',
                'template_name' => 'nullable|string|max:120',
                'preset_key' => 'nullable|string|max:120',
                'schedule_type' => 'required|in:once,daily',
                'send_at' => 'required_if:schedule_type,once|nullable|date',
                'send_time' => 'required_if:schedule_type,daily|nullable|date_format:H:i',
                'target_users' => 'nullable|string',
            ]);

            $template = $this->upsertTemplate([
                'template_id' => $validated['template_id'] ?? null,
                'name' => $validated['template_name'] ?? null,
                'channel' => $validated['channel'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'preset_key' => $validated['preset_key'] ?? null,
            ]);

            $tz = NotificationFlowHelper::timezone();
            $sendAt = null;
            $sendTime = null;

            if ($validated['schedule_type'] === 'once') {
                $sendAt = Carbon::parse($validated['send_at'], $tz)->utc();
                if ($sendAt->lte(now())) {
                    throw ValidationException::withMessages([
                        'send_at' => ['Gönderim zamanı gelecekte olmalı (TR saati).'],
                    ]);
                }
            } else {
                $sendTime = $validated['send_time'] . ':00';
            }

            $targetUsers = null;
            if (! empty($validated['target_users'])) {
                $targetUsers = array_map('intval', explode(',', $validated['target_users']));
            }

            $schedule = NotificationSchedule::create([
                'notification_template_id' => $template->id,
                'schedule_type' => $validated['schedule_type'],
                'send_at' => $sendAt,
                'send_time' => $sendTime,
                'target_users' => $targetUsers,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $schedule->load('template');

            return response()->json([
                'success' => true,
                'message' => 'Zamanlama kaydedildi.',
                'schedule' => $schedule,
                'template_id' => $template->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification schedule store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Zamanlama kaydedilemedi.',
            ], 500);
        }
    }

    public function toggleSchedule(NotificationSchedule $schedule)
    {
        $schedule->update(['is_active' => ! $schedule->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $schedule->is_active,
            'message' => $schedule->is_active ? 'Zamanlama aktif.' : 'Zamanlama duraklatıldı.',
        ]);
    }

    public function destroySchedule(NotificationSchedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Zamanlama silindi.',
        ]);
    }

    /** @param list<int> $targetIds */
    private function resolveTargetUsersDetail(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return User::notBot()
            ->whereIn('id', $targetIds)
            ->get(['id', 'name', 'email', 'phone', 'device_id'])
            ->map(function (User $user) {
                $label = trim((string) $user->name);

                return [
                    'id' => $user->id,
                    'label' => $label !== '' ? $label : 'Kullanıcı #' . $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array{template_id?: int|null, name?: string|null, channel: string, title: string, content: string, preset_key?: string|null} $data */
    private function upsertTemplate(array $data): NotificationTemplate
    {
        $name = filled($data['name'] ?? null)
            ? (string) $data['name']
            : mb_substr((string) $data['title'], 0, 80);
        $presetKey = filled($data['preset_key'] ?? null) ? (string) $data['preset_key'] : null;

        if (! empty($data['template_id'])) {
            $template = NotificationTemplate::findOrFail($data['template_id']);
            $template->update([
                'name' => $name,
                'channel' => $data['channel'],
                'title' => $data['title'],
                'content' => $data['content'],
                'preset_key' => $presetKey ?: $template->preset_key,
            ]);

            return $template->fresh();
        }

        if ($presetKey) {
            $existing = NotificationTemplate::query()
                ->where('preset_key', $presetKey)
                ->where('channel', $data['channel'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'is_active' => true,
                ]);

                return $existing->fresh();
            }

            return NotificationTemplate::create([
                'name' => $name,
                'channel' => $data['channel'],
                'title' => $data['title'],
                'content' => $data['content'],
                'source' => 'preset',
                'preset_key' => $presetKey,
                'created_by' => auth()->id(),
                'is_active' => true,
            ]);
        }

        return NotificationTemplate::create([
            'name' => $name,
            'channel' => $data['channel'],
            'title' => $data['title'],
            'content' => $data['content'],
            'source' => 'admin',
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);
    }
}
