<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view categories')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create categories')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit categories')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete categories')->only(['destroy']);
    }

    public function index()
    {
        $categories = Category::withCount('questions')->ordered()->paginate(10);
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return redirect()->route('admin.categories.index');
    }

    public function store(Request $request)
    {        
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);
        
        $rules = [
            'icon' => 'nullable|string|max:100',
            'color_code' => 'nullable|string|max:7',
            'is_active' => 'nullable|in:on,1,true',  
            'sort_order' => 'integer|min:0',
        ];

        // Add validation rules for each supported locale
        foreach ($supportedLocales as $locale) {
            if ($locale === 'tr') {
                $rules["name.{$locale}"] = 'required|string|max:255';
            } else {
                $rules["name.{$locale}"] = 'nullable|string|max:255';
            }
            $rules["description.{$locale}"] = 'nullable|string';
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Category validation failed:', $e->errors());
            return redirect()->back()->withErrors($e->validator)->withInput();
        }

        $category = new Category();
        
        // Set translations
        foreach ($supportedLocales as $locale) {
            if ($request->filled("name.{$locale}")) {
                $category->setTranslation('name', $locale, $request->input("name.{$locale}"));
            }
            if ($request->filled("description.{$locale}")) {
                $category->setTranslation('description', $locale, $request->input("description.{$locale}"));
            }
        }

        $category->icon = $request->icon;
        $category->color_code = $request->color_code;
        $category->is_active = $request->has('is_active') && $request->is_active !== null;
        $category->sort_order = $request->integer('sort_order', 0);
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', 'Kategori başarıyla oluşturuldu.');
    }

    public function show(Category $category)
    {
        return redirect()->route('admin.categories.index');
    }

    public function edit(Category $category)
    {
        return redirect()->route('admin.categories.index');
    }

    public function update(Request $request, Category $category)
    {
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);
        
        $rules = [
            'icon' => 'nullable|string|max:100',
            'color_code' => 'nullable|string|max:7',
            'is_active' => 'nullable|in:on,1,true',
            'sort_order' => 'integer|min:0',
        ];

        // Add validation rules for each supported locale
        foreach ($supportedLocales as $locale) {
            if ($locale === 'tr') {
                $rules["name.{$locale}"] = 'required|string|max:255';
            } else {
                $rules["name.{$locale}"] = 'nullable|string|max:255';
            }
            $rules["description.{$locale}"] = 'nullable|string';
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        }

        // Set translations
        foreach ($supportedLocales as $locale) {
            if ($request->filled("name.{$locale}")) {
                $category->setTranslation('name', $locale, $request->input("name.{$locale}"));
            }
            if ($request->filled("description.{$locale}")) {
                $category->setTranslation('description', $locale, $request->input("description.{$locale}"));
            }
        }

        $category->icon = $request->icon;
        $category->color_code = $request->color_code;
        $category->is_active = $request->has('is_active') && $request->is_active !== null;
        $category->sort_order = $request->integer('sort_order', 0);
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', 'Kategori başarıyla güncellendi.');
    }

    public function destroy(Category $category)
    {
        // Check if category has questions
        if ($category->questions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu kategoriye ait sorular bulunduğu için silinemez.'
            ], 422);
        }

        $category->delete();
        return response()->json([
            'success' => true,
            'message' => 'Kategori başarıyla silindi.'
        ]);
    }
}
