<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class GeneralSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view general settings')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create general settings')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit general settings')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete general settings')->only(['destroy']);
    }

    public function index()
    {
        $settings = GeneralSetting::orderBy('key')->get();
        return view('admin.general-settings.index', compact('settings'));
    }

    public function create()
    {
        return redirect()->route('admin.general-settings.index');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string|max:255|unique:general_settings,key',
                'value' => 'nullable|string',
                'type' => 'required|in:text,number,boolean,json',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean'
            ]);

            $validated['is_active'] = $request->boolean('is_active');

            GeneralSetting::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ayar başarıyla oluşturuldu!'
            ]);

        } catch (ValidationException $e) {
            Log::error('GeneralSetting validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('GeneralSetting creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ayar oluşturulurken bir hata oluştu!'
            ], 500);
        }
    }

    public function show(GeneralSetting $generalSetting)
    {
        return view('admin.general-settings.show', compact('generalSetting'));
    }

    public function edit(GeneralSetting $generalSetting)
    {
        return redirect()->route('admin.general-settings.index');
    }

    public function update(Request $request, GeneralSetting $generalSetting)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string|max:255|unique:general_settings,key,' . $generalSetting->id,
                'value' => 'nullable|string',
                'type' => 'required|in:text,number,boolean,json',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean'
            ]);
            

            $validated['is_active'] = $request->boolean('is_active');

            $generalSetting->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ayar başarıyla güncellendi!'
            ]);

        } catch (ValidationException $e) {
            Log::error('GeneralSetting validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('GeneralSetting update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ayar güncellenirken bir hata oluştu!'
            ], 500);
        }
    }

    public function destroy(GeneralSetting $generalSetting)
    {
        $generalSetting->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Ayar başarıyla silindi.'
        ]);
    }

    public function uploadLogo(Request $request)
    {
        try {
            Log::info('Logo upload request received', ['request' => $request->all()]);
            
            $validated = $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            Log::info('Logo validation passed');

            $path = $request->file('logo')->store('settings', 'public');
            Log::info('Logo stored at path: ' . $path);
            
            $setting = GeneralSetting::set('site_logo', $path, 'text', 'Site logosu (dosya yolu)');
            Log::info('GeneralSetting created/updated', ['setting' => $setting]);

            return response()->json([
                'success' => true,
                'message' => 'Logo başarıyla yüklendi!',
                'path' => $path
            ]);

        } catch (ValidationException $e) {
            Log::error('Logo validation error', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Logo upload error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Logo yüklenirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadFavicon(Request $request)
    {
        try {
            Log::info('Favicon upload request received', ['request' => $request->all()]);
            
            $validated = $request->validate([
                'favicon' => 'required|image|mimes:ico,png,jpg|max:512'
            ]);

            Log::info('Favicon validation passed');

            $path = $request->file('favicon')->store('settings', 'public');
            Log::info('Favicon stored at path: ' . $path);
            
            $setting = GeneralSetting::set('site_favicon', $path, 'text', 'Site favicon (dosya yolu)');
            Log::info('GeneralSetting created/updated', ['setting' => $setting]);

            return response()->json([
                'success' => true,
                'message' => 'Favicon başarıyla yüklendi!',
                'path' => $path
            ]);

        } catch (ValidationException $e) {
            Log::error('Favicon validation error', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Favicon upload error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Favicon yüklenirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}
