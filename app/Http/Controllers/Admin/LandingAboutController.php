<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingAbout;
use Illuminate\Http\Request;

class LandingAboutController extends Controller
{
    public function index()
    {
        $abouts = LandingAbout::latest()->paginate(20);
        return view('admin.landing.about.index', compact('abouts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'img' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:4096',
        ]);

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ];

        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $dir = 'uploads/landing/about';
            if (!is_dir(public_path($dir))) {
                @mkdir(public_path($dir), 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $data['img'] = '/' . $dir . '/' . $filename;
        }

        LandingAbout::create($data);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt oluşturuldu.']);
        }
        return redirect()->route('admin.landing.about.index')->with('success', 'Kayıt oluşturuldu.');
    }


    public function update(Request $request, LandingAbout $about)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'img' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:4096',
        ]);

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ];

        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $dir = 'uploads/landing/about';
            if (!is_dir(public_path($dir))) {
                @mkdir(public_path($dir), 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $data['img'] = '/' . $dir . '/' . $filename;
        }

        $about->update($data);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt güncellendi.']);
        }
        return redirect()->route('admin.landing.about.index')->with('success', 'Kayıt güncellendi.');
    }

    public function destroy(LandingAbout $about)
    {
        $about->delete();
        
        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt silindi.']);
        }
        return redirect()->route('admin.landing.about.index')->with('success', 'Kayıt silindi.');
    }
}


