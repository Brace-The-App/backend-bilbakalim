<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingBenefit;
use Illuminate\Http\Request;

class LandingBenefitController extends Controller
{
    public function index()
    {
        $benefits = LandingBenefit::latest()->paginate(20);
        return view('admin.landing.benefits.index', compact('benefits'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        LandingBenefit::create($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt oluşturuldu.']);
        }
        return redirect()->route('admin.landing.benefits.index')->with('success', 'Kayıt oluşturuldu.');
    }


    public function update(Request $request, LandingBenefit $benefit)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $benefit->update($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt güncellendi.']);
        }
        return redirect()->route('admin.landing.benefits.index')->with('success', 'Kayıt güncellendi.');
    }

    public function destroy(LandingBenefit $benefit)
    {
        $benefit->delete();
        
        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt silindi.']);
        }
        return redirect()->route('admin.landing.benefits.index')->with('success', 'Kayıt silindi.');
    }
}


