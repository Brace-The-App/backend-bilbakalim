@extends('admin.layouts.app')

@section('title', 'Bildirim Yönetimi')

@push('styles')
<style>
.page-title {
    margin-top: 2rem !important;
    padding-top: 1rem !important;
}
</style>
@endpush

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6"><h3>Bildirim Yönetimi</h3></div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Bildirim Yönetimi</li>
            </ol>
        </div>
    </div>
</div>

    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Bildirim Yönetimi</h4>
                    @can('create notifications')
                    <div class="card-header-right">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#notificationSendModal">
                            <i data-feather="send"></i> Bildirim Gönder
                        </button>
                    </div>
                    @endcan
                </div>
                <div class="card-body">
                    <!-- Filtreler -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Başlık veya mesaj ara...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="typeFilter">
                                <option value="">Tüm Tipler</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="fcm">FCM</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="statusFilter">
                                <option value="">Tüm Durumlar</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary" onclick="clearFilters()">Temizle</button>
                        </div>
                    </div>

                    <!-- Bildirimler Tablosu -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Başlık</th>
                                    <th >İçerik</th>
                                    <th >Tip</th>
                                    <th >Durum</th>
                                    <th >Oluşturan</th>
                                    <th >Gönderim Tarihi</th>
                                    <th >İşlemler</th>
                                </tr>
                            </thead>
                            <tbody id="notificationsTableBody">
                                @foreach($notifications as $notification)
                                <tr>
                                    <td>{{ $notification->title }}</td>
                                    <td>{{ Str::limit($notification->content, 50) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $notification->type_color }}" >
                                            {{ ucfirst($notification->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($notification->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                        @else
                                        <span class="badge bg-danger">Pasif</span>
                                        @endif
                                    </td>
                                    <td>{{ $notification->creator->name ?? '-' }}</td>
                                    <td>
                                        @if($notification->send_at)
                                            {{ \Carbon\Carbon::parse($notification->send_at)->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">Hemen</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#notificationShowModal"
                                                data-id="{{ $notification->id }}"
                                                data-title="{{ $notification->title }}"
                                                data-content="{{ $notification->content }}"
                                                data-type="{{ $notification->type }}"
                                                data-send-at="{{ $notification->send_at }}"
                                                data-active="{{ $notification->is_active }}">Görüntüle</button>
                                        @can('edit notifications')
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#notificationEditModal"
                                                data-id="{{ $notification->id }}"
                                                data-title="{{ $notification->title }}"
                                                data-content="{{ $notification->content }}"
                                                data-type="{{ $notification->type }}"
                                                data-send-at="{{ $notification->send_at }}"
                                                data-active="{{ $notification->is_active }}">Düzenle</button>
                                        @endcan
                                        @can('delete notifications')
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteNotification({{ $notification->id }}, '{{ $notification->title }}')">Sil</button>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="notificationsPagination" class="d-flex justify-content-center mt-3">
                        {{ $notifications->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Bildirim Gönderme Modal -->
<div class="modal fade" id="notificationSendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bildirim Gönder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notificationSendForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="send-title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="send-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="send-content" class="form-label">İçerik <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="send-content" name="content" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="send-type" class="form-label">Gönderim Tipi <span class="text-danger">*</span></label>
                                <select class="form-control" id="send-type" name="type" required>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="fcm">FCM (Push Notification)</option>
                                </select>
                                <div class="form-text" id="send-type-help">
                                    Email: Tüm kullanıcılara email gönderir
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="send-target-users" class="form-label">Hedef Kullanıcılar</label>
                                <input type="text" class="form-control" id="send-target-users" name="target_users" placeholder="1,2,3 (virgülle ayırın, boş bırakırsanız tüm kullanıcılara gider)">
                                <div class="form-text">Boş bırakırsanız tüm kullanıcılara gönderilir</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Gönder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bildirim Oluşturma Modal -->
<div class="modal fade" id="notificationCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Bildirim Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notificationCreateForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create-title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="create-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="create-content" class="form-label">İçerik <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="create-content" name="content" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="create-type" class="form-label">Tip <span class="text-danger">*</span></label>
                                <select class="form-control" id="create-type" name="type" required>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="fcm">FCM</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="create-send-at" class="form-label">Gönderim Tarihi</label>
                                <input type="datetime-local" class="form-control" id="create-send-at" name="send_at">
                                <div class="form-text">Boş bırakırsanız hemen gönderilir</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="create-active" name="is_active" checked>
                            <label class="form-check-label" for="create-active">
                                Aktif
                            </label>
                        </div>
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

<!-- Bildirim Düzenleme Modal -->
<div class="modal fade" id="notificationEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bildirim Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notificationEditForm">
                <div class="modal-body">
                    <input type="hidden" id="edit-id" name="notification_id">
                    <div class="mb-3">
                        <label for="edit-title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-content" class="form-label">İçerik <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit-content" name="content" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-type" class="form-label">Tip <span class="text-danger">*</span></label>
                                <select class="form-control" id="edit-type" name="type" required>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="fcm">FCM</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit-send-at" class="form-label">Gönderim Tarihi</label>
                                <input type="datetime-local" class="form-control" id="edit-send-at" name="send_at">
                                <div class="form-text">Boş bırakırsanız hemen gönderilir</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit-active" name="is_active">
                            <label class="form-check-label" for="edit-active">
                                Aktif
                            </label>
                        </div>
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

<!-- Bildirim Görüntüleme Modal -->
<div class="modal fade" id="notificationShowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bildirim Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Başlık</label>
                            <p id="show-title" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label fw-bold">İçerik</label>
                            <p id="show-content" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tip</label>
                            <p id="show-type" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Gönderim Tarihi</label>
                            <p id="show-send-at" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Durum</label>
                            <p id="show-status" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Bildirim Gönderme
    $('#notificationSendForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        $.ajax({
            url: '/private/lesley/admin/notifications/send',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#notificationSendModal').modal('hide');
                    toastr.success(response.message + ' (' + response.sent_count + ' kişiye gönderildi)');
                    loadNotifications();
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
                    toastr.error('Bildirim gönderilirken bir hata oluştu!');
                }
            }
        });
    });

    // Gönderim tipi değiştiğinde yardım metnini güncelle
    $('#send-type').on('change', function() {
        var type = $(this).val();
        var helpText = $('#send-type-help');
        
        switch(type) {
            case 'email':
                helpText.text('Email: Tüm kullanıcılara email gönderir');
                break;
            case 'sms':
                helpText.text('SMS: Tüm kullanıcılara SMS gönderir (telefon numarası olan)');
                break;
            case 'fcm':
                helpText.text('FCM: Sadece role 3 ve device_id olan kullanıcılara push notification gönderir');
                break;
        }
    });


    // Bildirim Düzenleme Modal
    $('#notificationEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var content = button.data('content');
        var type = button.data('type');
        var sendAt = button.data('send-at');
        var active = button.data('active');
        
        $('#edit-id').val(id);
        $('#edit-title').val(title);
        $('#edit-content').val(content);
        $('#edit-type').val(type);
        $('#edit-send-at').val(sendAt);
        $('#edit-active').prop('checked', active == 1);
    });

    // Bildirim Güncelleme
    $('#notificationEditForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.set('is_active', $('#edit-active').is(':checked') ? '1' : '0');
        var notificationId = $('#edit-id').val();
        
        // Debug: Form verilerini kontrol et
        console.log('Edit Form Data:');
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        $.ajax({
            url: '/private/lesley/admin/notifications/' + notificationId,
            type: 'PUT',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#notificationEditModal').modal('hide');
                    toastr.success(response.message);
                    loadNotifications();
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
                    toastr.error('Bildirim güncellenirken bir hata oluştu!');
                }
            }
        });
    });

    // Bildirim Görüntüleme Modal
    $('#notificationShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var title = button.data('title');
        var content = button.data('content');
        var type = button.data('type');
        var sendAt = button.data('send-at');
        var active = button.data('active');
        
        $('#show-title').text(title);
        $('#show-content').text(content);
        $('#show-type').html('<span class="badge bg-' + getTypeColor(type) + '">' + type.toUpperCase() + '</span>');
        $('#show-send-at').text(sendAt ? new Date(sendAt).toLocaleString('tr-TR') : 'Hemen');
        $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');
    });

    // Filtreler
    $('#searchInput, #typeFilter, #statusFilter').on('change keyup', function() {
        loadNotifications();
    });

    // Pagination
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadNotifications(page);
    });
});

