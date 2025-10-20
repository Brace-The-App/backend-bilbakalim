@extends('admin.layouts.app')

@section('title', 'Kullanıcı Yorumları')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Kullanıcı Yorumları</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Kullanıcı Yorumları</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Kullanıcı Yorumları Listesi</h5>
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testimonialCreateModal">Yeni Yorum</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Profil Resmi</th>
                                <th>Kullanıcı Adı</th>
                                <th>Yorum</th>
                                <th>Oluşturulma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="testimonialTableBody">
                            @foreach($testimonials as $testimonial)
                            <tr>
                                <td>{{ $testimonial->id }}</td>
                                <td>
                                    @if($testimonial->profile_img)
                                        <img src="{{ asset($testimonial->profile_img) }}" alt="Profile Image" style="width: 50px; height: 50px; object-fit: cover;" class="rounded-circle">
                                    @else
                                        <span class="text-muted">Resim Yok</span>
                                    @endif
                                </td>
                                <td>{{ $testimonial->user_name }}</td>
                                <td>{{ Str::limit($testimonial->comment, 50) }}</td>
                                <td>{{ $testimonial->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#testimonialShowModal"
                                            data-id="{{ $testimonial->id }}"
                                            data-user-name="{{ $testimonial->user_name }}"
                                            data-comment="{{ $testimonial->comment }}"
                                            data-profile-img="{{ $testimonial->profile_img }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#testimonialEditModal"
                                            data-id="{{ $testimonial->id }}"
                                            data-user-name="{{ $testimonial->user_name }}"
                                            data-comment="{{ $testimonial->comment }}"
                                            data-profile-img="{{ $testimonial->profile_img }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTestimonial({{ $testimonial->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="testimonialPagination" class="d-flex justify-content-center mt-3">
                    {{ $testimonials->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Testimonial Modal -->
<div class="modal fade" id="testimonialCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Kullanıcı Yorumu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.testimonials.store') }}" enctype="multipart/form-data">
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
              <label class="form-label">Kullanıcı Adı *</label>
              <input type="text" name="user_name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Yorum *</label>
              <textarea name="comment" class="form-control" rows="5" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Profil Resmi</label>
              <input type="file" name="profile_img" class="form-control" accept="image/*">
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

<!-- Show Testimonial Modal -->
<div class="modal fade" id="testimonialShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcı Yorumu Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Kullanıcı Adı</label>
              <p id="show-user-name" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Yorum</label>
              <p id="show-comment" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Profil Resmi</label>
              <div id="show-profile-img" class="form-control-plaintext"></div>
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

<!-- Edit Testimonial Modal -->
<div class="modal fade" id="testimonialEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcı Yorumu Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="testimonialEditForm" enctype="multipart/form-data">
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
              <label class="form-label">Kullanıcı Adı *</label>
              <input type="text" name="user_name" id="edit-user-name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Yorum *</label>
              <textarea name="comment" id="edit-comment" class="form-control" rows="5" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Profil Resmi</label>
              <input type="file" name="profile_img" class="form-control" accept="image/*">
              <small class="text-muted">JPG, PNG, GIF, SVG formatları desteklenir. Maksimum 2MB.</small>
              <div id="current-profile-img" class="mt-2"></div>
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

    // Create Testimonial Form
    $('#testimonialCreateModal form').on('submit', function(e) {
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
                $('#testimonialCreateModal').modal('hide');
                toastr.success('Kullanıcı yorumu başarıyla oluşturuldu!');
                loadTestimonials();
                $('#testimonialCreateModal form')[0].reset();
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
    
    // Edit Testimonial Form
    $('#testimonialEditModal form').on('submit', function(e) {
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
                $('#testimonialEditModal').modal('hide');
                toastr.success('Kullanıcı yorumu başarıyla güncellendi!');
                loadTestimonials();
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
    $('#testimonialShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var userName = button.data('user-name');
        var comment = button.data('comment');
        var profileImg = button.data('profile-img');
        
        $('#show-user-name').text(userName);
        $('#show-comment').text(comment);
        if (profileImg) {
            $('#show-profile-img').html('<img src="' + profileImg + '" alt="Profile Image" style="max-width: 200px; max-height: 200px;" class="rounded-circle">');
        } else {
            $('#show-profile-img').text('Resim Yok');
        }
    });
    
    // Edit Modal - Fill data
    $('#testimonialEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var userName = button.data('user-name');
        var comment = button.data('comment');
        var profileImg = button.data('profile-img');
        
        $('#edit-user-name').val(userName);
        $('#edit-comment').val(comment);
        if (profileImg) {
            $('#current-profile-img').html('<small class="text-muted">Mevcut resim:</small><br><img src="' + profileImg + '" alt="Current Image" style="max-width: 100px; max-height: 100px;" class="rounded-circle mt-1">');
        } else {
            $('#current-profile-img').html('<small class="text-muted">Mevcut resim yok</small>');
        }
        $('#testimonialEditForm').attr('action', '/private/lesley/admin/landing/testimonials/' + id);
    });
    
    // Load Testimonials Function
    function loadTestimonials(page = 1) {
        $.ajax({
            url: '/private/lesley/admin/landing/testimonials',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#testimonialTableBody').html();
                var pagination = $(response).find('#testimonialPagination').html();
                
                $('#testimonialTableBody').html(tableBody);
                $('#testimonialPagination').html(pagination);
                
                bindEditModalEvents();
                bindTestimonialShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }
    
    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#testimonialEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var userName = button.data('user-name');
            var comment = button.data('comment');
            var profileImg = button.data('profile-img');
            
            $('#edit-user-name').val(userName);
            $('#edit-comment').val(comment);
            if (profileImg) {
                $('#current-profile-img').html('<small class="text-muted">Mevcut resim:</small><br><img src="' + profileImg + '" alt="Current Image" style="max-width: 100px; max-height: 100px;" class="rounded-circle mt-1">');
            } else {
                $('#current-profile-img').html('<small class="text-muted">Mevcut resim yok</small>');
            }
            $('#testimonialEditForm').attr('action', '/private/lesley/admin/landing/testimonials/' + id);
        });
    }

    // Bind Testimonial Show Modal Events
    function bindTestimonialShowModalEvents() {
        $('#testimonialShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var userName = button.data('user-name');
            var comment = button.data('comment');
            var profileImg = button.data('profile-img');
            
            $('#show-user-name').text(userName);
            $('#show-comment').text(comment);
            if (profileImg) {
                $('#show-profile-img').html('<img src="' + profileImg + '" alt="Profile Image" style="max-width: 200px; max-height: 200px;" class="rounded-circle">');
            } else {
                $('#show-profile-img').text('Resim Yok');
            }
        });
    }
    
    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadTestimonials(page);
    });
    
    // Delete Testimonial
    window.deleteTestimonial = function(id) {
        if (confirm('Bu kullanıcı yorumunu silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/private/lesley/admin/landing/testimonials/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadTestimonials();
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
                        toastr.error('Kullanıcı yorumu silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindTestimonialShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')