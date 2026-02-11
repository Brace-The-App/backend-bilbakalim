@extends('admin.layouts.app')

@section('title', 'Özellikler')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Özellikler</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Özellikler</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Özellikler Listesi</h5>
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#featureCreateModal">Yeni Özellik</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Başlık</th>
                                <th>Açıklama</th>
                                <th>Oluşturulma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="featureTableBody">
                            @foreach($features as $feature)
                            <tr>
                                <td>{{ $feature->id }}</td>
                                <td>{{ $feature->title }}</td>
                                <td>{{ Str::limit($feature->description, 50) }}</td>
                                <td>{{ $feature->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#featureShowModal"
                                            data-id="{{ $feature->id }}"
                                            data-title="{{ $feature->title }}"
                                            data-description="{{ $feature->description }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#featureEditModal"
                                            data-id="{{ $feature->id }}"
                                            data-title="{{ $feature->title }}"
                                            data-description="{{ $feature->description }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteFeature({{ $feature->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="featurePagination" class="d-flex justify-content-center mt-3">
                    {{ $features->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Feature Modal -->
<div class="modal fade" id="featureCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Özellik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.features.store') }}">
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
              <label class="form-label">Başlık *</label>
              <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Açıklama *</label>
              <textarea name="description" class="form-control" rows="5" required></textarea>
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

<!-- Show Feature Modal -->
<div class="modal fade" id="featureShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Özellik Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Başlık</label>
              <p id="show-title" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Açıklama</label>
              <p id="show-description" class="form-control-plaintext"></p>
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

<!-- Edit Feature Modal -->
<div class="modal fade" id="featureEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Özellik Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="featureEditForm">
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
              <label class="form-label">Başlık *</label>
              <input type="text" name="title" id="edit-title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Açıklama *</label>
              <textarea name="description" id="edit-description" class="form-control" rows="5" required></textarea>
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

    // Create Feature Form
    $('#featureCreateModal form').on('submit', function(e) {
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
                $('#featureCreateModal').modal('hide');
                toastr.success('Özellik başarıyla oluşturuldu!');
                loadFeatures();
                $('#featureCreateModal form')[0].reset();
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

    // Edit Feature Form
    $('#featureEditModal form').on('submit', function(e) {
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
                $('#featureEditModal').modal('hide');
                toastr.success('Özellik başarıyla güncellendi!');
                loadFeatures();
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
    $('#featureShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var title = button.data('title');
        var description = button.data('description');

        $('#show-title').text(title);
        $('#show-description').text(description);
    });

    // Edit Modal - Fill data
    $('#featureEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var description = button.data('description');

        $('#edit-title').val(title);
        $('#edit-description').val(description);
        $('#featureEditForm').attr('action', '/admin/landing/features/' + id);
    });

    // Load Features Function
    function loadFeatures(page = 1) {
        $.ajax({
            url: '/admin/landing/features',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#featureTableBody').html();
                var pagination = $(response).find('#featurePagination').html();

                $('#featureTableBody').html(tableBody);
                $('#featurePagination').html(pagination);

                bindEditModalEvents();
                bindFeatureShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#featureEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var title = button.data('title');
            var description = button.data('description');

            $('#edit-title').val(title);
            $('#edit-description').val(description);
            $('#featureEditForm').attr('action', '/admin/landing/features/' + id);
        });
    }

    // Bind Feature Show Modal Events
    function bindFeatureShowModalEvents() {
        $('#featureShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var title = button.data('title');
            var description = button.data('description');

            $('#show-title').text(title);
            $('#show-description').text(description);
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadFeatures(page);
    });

    // Delete Feature
    window.deleteFeature = function(id) {
        if (confirm('Bu özelliği silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/landing/features/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadFeatures();
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
                        toastr.error('Özellik silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindFeatureShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')
