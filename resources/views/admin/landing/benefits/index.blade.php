@extends('admin.layouts.app')

@section('title', 'Avantajlar')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Avantajlar</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Avantajlar</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Avantajlar Listesi</h5>
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#benefitCreateModal">Yeni Avantaj</a>
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
                        <tbody id="benefitTableBody">
                            @foreach($benefits as $benefit)
                            <tr>
                                <td>{{ $benefit->id }}</td>
                                <td>{{ $benefit->title }}</td>
                                <td>{{ Str::limit($benefit->description, 50) }}</td>
                                <td>{{ $benefit->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#benefitShowModal"
                                            data-id="{{ $benefit->id }}"
                                            data-title="{{ $benefit->title }}"
                                            data-description="{{ $benefit->description }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#benefitEditModal"
                                            data-id="{{ $benefit->id }}"
                                            data-title="{{ $benefit->title }}"
                                            data-description="{{ $benefit->description }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteBenefit({{ $benefit->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="benefitPagination" class="d-flex justify-content-center mt-3">
                    {{ $benefits->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Benefit Modal -->
<div class="modal fade" id="benefitCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Avantaj</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.benefits.store') }}">
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

<!-- Show Benefit Modal -->
<div class="modal fade" id="benefitShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Avantaj Detayları</h5>
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

<!-- Edit Benefit Modal -->
<div class="modal fade" id="benefitEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Avantaj Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="benefitEditForm">
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

    // Create Benefit Form
    $('#benefitCreateModal form').on('submit', function(e) {
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
                $('#benefitCreateModal').modal('hide');
                toastr.success('Avantaj başarıyla oluşturuldu!');
                loadBenefits();
                $('#benefitCreateModal form')[0].reset();
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
    
    // Edit Benefit Form
    $('#benefitEditModal form').on('submit', function(e) {
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
                $('#benefitEditModal').modal('hide');
                toastr.success('Avantaj başarıyla güncellendi!');
                loadBenefits();
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
    $('#benefitShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var title = button.data('title');
        var description = button.data('description');
        
        $('#show-title').text(title);
        $('#show-description').text(description);
    });
    
    // Edit Modal - Fill data
    $('#benefitEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var description = button.data('description');
        
        $('#edit-title').val(title);
        $('#edit-description').val(description);
        $('#benefitEditForm').attr('action', '/private/lesley/admin/landing/benefits/' + id);
    });
    
    // Load Benefits Function
    function loadBenefits(page = 1) {
        $.ajax({
            url: '/private/lesley/admin/landing/benefits',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#benefitTableBody').html();
                var pagination = $(response).find('#benefitPagination').html();
                
                $('#benefitTableBody').html(tableBody);
                $('#benefitPagination').html(pagination);
                
                bindEditModalEvents();
                bindBenefitShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }
    
    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#benefitEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var title = button.data('title');
            var description = button.data('description');
            
            $('#edit-title').val(title);
            $('#edit-description').val(description);
            $('#benefitEditForm').attr('action', '/private/lesley/admin/landing/benefits/' + id);
        });
    }

    // Bind Benefit Show Modal Events
    function bindBenefitShowModalEvents() {
        $('#benefitShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
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
        loadBenefits(page);
    });
    
    // Delete Benefit
    window.deleteBenefit = function(id) {
        if (confirm('Bu avantajı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/private/lesley/admin/landing/benefits/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadBenefits();
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
                        toastr.error('Avantaj silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindBenefitShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')