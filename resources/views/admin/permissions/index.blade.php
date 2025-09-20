@extends('admin.layouts.app')

@section('title', 'Yetki Yönetimi')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Yetki Yönetimi</h4>
                    @can('create permissions')
                    <div class="card-header-right">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleCreateModal">
                            <i data-feather="plus"></i> Yeni Rol
                        </button>
                    </div>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($roles as $role)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">{{ $role->display_name ?? ucfirst($role->name) }}</h6>
                                    <div class="btn-group btn-group-sm">
                                     
                                       
                                    </div>
                                </div>
                                <div class="card-body">
                                    @if($role->description)
                                        <p class="card-text text-muted small">{{ $role->description }}</p>
                                    @endif
                                    
                                    <h6 class="mt-3 mb-2">Yetkiler:</h6>
                                    <div class="permission-list">
                                        @if($role->permissions->count() > 0)
                                            @foreach($permissionCategories as $category => $permissions)
                                                @if($permissions->count() > 0)
                                                    @php
                                                        $rolePermissions = $role->permissions->whereIn('id', $permissions->pluck('id'));
                                                    @endphp
                                                    @if($rolePermissions->count() > 0)
                                                        <div class="permission-category mb-2">
                                                            <strong class="text-primary">{{ ucfirst(str_replace('_', ' ', $category)) }}:</strong>
                                                            <div class="permission-items mt-1">
                                                                @foreach($rolePermissions as $permission)
                                                                    <span class="badge bg-success me-1 mb-1">{{ $permission->name }}</span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-muted">Hiç yetki atanmamış</span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#permissionModal"
                                                data-role-id="{{ $role->id }}"
                                                data-role-name="{{ $role->name }}"
                                                data-role-permissions="{{ $role->permissions->pluck('id')->toJson() }}">
                                            <i data-feather="settings"></i> Yetkileri Düzenle
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rol Oluşturma Modal -->
<div class="modal fade" id="roleCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Rol Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="roleCreateForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create-name" class="form-label">Rol Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="create-name" name="name" required>
                        <div class="form-text">Küçük harf ve alt çizgi kullanın (örn: personel, uye)</div>
                    </div>
                    <div class="mb-3">
                        <label for="create-display-name" class="form-label">Görünen Ad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="create-display-name" name="display_name" required>
                        <div class="form-text">Kullanıcıların göreceği ad (örn: Personel, Üye)</div>
                    </div>
                    <div class="mb-3">
                        <label for="create-description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="create-description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rol Düzenleme Modal -->
<div class="modal fade" id="roleEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rol Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="roleEditForm">
                <div class="modal-body">
                    <input type="hidden" id="edit-role-id" name="role_id">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Rol Adı</label>
                        <input type="text" class="form-control" id="edit-name" name="name" readonly>
                        <div class="form-text">Rol adı değiştirilemez</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-display-name" class="form-label">Görünen Ad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-display-name" name="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Yetki Düzenleme Modal -->
<div class="modal fade" id="permissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yetkileri Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="permissionForm">
                <div class="modal-body">
                    <input type="hidden" id="permission-role-id" name="role_id">
                    <div class="row">
                        @foreach($permissionCategories as $category => $permissions)
                            @if($permissions->count() > 0)
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">{{ ucfirst(str_replace('_', ' ', $category)) }}</h6>
                                        </div>
                                        <div class="card-body">
                                            @foreach($permissions as $permission)
                                                <div class="form-check">
                                                    <input class="form-check-input permission-checkbox" 
                                                           type="checkbox" 
                                                           name="permissions[]" 
                                                           value="{{ $permission->id }}" 
                                                           id="permission-{{ $permission->id }}">
                                                    <label class="form-check-label" for="permission-{{ $permission->id }}">
                                                        {{ $permission->name }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Yetkileri Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Rol Oluşturma
    $('#roleCreateForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: '/private/lesley/admin/permissions/roles',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#roleCreateModal').modal('hide');
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        var errorMessages = [];
                        $.each(errors, function(key, value) {
                            errorMessages.push(value[0]);
                        });
                        toastr.error(errorMessages.join('<br>'));
                    }
                } else {
                    toastr.error('Rol oluşturulurken bir hata oluştu!');
                }
            }
        });
    });

    // Rol Düzenleme Modal
    $('#roleEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');
        var displayName = button.data('display-name');
        var description = button.data('description');
        
        $('#edit-role-id').val(id);
        $('#edit-name').val(name);
        $('#edit-display-name').val(displayName);
        $('#edit-description').val(description);
    });

    // Rol Güncelleme
    $('#roleEditForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var roleId = $('#edit-role-id').val();
        
        $.ajax({
            url: '/private/lesley/admin/permissions/roles/' + roleId,
            type: 'PUT',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#roleEditModal').modal('hide');
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        var errorMessages = [];
                        $.each(errors, function(key, value) {
                            errorMessages.push(value[0]);
                        });
                        toastr.error(errorMessages.join('<br>'));
                    }
                } else {
                    toastr.error('Rol güncellenirken bir hata oluştu!');
                }
            }
        });
    });

    // Yetki Düzenleme Modal
    $('#permissionModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var roleId = button.data('role-id');
        var roleName = button.data('role-name');
        var rolePermissions = button.data('role-permissions');
        
        $('#permission-role-id').val(roleId);
        $('.modal-title').text('Yetkileri Düzenle - ' + roleName);
        
        // Tüm checkbox'ları temizle
        $('.permission-checkbox').prop('checked', false);
        
        // Rol yetkilerini işaretle
        if (rolePermissions && rolePermissions.length > 0) {
            rolePermissions.forEach(function(permissionId) {
                $('#permission-' + permissionId).prop('checked', true);
            });
        }
    });

    // Yetki Güncelleme
    $('#permissionForm').on('submit', function(e) {
        e.preventDefault();
        
        var roleId = $('#permission-role-id').val();
        
        // Checkbox değerlerini topla
        var selectedPermissions = [];
        $('.permission-checkbox:checked').each(function() {
            selectedPermissions.push(parseInt($(this).val()));
        });
        
        console.log('Updating permissions for role:', roleId);
        console.log('Selected permissions:', selectedPermissions);
        
        // FormData yerine JSON gönder
        $.ajax({
            url: '/private/lesley/admin/permissions/roles/' + roleId + '/permissions',
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                permissions: selectedPermissions
            },
            success: function(response) {
                console.log('Permission update response:', response);
                if (response.success) {
                    $('#permissionModal').modal('hide');
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                console.log('Permission update error:', xhr);
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        var errorMessages = [];
                        $.each(errors, function(key, value) {
                            errorMessages.push(value[0]);
                        });
                        toastr.error(errorMessages.join('<br>'));
                    }
                } else {
                    toastr.error('Yetkiler güncellenirken bir hata oluştu!');
                }
            }
        });
    });
});

// Rol Silme
function deleteRole(roleId, roleName) {
    if (confirm('"' + roleName + '" rolünü silmek istediğinizden emin misiniz?')) {
        $.ajax({
            url: '/private/lesley/admin/permissions/roles/' + roleId,
            type: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 403) {
                    toastr.error('Bu rol silinemez!');
                } else {
                    toastr.error('Rol silinirken bir hata oluştu!');
                }
            }
        });
    }
}
</script>
@endpush
