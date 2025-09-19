<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view permissions')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create permissions')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit permissions')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete permissions')->only(['destroy']);
    }

    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::orderBy('name')->get();
        
        // Permission'ları kategorilere ayır
        $permissionCategories = [
            'users' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'user');
            }),
            'categories' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'categor');
            }),
            'questions' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'question');
            }),
            'tournaments' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'tournament');
            }),
            'general_settings' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'general setting');
            }),
            'permissions' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'permission');
            }),
            'notifications' => $permissions->filter(function($permission) {
                return str_contains($permission->name, 'notification');
            }),
            'other' => $permissions->filter(function($permission) {
                return !str_contains($permission->name, 'user') && 
                       !str_contains($permission->name, 'categor') && 
                       !str_contains($permission->name, 'question') && 
                       !str_contains($permission->name, 'tournament') && 
                       !str_contains($permission->name, 'general setting') && 
                       !str_contains($permission->name, 'permission') && 
                       !str_contains($permission->name, 'notification');
            })
        ];

        return view('admin.permissions.index', compact('roles', 'permissionCategories'));
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        try {
            Log::info('Updating role permissions', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'permissions' => 'array',
                'permissions.*' => 'integer|exists:permissions,id'
            ]);

            Log::info('Validation passed', ['validated' => $validated]);

            // Permission ID'lerini al
            $permissionIds = $validated['permissions'] ?? [];
            $permissions = Permission::whereIn('id', $permissionIds)->get();
            
            Log::info('Found permissions', ['permissions' => $permissions->pluck('name')->toArray()]);
            
            $role->syncPermissions($permissions);

            Log::info('Permissions synced successfully', [
                'role_id' => $role->id,
                'permissions' => $role->permissions->pluck('name')->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol yetkileri başarıyla güncellendi!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Permission validation error: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Permission update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Rol yetkileri güncellenirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'display_name' => 'required|string|max:255',
                'description' => 'nullable|string'
            ]);

            $role = Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol başarıyla oluşturuldu!',
                'role' => $role
            ]);

        } catch (ValidationException $e) {
            Log::error('Role creation validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Role creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Rol oluşturulurken bir hata oluştu!'
            ], 500);
        }
    }

    public function updateRole(Request $request, Role $role)
    {
        try {
            $validated = $request->validate([
                'display_name' => 'required|string|max:255',
                'description' => 'nullable|string'
            ]);

            $role->update([
                'display_name' => $validated['display_name'],
                'description' => $validated['description']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol başarıyla güncellendi!'
            ]);

        } catch (ValidationException $e) {
            Log::error('Role update validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Role update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Rol güncellenirken bir hata oluştu!'
            ], 500);
        }
    }

    public function destroyRole(Role $role)
    {
        try {
            // Admin rolünü silmeyi engelle
            if ($role->name === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin rolü silinemez!'
                ], 403);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rol başarıyla silindi!'
            ]);

        } catch (\Exception $e) {
            Log::error('Role deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Rol silinirken bir hata oluştu!'
            ], 500);
        }
    }
}
