@extends('admin.layouts.app')

@section('title', 'Sıkça Sorulan Sorular')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Sıkça Sorulan Sorular</h3>
            <p class="text-muted mb-0 small">Landing içerik yönetimi</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#faqCreateModal">Yeni SSS</a>
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
                                <th>Soru</th>
                                <th>Cevap</th>
                                <th>Oluşturulma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="faqTableBody">
                            @foreach($faqs as $faq)
                            <tr>
                                <td>{{ $faq->id }}</td>
                                <td>{{ Str::limit($faq->question, 50) }}</td>
                                <td>{{ Str::limit($faq->answer, 50) }}</td>
                                <td>{{ $faq->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#faqShowModal"
                                            data-id="{{ $faq->id }}"
                                            data-question="{{ $faq->question }}"
                                            data-answer="{{ $faq->answer }}">Görüntüle</button>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#faqEditModal"
                                            data-id="{{ $faq->id }}"
                                            data-question="{{ $faq->question }}"
                                            data-answer="{{ $faq->answer }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteFaq({{ $faq->id }})">Sil</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="faqPagination" class="d-flex justify-content-center mt-3">
                    {{ $faqs->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create FAQ Modal -->
<div class="modal fade" id="faqCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni SSS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.landing.faqs.store') }}">
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
              <label class="form-label">Soru *</label>
              <input type="text" name="question" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Cevap *</label>
              <textarea name="answer" class="form-control" rows="5" required></textarea>
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

<!-- Show FAQ Modal -->
<div class="modal fade" id="faqShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">SSS Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Soru</label>
              <p id="show-question" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Cevap</label>
              <p id="show-answer" class="form-control-plaintext"></p>
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

<!-- Edit FAQ Modal -->
<div class="modal fade" id="faqEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">SSS Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="faqEditForm">
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
              <label class="form-label">Soru *</label>
              <input type="text" name="question" id="edit-question" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Cevap *</label>
              <textarea name="answer" id="edit-answer" class="form-control" rows="5" required></textarea>
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

    // Create FAQ Form
    $('#faqCreateModal form').on('submit', function(e) {
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
                $('#faqCreateModal').modal('hide');
                toastr.success('SSS başarıyla oluşturuldu!');
                loadFaqs();
                $('#faqCreateModal form')[0].reset();
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

    // Edit FAQ Form
    $('#faqEditModal form').on('submit', function(e) {
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
                $('#faqEditModal').modal('hide');
                toastr.success('SSS başarıyla güncellendi!');
                loadFaqs();
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
    $('#faqShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var question = button.data('question');
        var answer = button.data('answer');

        $('#show-question').text(question);
        $('#show-answer').text(answer);
    });

    // Edit Modal - Fill data
    $('#faqEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var question = button.data('question');
        var answer = button.data('answer');

        $('#edit-question').val(question);
        $('#edit-answer').val(answer);
        $('#faqEditForm').attr('action', '/admin/landing/faqs/' + id);
    });

    // Load FAQs Function
    function loadFaqs(page = 1) {
        $.ajax({
            url: '/admin/landing/faqs',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#faqTableBody').html();
                var pagination = $(response).find('#faqPagination').html();

                $('#faqTableBody').html(tableBody);
                $('#faqPagination').html(pagination);

                bindEditModalEvents();
                bindFaqShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Edit Modal Events
    function bindEditModalEvents() {
        $('#faqEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var question = button.data('question');
            var answer = button.data('answer');

            $('#edit-question').val(question);
            $('#edit-answer').val(answer);
            $('#faqEditForm').attr('action', '/admin/landing/faqs/' + id);
        });
    }

    // Bind FAQ Show Modal Events
    function bindFaqShowModalEvents() {
        $('#faqShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var question = button.data('question');
            var answer = button.data('answer');

            $('#show-question').text(question);
            $('#show-answer').text(answer);
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadFaqs(page);
    });

    // Delete FAQ
    window.deleteFaq = function(id) {
        if (confirm('Bu SSS\'yi silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/landing/faqs/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadFaqs();
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
                        toastr.error('SSS silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize modal events on page load
    bindEditModalEvents();
    bindFaqShowModalEvents();
});
</script>
@endpush

@endsection

@include('admin.layouts.footer')
