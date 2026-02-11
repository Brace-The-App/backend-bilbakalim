@extends('admin.layouts.app')

@section('title', 'Uygulama Hakkında')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Uygulama Hakkında</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Uygulama Hakkında</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Uygulama Hakkında Listesi</h5>
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#aboutCreateModal">Yeni Ekle</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Resim</th>
                                <th>Başlık</th>
                                <th>Açıklama</th>
                                <th>Oluşturulma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="aboutTableBody">
                            @foreach($abouts as $about)
                            <tr>
                                <td>{{ $about->id }}</td>
                                <td>
                                    @if($about->img)
                                        <img src="{{ asset($about->img) }}" alt="About Image" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                    @else
                                        <span class="text-muted">Resim Yok</span>
                                    @endif
                                </td>
                                <td>{{ $about->title }}</td>
                                <td>{{ Str::limit($about->description, 50) }}</td>
                                <td>{{ $about->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#aboutShowModal"
                                            data-id="{{ $about->id }}"
                                            data-title="{{ $about->title }}"
                                            data-description="{{ $about->description }}"
                                            data-img="{{ $about->img }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#aboutEditModal"
                                            data-id="{{ $about->id }}"
                                            data-title="{{ $about->title }}"
                                            data-description="{{ $about->description }}"
                                            data-img="{{ $about->img }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteAbout({{ $about->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="aboutPagination" class="d-flex justify-content-center mt-3">
                    {{ $abouts->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create About Modal -->
<div class="modal fade" id="aboutCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Uygulama Hakkında</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.about.store') }}" enctype="multipart/form-data">
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
            <div class="col-12">
              <label class="form-label">Resim</label>
              <input type="file" name="img" class="form-control" accept="image/*">
              <small class="text-muted">JPG, PNG, GIF, SVG formatları desteklenir. Maksimum 2MB.</small>
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

<!-- Show About Modal -->
<div class="modal fade" id="aboutShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Uygulama Hakkında Detayları</h5>
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
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Resim</label>
              <div id="show-img" class="form-control-plaintext"></div>
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

<!-- Edit About Modal -->
<div class="modal fade" id="aboutEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Uygulama Hakkında Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="aboutEditForm" enctype="multipart/form-data">
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
            <div class="col-12">
              <label class="form-label">Resim</label>
              <input type="file" name="img" class="form-control" accept="image/*">
              <small class="text-muted">JPG, PNG, GIF, SVG formatları desteklenir. Maksimum 2MB.</small>
              <div id="current-img" class="mt-2"></div>
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

    // Create About Form
    $('#aboutCreateModal form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = new FormData(this);
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
                $('#aboutCreateModal').modal('hide');
                toastr.success('Uygulama hakkında başarıyla oluşturuldu!');
                loadAbouts();
                $('#aboutCreateModal form')[0].reset();
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

    // Edit About Form
    $('#aboutEditModal form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = new FormData(this);
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
                $('#aboutEditModal').modal('hide');
                toastr.success('Uygulama hakkında başarıyla güncellendi!');
                loadAbouts();
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
    $('#aboutShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var title = button.data('title');
        var description = button.data('description');
        var img = button.data('img');

        $('#show-title').text(title);
        $('#show-description').text(description);
        if (img) {
            $('#show-img').html('<img src="' + img + '" alt="About Image" style="max-width: 200px; max-height: 200px;" class="rounded">');
        } else {
            $('#show-img').text('Resim Yok');
        }
    });

    // Edit Modal - Fill data
    $('#aboutEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var description = button.data('description');
        var img = button.data('img');

        $('#edit-title').val(title);
        $('#edit-description').val(description);
        if (img) {
            $('#current-img').html('<small class="text-muted">Mevcut resim:</small><br><img src=" '+ img + '" alt="Current Image" style="max-width: 100px; max-height: 100px;" class="rounded mt-1">');
        } else {
            $('#current-img').html('<small class="text-muted">Mevcut resim yok</small>');
        }
        $('#aboutEditForm').attr('action', '/admin/landing/about/' + id);
    });

    // Load Abouts Function
    function loadAbouts(page = 1) {
        $.ajax({
            url: '/admin/landing/about',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#aboutTableBody').html();
                var pagination = $(response).find('#aboutPagination').html();

                $('#aboutTableBody').html(tableBody);
                $('#aboutPagination').html(pagination);

                bindEditModalEvents();
                bindAboutShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#aboutEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var title = button.data('title');
            var description = button.data('description');
            var img = button.data('img');

            $('#edit-title').val(title);
            $('#edit-description').val(description);
            if (img) {
                $('#current-img').html('<small class="text-muted">Mevcut resim:</small><br><img src="' + img + '" alt="Current Image" style="max-width: 100px; max-height: 100px;" class="rounded mt-1">');
            } else {
                $('#current-img').html('<small class="text-muted">Mevcut resim yok</small>');
            }
            $('#aboutEditForm').attr('action', '/admin/landing/about/' + id);
        });
    }

    // Bind About Show Modal Events
    function bindAboutShowModalEvents() {
        $('#aboutShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var title = button.data('title');
            var description = button.data('description');
            var img = button.data('img');

            $('#show-title').text(title);
            $('#show-description').text(description);
            if (img) {
                $('#show-img').html('<img src="' + img + '" alt="About Image" style="max-width: 200px; max-height: 200px;" class="rounded">');
            } else {
                $('#show-img').text('Resim Yok');
            }
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadAbouts(page);
    });

    // Delete About
    window.deleteAbout = function(id) {
        if (confirm('Bu kaydı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/landing/about/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadAbouts();
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
                        toastr.error('Kayıt silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindAboutShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')
