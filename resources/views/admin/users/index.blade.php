@extends('admin.layouts.app')

@section('title', 'Kullanıcılar')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Kullanıcılar</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Kullanıcılar</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Kullanıcı Listesi</h5>
                @can('create users')
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userCreateModal">Yeni Kullanıcı</a>
                </div>
                @endcan
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-2 align-items-end mb-3">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Ara</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i data-feather="search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Ara..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Rol</label>
                        <select name="role" class="form-select">
                            <option value="">Tüm Roller</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Durum</label>
                        <select name="status" class="form-select">
                            <option value="">Tüm Durumlar</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Askıya Alınmış</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Beklemede</option>
                        </select>
                    </div>
                    <div class="col-md-12 col-lg-3 text-md-start text-lg-end">
                        <button type="submit" class="btn btn-primary me-2"><i data-feather="filter" class="me-1"></i>Filtrele</button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Temizle</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>İsim</th>
                                <th>Email</th>
                                <th>Telefon</th>
                                <th>Rol</th>
                                <th>Coin</th>
                                <th>Durum</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?: '-' }}</td>
                                <td>
                                    <span class="badge bg-primary">
                                        {{ $user->getRoleNames()->first() ?? 'Rol Yok' }}
                                    </span>
                                </td>
                                <td>{{ $user->total_coins ?? 0 }}</td>
                                <td>
                                    @switch($user->account_status)
                                        @case('active')
                                            <span class="badge bg-success">Aktif</span>
                                            @break
                                        @case('suspended')
                                            <span class="badge bg-danger">Askıya Alınmış</span>
                                            @break
                                        @case('pending')
                                            <span class="badge bg-warning">Beklemede</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ $user->account_status }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $user->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#userShowModal"
                                            data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}"
                                            data-phone="{{ $user->phone }}" data-coins="{{ $user->total_coins ?? 0 }}" data-status="{{ $user->account_status }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#userEditModal"
                                            data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}"
                                            data-phone="{{ $user->phone }}" data-coins="{{ $user->total_coins ?? 0 }}" data-status="{{ $user->account_status }}"
                                            data-role="{{ $user->roles->first()->name ?? '' }}" data-package="{{ $user->package_id }}">Düzenle</button>
                                    @can('delete users')
                                    @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Emin misiniz?')">Sil</button>
                                    </form>
                                    @endif
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-3">
                    {{ $users->withQueryString()->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="userCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Kullanıcı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label">İsim</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Şifre</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Rol</label>
            <select name="role" class="form-select" required>
              @foreach($roles as $role)
                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Paket</label>
            <select name="package_id" class="form-select">
              <option value="">Seçiniz</option>
              @foreach($packages as $package)
                <option value="{{ $package->id }}">{{ $package->title }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Show User Modal -->
<div class="modal fade" id="userShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcı Detayı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-4">İsim</dt><dd class="col-8" id="show-name"></dd>
          <dt class="col-4">Email</dt><dd class="col-8" id="show-email"></dd>
          <dt class="col-4">Telefon</dt><dd class="col-8" id="show-phone"></dd>
          <dt class="col-4">Coin</dt><dd class="col-8" id="show-coins"></dd>
          <dt class="col-4">Durum</dt><dd class="col-8" id="show-status"></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="userEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcıyı Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="userEditForm">
        @csrf
        @method('PUT')
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label">İsim</label>
            <input type="text" name="name" id="edit-name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="edit-email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" id="edit-phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Durum</label>
            <select name="account_status" id="edit-status" class="form-select">
              <option value="active">Aktif</option>
              <option value="suspended">Askıya Alınmış</option>
              <option value="pending">Beklemede</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Toplam Coin</label>
            <input type="number" min="0" name="total_coins" id="edit-coins" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Rol</label>
            <select name="role" id="edit-role" class="form-select" required>
              @foreach($roles as $role)
                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Paket</label>
            <select name="package_id" id="edit-package" class="form-select">
              <option value="">Paket Seçin</option>
              @foreach($packages as $package)
                <option value="{{ $package->id }}">{{ $package->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Yeni Şifre (Opsiyonel)</label>
            <input type="password" name="password" id="edit-password" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Toastr configuration
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Create User Form
    $('#userCreateModal form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = form.serialize();
        var url = form.attr('action');
        
        // Clear previous errors
        form.find('.alert-danger').remove();
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#userCreateModal').modal('hide');
                toastr.success('Kullanıcı başarıyla oluşturuldu!');
                // Reload data without page refresh
                loadUsers();
                // Reset form
                $('#userCreateModal form')[0].reset();
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
                    toastr.error('Bir hata oluştu!');
                }
            }
        });
    });
    
    // Edit User Form
    $('#userEditModal form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = form.serialize();
        var url = form.attr('action');
        
        // Clear previous errors
        form.find('.alert-danger').remove();
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#userEditModal').modal('hide');
                toastr.success('Kullanıcı başarıyla güncellendi!');
                // Reload data without page refresh
                loadUsers();
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
                    toastr.error('Bir hata oluştu!');
                }
            }
        });
    });
    
    // Show Modal - Fill data
    $('#userShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var name = button.data('name');
        var email = button.data('email');
        var phone = button.data('phone');
        var status = button.data('status');
        var coins = button.data('coins');
        
        $('#show-name').text(name);
        $('#show-email').text(email);
        $('#show-phone').text(phone || '-');
        $('#show-coins').text(coins || 0);
        $('#show-status').html(status === 'active' ? '<span class="badge bg-success">Aktif</span>' : 
                              status === 'suspended' ? '<span class="badge bg-danger">Askıya Alınmış</span>' : 
                              '<span class="badge bg-warning">Beklemede</span>');
    });
    
    // Edit Modal - Fill data
    $('#userEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');
        var email = button.data('email');
        var phone = button.data('phone');
        var status = button.data('status');
        var coins = button.data('coins');
        var role = button.data('role');
        var package = button.data('package');
        
        $('#edit-name').val(name);
        $('#edit-email').val(email);
        $('#edit-phone').val(phone);
        $('#edit-status').val(status);
        $('#edit-coins').val(coins);
        $('#edit-role').val(role);
        $('#edit-package').val(package);
        $('#edit-password').val(''); // Clear password field
        $('#userEditForm').attr('action', '/private/lesley/admin/users/' + id);
    });
    
    // Load Users Function
    function loadUsers(page = 1) {
        $.ajax({
            url: '/private/lesley/admin/users',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                // Extract table body and pagination from response
                var tableBody = $(response).find('tbody').html();
                var pagination = $(response).find('.pagination').parent().html();
                
                // Update table body
                $('tbody').html(tableBody);
                
                // Update pagination
                $('.pagination').parent().html(pagination);
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }
    
    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadUsers(page);
    });
    
});
</script>
@endpush


@endsection

@include('admin.layouts.footer')
