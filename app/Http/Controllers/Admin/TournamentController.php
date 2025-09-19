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
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'quota' => 'required|integer|min:1',
                'rules' => 'nullable|array',
                'awards' => 'nullable|array',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
                'start_time' => 'required|date_format:H:i',
                'duration_minutes' => 'required|integer|min:1',
                'entry_fee' => 'required|numeric|min:0',
                'question_count' => 'required|integer|min:1',
                'difficulty_level' => 'required|in:easy,medium,hard',
                'status' => 'required|in:upcoming,active,finished,cancelled',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_featured' => 'nullable|boolean'
            ]);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('tournaments', 'public');
            }

            $validated['is_featured'] = $request->has('is_featured');

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
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'quota' => 'required|integer|min:1',
                'rules' => 'nullable|array',
                'awards' => 'nullable|array',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'start_time' => 'required|date_format:H:i',
                'duration_minutes' => 'required|integer|min:1',
                'entry_fee' => 'required|numeric|min:0',
                'question_count' => 'required|integer|min:1',
                'difficulty_level' => 'required|in:easy,medium,hard',
                'status' => 'required|in:upcoming,active,finished,cancelled',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_featured' => 'nullable|boolean'
            ]);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('tournaments', 'public');
            }

            $validated['is_featured'] = $request->has('is_featured');

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
