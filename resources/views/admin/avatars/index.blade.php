@extends('admin.layouts.app')

@section('title', 'Avatarlar')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Avatarlar</h3>
            <p class="text-muted mb-0 small">Liste ve hızlı işlemler</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            @can('create avatars')
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#avatarCreateModal">Yeni Avatar</a>
            @endcan
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Görsel</th>
                                <th>Sıra</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="avatarsTableBody">
                            @foreach($avatars as $avatar)
                            <tr>
                                <td>{{ $avatar->id }}</td>
                                <td>
                                    <img src="{{ $avatar->image_url }}" alt="Avatar {{ $avatar->id }}"
                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%;">
                                </td>
                                <td>{{ $avatar->sort_order }}</td>
                                <td>
                                    @if($avatar->is_active)
                                        <span class="badge bg-success px-1 py-0" style="font-size: 0.75rem;">Aktif</span>
                                    @else
                                        <span class="badge bg-danger px-1 py-0" style="font-size: 0.75rem;">Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#avatarShowModal"
                                            data-id="{{ $avatar->id }}"
                                            data-image-url="{{ $avatar->image_url }}"
                                            data-sort="{{ $avatar->sort_order }}"
                                            data-active="{{ $avatar->is_active }}">Görüntüle</button>
                                    @can('edit avatars')
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#avatarEditModal"
                                            data-id="{{ $avatar->id }}"
                                            data-image-url="{{ $avatar->image_url }}"
                                            data-sort="{{ $avatar->sort_order }}"
                                            data-active="{{ $avatar->is_active }}">Düzenle</button>
                                    @endcan
                                    @can('delete avatars')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteAvatar({{ $avatar->id }})">Sil</button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="avatarsPagination" class="d-flex justify-content-center mt-3">
                    {{ $avatars->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Avatar Modal -->
<div class="modal fade" id="avatarCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Yeni Avatar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('admin.avatars.store') }}" enctype="multipart/form-data" id="avatarCreateForm">
        @csrf
        <div class="modal-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Görsel *</label>
              <input type="file" name="image" class="form-control" accept="image/*" required>
              <small class="form-text text-muted">Maksimum 2MB, Format: JPEG, PNG, JPG, GIF, WEBP</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sıra</label>
              <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="create_is_active" checked>
                <label class="form-check-label" for="create_is_active">Aktif</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Show Avatar Modal -->
<div class="modal fade" id="avatarShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Avatar Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <img id="show-image" src="" alt="Avatar"
               style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #ddd;">
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Sıra</label>
              <p id="show-sort" class="form-control-plaintext"></p>
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

<!-- Edit Avatar Modal -->
<div class="modal fade" id="avatarEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Avatarı Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" id="avatarEditForm" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="modal-body">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Mevcut Görsel</label>
              <div class="text-center mb-2">
                <img id="edit-current-image" src="" alt="Avatar"
                     style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #ddd;">
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Yeni Görsel (Değiştirmek için seçin)</label>
              <input type="file" name="image" class="form-control" accept="image/*">
              <small class="form-text text-muted">Maksimum 2MB, Format: JPEG, PNG, JPG, GIF, WEBP</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sıra</label>
              <input type="number" name="sort_order" id="edit-sort" class="form-control" min="0">
            </div>
            <div class="col-md-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="edit-active">
                <label class="form-check-label" for="edit-active">Aktif</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('styles')
<style>
/* Extra spacing to prevent header overlap on avatars page */
.page-title {
    margin-top: 2rem !important;
    padding-top: 1rem !important;
}
</style>
@endpush

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

    // Create Avatar Form
    $('#avatarCreateForm').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = new FormData(form[0]);
        var url = form.attr('action');

        // Clear previous errors
        form.find('.alert-danger').remove();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Close modal
                $('#avatarCreateModal').modal('hide');
                // Show success message
                toastr.success('Avatar başarıyla oluşturuldu!');
                // Reload page to show new avatar
                location.reload();
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

    // Edit Avatar Form
    $('#avatarEditForm').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = new FormData(form[0]);
        var url = form.attr('action');

        // Clear previous errors
        form.find('.alert-danger').remove();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Close modal
                $('#avatarEditModal').modal('hide');
                // Show success message
                toastr.success('Avatar başarıyla güncellendi!');
                // Reload page to show updated avatar
                location.reload();
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

    // Load Avatars Function
    function loadAvatars(page = 1) {
        $.ajax({
            url: '/admin/avatars',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                // Extract table body and pagination from response
                var tableBody = $(response).find('#avatarsTableBody').html();
                var pagination = $(response).find('#avatarsPagination').html();

                // Update table body
                $('#avatarsTableBody').html(tableBody);

                // Update pagination
                $('#avatarsPagination').html(pagination);

                // Re-bind edit modal events
                bindEditModalEvents();
                // Re-bind show modal events
                bindAvatarShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#avatarEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var imageUrl = button.data('image-url');
            var sort = button.data('sort');
            var active = button.data('active');

            $('#edit-current-image').attr('src', imageUrl);
            $('#edit-sort').val(sort);
            $('#edit-active').prop('checked', active == 1);
            $('#avatarEditForm').attr('action', '/admin/avatars/' + id);
        });
    }

    // Bind Avatar Show Modal Events
    function bindAvatarShowModalEvents() {
        $('#avatarShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var imageUrl = button.data('image-url');
            var sort = button.data('sort');
            var active = button.data('active');

            $('#show-image').attr('src', imageUrl);
            $('#show-sort').text(sort);
            $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadAvatars(page);
    });

    // Delete Avatar
    window.deleteAvatar = function(id) {
        if (confirm('Bu avatarı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/avatars/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE',
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        // Reload page
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        var response = xhr.responseJSON;
                        if (response.message) {
                            toastr.error(response.message);
                        } else if (response.errors) {
                            var errorMessages = [];
                            $.each(response.errors, function(key, value) {
                                errorMessages.push(value[0]);
                            });
                            toastr.error(errorMessages.join('<br>'));
                        }
                    } else {
                        toastr.error('Avatar silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize edit modal events on page load
    bindEditModalEvents();
    // Initialize show modal events on page load
    bindAvatarShowModalEvents();

});
</script>
@endpush

@endsection

@include('admin.layouts.footer')

