<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\WebhookService;
use App\Models\Duel;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view users')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create users')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit users')->only(['edit', 'update', 'toggleStatus']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete users')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        $onlineUserIds = app(WebhookService::class)->getOnlineUserIds();

        $query = User::notBot()
            ->with(['package', 'roles', 'avatarModel'])
            ->withCount('rewardRequests')
            ->addSelect([
                'finished_duels_count' => Duel::selectRaw('COUNT(*)')
                    ->where('status', 'finished')
                    ->where(function ($q) {
                        $q->whereColumn('challenger_id', 'users.id')
                            ->orWhereColumn('opponent_id', 'users.id');
                    }),
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('status')) {
            $query->where('account_status', $request->status);
        }

        if ($request->filled('online') && $request->online == '1') {
            if (empty($onlineUserIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', $onlineUserIds);
            }
        }

        if ($request->filled('premium') && $request->premium == '1') {
            $query->where('is_premium', true);
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        if ($request->filled('sort_coins') && in_array($request->sort_coins, ['asc', 'desc'], true)) {
            $query->orderBy('coins', $request->sort_coins);
        } else {
            $query->latest();
        }

        $users = $query->paginate($perPage)->withQueryString();

        // Premium paket adı users.package_id'de yok; son premium ödemenin metadata'sından al
        $premiumNames = Payment::query()
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->where('status', 'completed')
            ->where('metadata->type', 'premium')
            ->orderByDesc('id')
            ->get(['user_id', 'metadata'])
            ->groupBy('user_id')
            ->map(function ($rows) {
                $meta = $rows->first()->metadata ?? [];
                return $meta['package_snapshot']['name'] ?? null;
            });

        $onlineLookup = array_fill_keys($onlineUserIds, true);

        $users->getCollection()->transform(function (User $user) use ($premiumNames, $onlineLookup) {
            $user->setAttribute(
                'premium_package_name',
                $premiumNames[$user->id] ?? $user->package?->title
            );
            $user->setAttribute('is_online', isset($onlineLookup[(int) $user->id]));
            return $user;
        });

        $roles = Role::all();
        $packages = Package::active()->get();

        $botIds = User::bots()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $onlineHumans = array_values(array_filter(
            array_map('intval', $onlineUserIds),
            fn (int $id) => !in_array($id, $botIds, true)
        ));

        $summary = [
            'total' => User::notBot()->count(),
            'online' => count($onlineHumans),
            'suspended' => User::notBot()->where('account_status', 'suspended')->count(),
            'premium' => User::notBot()->where('is_premium', true)->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'packages', 'summary', 'perPage', 'onlineUserIds'));
    }

    public function create()
    {
        return redirect()->route('admin.users.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|exists:roles,name',
            'phone' => 'nullable|string|max:20',
            'package_id' => 'nullable|exists:packages,id',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->phone = $request->phone;
        $user->package_id = $request->package_id;
        $user->account_status = 'active';
        $user->coins = 0;
        $user->save();

        $user->assignRole($request->role);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kullanıcı başarıyla oluşturuldu.']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı başarıyla oluşturuldu.');
    }

    public function show(User $user)
    {
        return redirect()->route('admin.users.index');
    }

    public function edit(User $user)
    {
        return redirect()->route('admin.users.index');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6',
            'role' => 'required|exists:roles,name',
            'phone' => 'nullable|string|max:20',
            'account_status' => 'required|in:active,suspended,pending',
            'coins' => 'required|integer|min:0',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->phone = $request->phone;
        $user->package_id = $request->package_id;
        $user->account_status = $request->account_status;
        $user->coins = $request->coins;
        $user->save();

        $user->syncRoles([$request->role]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kullanıcı başarıyla güncellendi.']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı başarıyla güncellendi.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kendi hesabınızın durumunu buradan değiştiremezsiniz.',
            ], 400);
        }

        if ($user->account_status === 'suspended') {
            $user->account_status = 'active';
            $message = 'Kullanıcı hesabı aktif edildi.';
        } else {
            $user->account_status = 'suspended';
            $message = 'Kullanıcı askıya alındı.';
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'account_status' => $user->account_status,
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }

        $user->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kullanıcı başarıyla silindi.']);
        }

        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı başarıyla silindi.');
    }
}
