@extends('admin.layouts.app')

@section('title', 'Hediye Kartı Mağazaları')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Hediye Kartı Mağazaları</h3>
            <p class="text-muted mb-0 small">Liste ve hızlı işlemler</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            @can('create gift card stores')
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#storeCreateModal">Yeni Mağaza</a>
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
                                <th>Tip</th>
                                <th>Görsel</th>
                                <th>Sıra</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="storesTableBody">
                            @foreach($stores as $store)
                            <tr>
                                <td>{{ $store->id }}</td>
                                <td>
                                    @if($store->type === 'market')
                                        <span class="badge bg-info">Market</span>
                                    @else
                                        <span class="badge bg-primary">Mağaza</span>
                                    @endif
                                </td>
                                <td>
                                    <img src="{{ $store->image_url }}" alt="Store {{ $store->id }}"
                                         style="width: 100px; height: 60px; object-fit: contain; border: 1px solid #ddd; padding: 5px;">
                                </td>
                                <td>{{ $store->sort_order }}</td>
                                <td>
                                    @if($store->is_active)
                                        <span class="badge bg-success px-1 py-0" style="font-size: 0.75rem;">Aktif</span>
                                    @else
                                        <span class="badge bg-danger px-1 py-0" style="font-size: 0.75rem;">Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#storeShowModal"
                                            data-id="{{ $store->id }}"
                                            data-type="{{ $store->type }}"
                                            data-image-url="{{ $store->image_url }}"
                                            data-sort="{{ $store->sort_order }}"
                                            data-active="{{ $store->is_active }}">Görüntüle</button>
                                    @can('edit gift card stores')
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#storeEditModal"
                                            data-id="{{ $store->id }}"
                                            data-type="{{ $store->type }}"
                                            data-image-url="{{ $store->image_url }}"
                                            data-sort="{{ $store->sort_order }}"
                                            data-active="{{ $store->is_active }}">Düzenle</button>
                                    @endcan
                                    @can('delete gift card stores')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteStore({{ $store->id }})">Sil</button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="storesPagination" class="d-flex justify-content-center mt-3">
                    {{ $stores->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Store Modal -->
<div class="modal fade" id="storeCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Yeni Mağaza</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('admin.gift-card-stores.store') }}" enctype="multipart/form-data" id="storeCreateForm">
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
              <label class="form-label">Tip *</label>
              <select name="type" class="form-select" required>
                <option value="">Seçiniz</option>
                <option value="market">Market</option>
                <option value="mağaza">Mağaza</option>
              </select>
            </div>
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

<!-- Show Store Modal -->
<div class="modal fade" id="storeShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mağaza Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <img id="show-image" src="" alt="Store"
               style="max-width: 300px; max-height: 200px; object-fit: contain; border: 2px solid #ddd; padding: 10px;">
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Tip</label>
              <p id="show-type" class="form-control-plaintext"></p>
            </div>
          </div>
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

<!-- Edit Store Modal -->
<div class="modal fade" id="storeEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Mağazayı Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" id="storeEditForm" enctype="multipart/form-data">
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
              <label class="form-label">Tip *</label>
              <select name="type" id="edit-type" class="form-select" required>
                <option value="">Seçiniz</option>
                <option value="market">Market</option>
                <option value="mağaza">Mağaza</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Mevcut Görsel</label>
              <div class="text-center mb-2">
                <img id="edit-current-image" src="" alt="Store"
                     style="max-width: 200px; max-height: 120px; object-fit: contain; border: 2px solid #ddd; padding: 5px;">
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
.page-title {
    margin-top: 2rem !important;
    padding-top: 1rem !important;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
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

    // Create Store Form
    $('#storeCreateForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(form[0]);
        var url = form.attr('action');
        form.find('.alert-danger').remove();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#storeCreateModal').modal('hide');
                toastr.success('Mağaza başarıyla oluşturuldu!');
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

    // Edit Store Form
    $('#storeEditForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(form[0]);
        var url = form.attr('action');
        form.find('.alert-danger').remove();

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#storeEditModal').modal('hide');
                toastr.success('Mağaza başarıyla güncellendi!');
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

    // Bind Edit Modal Events
    $('#storeEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var type = button.data('type');
        var imageUrl = button.data('image-url');
        var sort = button.data('sort');
        var active = button.data('active');

        $('#edit-type').val(type);
        $('#edit-current-image').attr('src', imageUrl);
        $('#edit-sort').val(sort);
        $('#edit-active').prop('checked', active == 1);
        $('#storeEditForm').attr('action', '/admin/gift-card-stores/' + id);
    });

    // Bind Store Show Modal Events
    $('#storeShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var type = button.data('type');
        var imageUrl = button.data('image-url');
        var sort = button.data('sort');
        var active = button.data('active');

        $('#show-image').attr('src', imageUrl);
        $('#show-type').html(type === 'market' ? '<span class="badge bg-info">Market</span>' : '<span class="badge bg-primary">Mağaza</span>');
        $('#show-sort').text(sort);
        $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');
    });

    // Delete Store
    window.deleteStore = function(id) {
        if (confirm('Bu mağazayı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/gift-card-stores/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE',
                    _token: '{{ csrf_token() }}'
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
                        toastr.error('Mağaza silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')

