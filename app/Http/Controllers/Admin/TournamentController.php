<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class TournamentController extends Controller
{
    public function __construct()
    {
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view tournaments')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create tournaments')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit tournaments')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete tournaments')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Tournament::withCount('tournamentUsers');

        // Filtering
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        $tournaments = $query->latest()->paginate(10);

        return view('admin.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return redirect()->route('admin.tournaments.index');
    }

    public function store(Request $request)
    {
        try {
            $tournamentType = $request->input('tournament_type');

            $rules = [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'quota' => 'required|integer|min:1',
                'rules' => 'nullable|array',
                'awards' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'entry_fee' => 'required|numeric|min:0',
                'difficulty_level' => 'required|in:easy,medium,hard',
                'status' => 'required|in:upcoming,active,finished,cancelled',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_featured' => 'nullable|boolean',
                'tournament_type' => 'required|in:question_based,time_based',
                'min_participants' => 'required|integer|min:1',
                'reward_type' => 'required|in:coin,gift_card,product,discount',
                'reward_value' => 'required|numeric|min:0'
            ];

            // Turnuva türüne göre validation
            if ($tournamentType === 'question_based') {
                $rules['question_count'] = 'required|integer|min:1';
            } else if ($tournamentType === 'time_based') {
                $rules['duration_minutes'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            // Awards JSON string'i decode et
            if ($request->has('awards') && is_string($request->awards)) {
                $validated['awards'] = json_decode($request->awards, true);
            } else if ($request->has('reward_type') && $request->has('reward_value')) {
                $validated['awards'] = [
                    'type' => $request->reward_type,
                    'value' => floatval($request->reward_value)
                ];
            }

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('tournaments', 'public');
            }

            $validated['is_featured'] = $request->has('is_featured');
            $validated['tournament_type'] = $tournamentType;
            $validated['min_participants'] = $request->input('min_participants', 1);

            // Turnuva türüne göre alanları ayarla
            if ($tournamentType === 'question_based') {
                $validated['question_count'] = $request->question_count;
                $validated['duration_minutes'] = 0; // Time based için gerekli değil
            } else if ($tournamentType === 'time_based') {
                $validated['duration_minutes'] = $request->duration_minutes;
                $validated['question_count'] = 0; // Question based için gerekli değil
            }

            Tournament::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Turnuva başarıyla oluşturuldu!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Tournament validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Tournament creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Turnuva oluşturulurken bir hata oluştu!'
            ], 500);
        }
    }

    public function show(Tournament $tournament)
    {
        return redirect()->route('admin.tournaments.index');
    }

    public function edit(Tournament $tournament)
    {
        return redirect()->route('admin.tournaments.index');
    }

    public function update(Request $request, Tournament $tournament)
    {
        try {
            $tournamentType = $request->input('tournament_type', $tournament->tournament_type);

            $rules = [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'quota' => 'required|integer|min:1',
                'rules' => 'nullable|array',
                'awards' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'start_time' => 'required|date_format:H:i',
                'entry_fee' => 'required|numeric|min:0',
                'difficulty_level' => 'required|in:easy,medium,hard',
                'status' => 'required|in:upcoming,active,finished,cancelled',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_featured' => 'nullable|boolean',
                'tournament_type' => 'required|in:question_based,time_based',
                'min_participants' => 'required|integer|min:1',
                'reward_type' => 'required|in:coin,gift_card,product,discount',
                'reward_value' => 'required|numeric|min:0'
            ];

            if ($tournamentType === 'question_based') {
                $rules['question_count'] = 'required|integer|min:1';
            } else if ($tournamentType === 'time_based') {
                $rules['duration_minutes'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            // Awards JSON string'i decode et
            if ($request->has('awards') && is_string($request->awards)) {
                $validated['awards'] = json_decode($request->awards, true);
            } else if ($request->has('reward_type') && $request->has('reward_value')) {
                $validated['awards'] = [
                    'type' => $request->reward_type,
                    'value' => floatval($request->reward_value)
                ];
            }

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('tournaments', 'public');
            }

            $validated['is_featured'] = $request->has('is_featured');
            $validated['tournament_type'] = $tournamentType;
            $validated['min_participants'] = $request->input('min_participants', 1);

            // Turnuva türüne göre alanları ayarla
            if ($tournamentType === 'question_based') {
                $validated['question_count'] = $request->question_count;
                if (!$tournament->tournament_type || $tournament->tournament_type === 'time_based') {
                    $validated['duration_minutes'] = null;
                }
            } else if ($tournamentType === 'time_based') {
                $validated['duration_minutes'] = $request->duration_minutes;
                if (!$tournament->tournament_type || $tournament->tournament_type === 'question_based') {
                    $validated['question_count'] = null;
                }
            }

            $tournament->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Turnuva başarıyla güncellendi!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Tournament validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Tournament update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Turnuva güncellenirken bir hata oluştu!'
            ], 500);
        }
    }

    public function destroy(Tournament $tournament)
    {
        if ($tournament->tournamentUsers()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu turnuvaya katılımcılar bulunduğu için silinemez.'
            ], 422);
        }

        $tournament->delete();

        return response()->json([
            'success' => true,
            'message' => 'Turnuva başarıyla silindi.'
        ]);
    }
}
