<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingTestimonial;
use Illuminate\Http\Request;

class LandingTestimonialController extends Controller
{
    public function index()
    {
        $testimonials = LandingTestimonial::latest()->paginate(20);
        return view('admin.landing.testimonials.index', compact('testimonials'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_name' => 'required|string|max:255',
            'comment' => 'required|string',
            'profile_img' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:4096',
        ]);

        $data = [
            'user_name' => $validated['user_name'],
            'comment' => $validated['comment'],
        ];

        if ($request->hasFile('profile_img')) {
            $file = $request->file('profile_img');
            $dir = 'uploads/landing/testimonials';
            if (!is_dir(public_path($dir))) {
                @mkdir(public_path($dir), 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $data['profile_img'] = '/' . $dir . '/' . $filename;
        }

        LandingTestimonial::create($data);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt oluşturuldu.']);
        }
        return redirect()->route('admin.landing.testimonials.index')->with('success', 'Kayıt oluşturuldu.');
    }


    public function update(Request $request, LandingTestimonial $testimonial)
    {
        $validated = $request->validate([
            'user_name' => 'required|string|max:255',
            'comment' => 'required|string',
            'profile_img' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:4096',
        ]);

        $data = [
            'user_name' => $validated['user_name'],
            'comment' => $validated['comment'],
        ];

        if ($request->hasFile('profile_img')) {
            $file = $request->file('profile_img');
            $dir = 'uploads/landing/testimonials';
            if (!is_dir(public_path($dir))) {
                @mkdir(public_path($dir), 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $data['profile_img'] = '/' . $dir . '/' . $filename;
        }

        $testimonial->update($data);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt güncellendi.']);
        }
        return redirect()->route('admin.landing.testimonials.index')->with('success', 'Kayıt güncellendi.');
    }

    public function destroy(LandingTestimonial $testimonial)
    {
        $testimonial->delete();
        
        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Kayıt silindi.']);
        }
        return redirect()->route('admin.landing.testimonials.index')->with('success', 'Kayıt silindi.');
    }
}


