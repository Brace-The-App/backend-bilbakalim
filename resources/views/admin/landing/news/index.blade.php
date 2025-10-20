@extends('admin.layouts.app')

@section('title', 'Bizden Haberler')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Bizden Haberler</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Bizden Haberler</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Bizden Haberler Listesi</h5>
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newsCreateModal">Yeni Haber</a>
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
                        <tbody id="newsTableBody">
                            @foreach($news as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td>
                                    @if($item->img)
                                        <img src="{{ asset($item->img) }}" alt="News Image" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                    @else
                                        <span class="text-muted">Resim Yok</span>
                                    @endif
                                </td>
                                <td>{{ $item->title }}</td>
                                <td>{{ Str::limit($item->description, 50) }}</td>
                                <td>{{ $item->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#newsShowModal"
                                            data-id="{{ $item->id }}"
                                            data-title="{{ $item->title }}"
                                            data-description="{{ $item->description }}"
                                            data-img="{{ $item->img }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#newsEditModal"
                                            data-id="{{ $item->id }}"
                                            data-title="{{ $item->title }}"
                                            data-description="{{ $item->description }}"
                                            data-img="{{ $item->img }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteNews({{ $item->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="newsPagination" class="d-flex justify-content-center mt-3">
                    {{ $news->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create News Modal -->
<div class="modal fade" id="newsCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Haber</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.news.store') }}" enctype="multipart/form-data">
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

<!-- Show News Modal -->
<div class="modal fade" id="newsShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Haber Detayları</h5>
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

<!-- Edit News Modal -->
<div class="modal fade" id="newsEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Haber Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="newsEditForm" enctype="multipart/form-data">
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

    // Create News Form
    $('#newsCreateModal form').on('submit', function(e) {
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
                $('#newsCreateModal').modal('hide');
                toastr.success('Haber başarıyla oluşturuldu!');
                loadNews();
                $('#newsCreateModal form')[0].reset();
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
    
    // Edit News Form
    $('#newsEditModal form').on('submit', function(e) {
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
                $('#newsEditModal').modal('hide');
                toastr.success('Haber başarıyla güncellendi!');
                loadNews();
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
    $('#newsShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var title = button.data('title');
        var description = button.data('description');
        var img = button.data('img');
        
        $('#show-title').text(title);
        $('#show-description').text(description);
        if (img) {
            $('#show-img').html('<img src="' + img + '" alt="News Image" style="max-width: 200px; max-height: 200px;" class="rounded">');
        } else {
            $('#show-img').text('Resim Yok');
        }
    });
    
    // Edit Modal - Fill data
    $('#newsEditModal').on('show.bs.modal', function (event) {
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
        $('#newsEditForm').attr('action', '/private/lesley/admin/landing/news/' + id);
    });
    
    // Load News Function
    function loadNews(page = 1) {
        $.ajax({
            url: '/private/lesley/admin/landing/news',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#newsTableBody').html();
                var pagination = $(response).find('#newsPagination').html();
                
                $('#newsTableBody').html(tableBody);
                $('#newsPagination').html(pagination);
                
                bindEditModalEvents();
                bindNewsShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }
    
    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#newsEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
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
            $('#newsEditForm').attr('action', '/private/lesley/admin/landing/news/' + id);
        });
    }

    // Bind News Show Modal Events
    function bindNewsShowModalEvents() {
        $('#newsShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var title = button.data('title');
            var description = button.data('description');
            var img = button.data('img');
            
            $('#show-title').text(title);
            $('#show-description').text(description);
            if (img) {
                $('#show-img').html('<img src="' + img + '" alt="News Image" style="max-width: 200px; max-height: 200px;" class="rounded">');
            } else {
                $('#show-img').text('Resim Yok');
            }
        });
    }
    
    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadNews(page);
    });
    
    // Delete News
    window.deleteNews = function(id) {
        if (confirm('Bu haberi silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/private/lesley/admin/landing/news/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadNews();
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
                        toastr.error('Haber silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindNewsShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')