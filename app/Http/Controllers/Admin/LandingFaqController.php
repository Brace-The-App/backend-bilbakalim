<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingFaq;
use Illuminate\Http\Request;

class LandingFaqController extends Controller
{
    public function index()
    {
        $faqs = LandingFaq::latest()->paginate(20);
        return view('admin.landing.faqs.index', compact('faqs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        LandingFaq::create($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt oluşturuldu.']);
        }
        return redirect()->route('admin.landing.faqs.index')->with('success', 'Kayıt oluşturuldu.');
    }


    public function update(Request $request, LandingFaq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        $faq->update($validated);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt güncellendi.']);
        }
        return redirect()->route('admin.landing.faqs.index')->with('success', 'Kayıt güncellendi.');
    }

    public function destroy(LandingFaq $faq)
    {
        $faq->delete();
        
        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt silindi.']);
        }
        return redirect()->route('admin.landing.faqs.index')->with('success', 'Kayıt silindi.');
    }
}


