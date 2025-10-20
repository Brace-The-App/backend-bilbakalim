<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingFeature;
use Illuminate\Http\Request;

class LandingFeatureController extends Controller
{
    public function index()
    {
        $features = LandingFeature::latest()->paginate(20);
        return view('admin.landing.features.index', compact('features'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        LandingFeature::create($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt oluşturuldu.']);
        }
        return redirect()->route('admin.landing.features.index')->with('success', 'Kayıt oluşturuldu.');
    }


    public function update(Request $request, LandingFeature $feature)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $feature->update($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt güncellendi.']);
        }
        return redirect()->route('admin.landing.features.index')->with('success', 'Kayıt güncellendi.');
    }

    public function destroy(LandingFeature $feature)
    {
        $feature->delete();
        
        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt silindi.']);
        }
        return redirect()->route('admin.landing.features.index')->with('success', 'Kayıt silindi.');
    }
}


