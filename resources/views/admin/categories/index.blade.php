@extends('admin.layouts.app')

@section('title', 'Kategoriler')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6"><h3>Kategoriler</h3></div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Kategoriler</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Kategori Listesi</h5>
                @can('create categories')
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryCreateModal">Yeni Kategori</a>
                </div>
                @endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>İkon</th>
                                <th>İsim</th>
                                <th>Açıklama</th>
                                <th>Renk</th>
                                <th>Soru Sayısı</th>
                                <th>Sıra</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody">
                            @foreach($categories as $category)
                            <tr>
                                <td>{{ $category->id }}</td>
                                <td><i class="{{ $category->icon }}"></i></td>
                                <td>{{ $category->getTranslation('name', 'tr') }}</td>
                                <td>{{ $category->getTranslation('description', 'tr') }}</td>
                                <td><span class="badge" style="background-color: {{ $category->color_code }}">{{ $category->color_code }}</span></td>
                                <td>{{ $category->questions_count ?? 0 }}</td>
                                <td>{{ $category->sort_order }}</td>
                                <td>
                                    @if($category->is_active)
                                        <span class="badge bg-success px-1 py-0" style="font-size: 0.75rem;">Aktif</span>
                                    @else
                                        <span class="badge bg-danger px-1 py-0" style="font-size: 0.75rem;">Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#categoryShowModal"
                                            data-id="{{ $category->id }}"
                                            data-name-tr="{{ $category->getTranslation('name', 'tr') }}"
                                            data-name-en="{{ $category->getTranslation('name', 'en') }}"
                                            data-desc-tr="{{ $category->getTranslation('description', 'tr') }}"
                                            data-desc-en="{{ $category->getTranslation('description', 'en') }}"
                                            data-icon="{{ $category->icon }}"
                                            data-color="{{ $category->color_code }}"
                                            data-sort="{{ $category->sort_order }}"
                                            data-active="{{ $category->is_active }}">Görüntüle</button>
                                    @can('edit categories')
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#categoryEditModal"
                                            data-id="{{ $category->id }}"
                                            data-name-tr="{{ $category->getTranslation('name', 'tr') }}"
                                            data-name-en="{{ $category->getTranslation('name', 'en') }}"
                                            data-desc-tr="{{ $category->getTranslation('description', 'tr') }}"
                                            data-desc-en="{{ $category->getTranslation('description', 'en') }}"
                                            data-icon="{{ $category->icon }}"
                                            data-color="{{ $category->color_code }}"
                                            data-sort="{{ $category->sort_order }}"
                                            data-active="{{ $category->is_active }}">Düzenle</button>
                                    @endcan
                                    @can('delete categories')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCategory({{ $category->id }})">Sil</button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="categoriesPagination" class="d-flex justify-content-center mt-3">
                    {{ $categories->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Category Modal -->
<div class="modal fade" id="categoryCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Yeni Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('admin.categories.store') }}">
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
          
          <!-- Tab Navigation -->
          <ul class="nav nav-tabs" id="createCategoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tr-tab" data-bs-toggle="tab" data-bs-target="#tr-pane" type="button" role="tab">
                🇹🇷 Türkçe
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en-pane" type="button" role="tab">
                🇬🇧 English
              </button>
            </li>
          </ul>
          
          <!-- Tab Content -->
          <div class="tab-content mt-3" id="createCategoryTabContent">
            <!-- Turkish Tab -->
            <div class="tab-pane fade show active" id="tr-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">İsim (Türkçe) *</label>
                  <input type="text" name="name[tr]" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama (Türkçe)</label>
                  <textarea name="description[tr]" class="form-control" rows="3"></textarea>
                </div>
              </div>
            </div>
            
            <!-- English Tab -->
            <div class="tab-pane fade" id="en-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">İsim (English)</label>
                  <input type="text" name="name[en]" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama (English)</label>
                  <textarea name="description[en]" class="form-control" rows="3"></textarea>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Other Fields -->
          <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label">İkon</label>
            <input type="text" name="icon" class="form-control" placeholder="fa fa-home">
          </div>
          <div class="col-md-6">
            <label class="form-label">Renk Kodu</label>
            <input type="color" name="color_code" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sıra</label>
            <input type="number" name="sort_order" class="form-control" value="0">
          </div>
          <div class="col-md-6">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
              <label class="form-check-label" for="is_active">Aktif</label>
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

<!-- Show Category Modal -->
<div class="modal fade" id="categoryShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kategori Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Türkçe İsim</label>
              <p id="show-name-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">İngilizce İsim</label>
              <p id="show-name-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Türkçe Açıklama</label>
              <p id="show-desc-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">İngilizce Açıklama</label>
              <p id="show-desc-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label fw-bold">İkon</label>
              <p id="show-icon" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label fw-bold">Renk</label>
              <p id="show-color" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label fw-bold">Sıra</label>
              <p id="show-sort" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
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

<!-- Edit Category Modal -->
<div class="modal fade" id="categoryEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Kategoriyi Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" id="categoryEditForm">
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
          
          <!-- Tab Navigation -->
          <ul class="nav nav-tabs" id="editCategoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="edit-tr-tab" data-bs-toggle="tab" data-bs-target="#edit-tr-pane" type="button" role="tab">
                🇹🇷 Türkçe
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="edit-en-tab" data-bs-toggle="tab" data-bs-target="#edit-en-pane" type="button" role="tab">
                🇬🇧 English
              </button>
            </li>
          </ul>
          
          <!-- Tab Content -->
          <div class="tab-content mt-3" id="editCategoryTabContent">
            <!-- Turkish Tab -->
            <div class="tab-pane fade show active" id="edit-tr-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">İsim (Türkçe) *</label>
                  <input type="text" name="name[tr]" id="edit-name-tr" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama (Türkçe)</label>
                  <textarea name="description[tr]" id="edit-desc-tr" class="form-control" rows="3"></textarea>
                </div>
              </div>
            </div>
            
            <!-- English Tab -->
            <div class="tab-pane fade" id="edit-en-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">İsim (English)</label>
                  <input type="text" name="name[en]" id="edit-name-en" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">Açıklama (English)</label>
                  <textarea name="description[en]" id="edit-desc-en" class="form-control" rows="3"></textarea>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Other Fields -->
          <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label">İkon</label>
            <input type="text" name="icon" id="edit-icon" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Renk Kodu</label>
            <input type="color" name="color_code" id="edit-color" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sıra</label>
            <input type="number" name="sort_order" id="edit-sort" class="form-control">
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
/* Extra spacing to prevent header overlap on categories page */
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

    // Create Category Form
    $('#categoryCreateModal form').on('submit', function(e) {
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
                // Close modal
                $('#categoryCreateModal').modal('hide');
                // Show success message
                toastr.success('Kategori başarıyla oluşturuldu!');
                // Reload data without page refresh
                loadCategories();
                // Reset form
                $('#categoryCreateModal form')[0].reset();
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

    // Edit Category Form
    $('#categoryEditModal form').on('submit', function(e) {
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
                // Close modal
                $('#categoryEditModal').modal('hide');
                // Show success message
                toastr.success('Kategori başarıyla güncellendi!');
                // Reload data without page refresh
                loadCategories();
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


    // Load Categories Function
    function loadCategories(page = 1) {
        $.ajax({
            url: '/admin/categories',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                // Extract table body and pagination from response
                var tableBody = $(response).find('#categoriesTableBody').html();
                var pagination = $(response).find('#categoriesPagination').html();
                
                // Update table body
                $('#categoriesTableBody').html(tableBody);
                
                // Update pagination
                $('#categoriesPagination').html(pagination);
                
                // Re-bind edit modal events
                bindEditModalEvents();
                // Re-bind show modal events
                bindCategoryShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#categoryEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var nameTr = button.data('name-tr');
            var nameEn = button.data('name-en');
            var descTr = button.data('desc-tr');
            var descEn = button.data('desc-en');
            var icon = button.data('icon');
            var color = button.data('color');
            var sort = button.data('sort');
            var active = button.data('active');
            
            $('#edit-name-tr').val(nameTr || '');
            $('#edit-name-en').val(nameEn || '');
            $('#edit-desc-tr').val(descTr || '');
            $('#edit-desc-en').val(descEn || '');
            $('#edit-icon').val(icon);
            $('#edit-color').val(color);
            $('#edit-sort').val(sort);
            $('#edit-active').prop('checked', active == 1);
            $('#categoryEditForm').attr('action', '/admin/categories/' + id);
        });
    }

    // Bind Category Show Modal Events
    function bindCategoryShowModalEvents() {
        $('#categoryShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var nameTr = button.data('name-tr');
            var nameEn = button.data('name-en');
            var descTr = button.data('desc-tr');
            var descEn = button.data('desc-en');
            var icon = button.data('icon');
            var color = button.data('color');
            var sort = button.data('sort');
            var active = button.data('active');
            
            $('#show-name-tr').text(nameTr || '-');
            $('#show-name-en').text(nameEn || '-');
            $('#show-desc-tr').text(descTr || '-');
            $('#show-desc-en').text(descEn || '-');
            $('#show-icon').html('<i class="' + icon + '"></i> ' + icon);
            $('#show-color').html('<span class="badge" style="background-color: ' + color + ';">' + color + '</span>');
            $('#show-sort').text(sort);
            $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadCategories(page);
    });

    // Delete Category
    window.deleteCategory = function(id) {
        if (confirm('Bu kategoriyi silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/categories/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        // Reload data without page refresh
                        loadCategories();
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
                        toastr.error('Kategori silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize edit modal events on page load
    bindEditModalEvents();
    // Initialize show modal events on page load
    bindCategoryShowModalEvents();

});
</script>
@endpush

@endsection

@include('admin.layouts.footer')