// Bildirim Yükleme
function loadNotifications(page = 1) {
    var search = $('#searchInput').val();
    var type = $('#typeFilter').val();
    var status = $('#statusFilter').val();
    
    $.ajax({
        url: '/private/lesley/admin/notifications',
        type: 'GET',
        data: { 
            page: page,
            search: search,
            type: type,
            status: status
        },
        success: function(response) {
            var tableBody = $(response).find('#notificationsTableBody').html();
            var pagination = $(response).find('#notificationsPagination').html();
            
            $('#notificationsTableBody').html(tableBody);
            $('#notificationsPagination').html(pagination);
            
            // Feather icon'ları yeniden initialize et
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        },
        error: function() {
            toastr.error('Veriler yüklenirken bir hata oluştu!');
        }
    });
}

// Filtreleri Temizle
function clearFilters() {
    $('#searchInput').val('');
    $('#typeFilter').val('');
    $('#statusFilter').val('');
    loadNotifications();
}

// Tip Rengi
function getTypeColor(type) {
    switch(type) {
        case 'email': return 'info';
        case 'sms': return 'warning';
        case 'fcm': return 'dark';
        default: return 'secondary';
    }
}

// Bildirim Silme
function deleteNotification(id, title) {
    if (confirm('"' + title + '" bildirimini silmek istediğinizden emin misiniz?')) {
        $.ajax({
            url: '/private/lesley/admin/notifications/' + id,
            type: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    loadNotifications();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Bildirim silinirken bir hata oluştu!');
            }
        });
    }
}
</script>
@endpush
@include('admin.layouts.footer')