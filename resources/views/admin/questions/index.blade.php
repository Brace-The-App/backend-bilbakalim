@extends('admin.layouts.app')

@section('title', 'Sorular')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6"><h3>Sorular</h3></div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Sorular</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Soru Listesi</h5>
                @can('create questions')
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#questionCreateModal">Yeni Soru</a>
                </div>
                @endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Soru</th>
                                <th>Kategori</th>
                                <th>Seviye</th>
                                <th>Coin</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="questionsTableBody">
                            @foreach(($questions ?? []) as $question)
                            <tr>
                                <td>{{ $question->id }}</td>
                                <td>{{ Str::limit($question->getTranslation('question', 'tr'), 60) }}</td>
                                <td>{{ $question->category->getTranslation('name', 'tr') ?? '-' }}</td>
                                <td>
                                    @if($question->question_level === 'easy')
                                        <span class="badge bg-success px-1 py-0" style="font-size: 0.75rem;">Kolay</span>
                                    @elseif($question->question_level === 'medium')
                                        <span class="badge bg-warning px-1 py-0" style="font-size: 0.75rem;">Orta</span>
                                    @else
                                        <span class="badge bg-danger px-1 py-0" style="font-size: 0.75rem;">Zor</span>
                                    @endif
                                </td>
                                <td>{{ $question->coin_value }}</td>
                                <td>
                                    @if($question->is_active)
                                        <span class="badge bg-success px-1 py-0" style="font-size: 0.75rem;">Aktif</span>
                                    @else
                                        <span class="badge bg-danger px-1 py-0" style="font-size: 0.75rem;">Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#questionShowModal"
                                            data-id="{{ $question->id }}"
                                            data-question-tr="{{ $question->getTranslation('question', 'tr') }}"
                                            data-question-en="{{ $question->getTranslation('question', 'en') }}"
                                            data-a1-tr="{{ $question->getTranslation('one_choice', 'tr') }}"
                                            data-a1-en="{{ $question->getTranslation('one_choice', 'en') }}"
                                            data-a2-tr="{{ $question->getTranslation('two_choice', 'tr') }}"
                                            data-a2-en="{{ $question->getTranslation('two_choice', 'en') }}"
                                            data-a3-tr="{{ $question->getTranslation('three_choice', 'tr') }}"
                                            data-a3-en="{{ $question->getTranslation('three_choice', 'en') }}"
                                            data-a4-tr="{{ $question->getTranslation('four_choice', 'tr') }}"
                                            data-a4-en="{{ $question->getTranslation('four_choice', 'en') }}"
                                            data-right="{{ $question->correct_answer }}"
                                            data-level="{{ $question->question_level }}"
                                            data-coin="{{ $question->coin_value }}"
                                            data-active="{{ $question->is_active }}"
                                            data-category="{{ $question->category_id }}">Görüntüle</button>
                                    @can('edit questions')
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#questionEditModal"
                                            data-id="{{ $question->id }}"
                                            data-question-tr="{{ $question->getTranslation('question', 'tr') }}"
                                            data-question-en="{{ $question->getTranslation('question', 'en') }}"
                                            data-a1-tr="{{ $question->getTranslation('one_choice', 'tr') }}"
                                            data-a1-en="{{ $question->getTranslation('one_choice', 'en') }}"
                                            data-a2-tr="{{ $question->getTranslation('two_choice', 'tr') }}"
                                            data-a2-en="{{ $question->getTranslation('two_choice', 'en') }}"
                                            data-a3-tr="{{ $question->getTranslation('three_choice', 'tr') }}"
                                            data-a3-en="{{ $question->getTranslation('three_choice', 'en') }}"
                                            data-a4-tr="{{ $question->getTranslation('four_choice', 'tr') }}"
                                            data-a4-en="{{ $question->getTranslation('four_choice', 'en') }}"
                                            data-right="{{ $question->correct_answer }}"
                                            data-level="{{ $question->question_level }}"
                                            data-coin="{{ $question->coin_value }}"
                                            data-active="{{ $question->is_active }}"
                                            data-category="{{ $question->category_id }}">Düzenle</button>
                                    @endcan
                                    @can('delete questions')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteQuestion({{ $question->id }})">Sil</button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="questionsPagination" class="d-flex justify-content-center mt-3">
                    {{ $questions->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Question Modal -->
<div class="modal fade" id="questionCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Yeni Soru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('admin.questions.store') }}">
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
          <ul class="nav nav-tabs" id="createQuestionTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="q-tr-tab" data-bs-toggle="tab" data-bs-target="#q-tr-pane" type="button" role="tab">
                🇹🇷 Türkçe
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="q-en-tab" data-bs-toggle="tab" data-bs-target="#q-en-pane" type="button" role="tab">
                🇬🇧 English
              </button>
            </li>
          </ul>
          
          <!-- Tab Content -->
          <div class="tab-content mt-3" id="createQuestionTabContent">
            <!-- Turkish Tab -->
            <div class="tab-pane fade show active" id="q-tr-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Soru (Türkçe) *</label>
                  <textarea name="question[tr]" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 1 (Türkçe) *</label>
                  <input type="text" name="one_choice[tr]" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 2 (Türkçe) *</label>
                  <input type="text" name="two_choice[tr]" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 3 (Türkçe) *</label>
                  <input type="text" name="three_choice[tr]" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 4 (Türkçe) *</label>
                  <input type="text" name="four_choice[tr]" class="form-control" required>
                </div>
              </div>
            </div>
            
            <!-- English Tab -->
            <div class="tab-pane fade" id="q-en-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Soru (English)</label>
                  <textarea name="question[en]" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 1 (English)</label>
                  <input type="text" name="one_choice[en]" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 2 (English)</label>
                  <input type="text" name="two_choice[en]" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 3 (English)</label>
                  <input type="text" name="three_choice[en]" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 4 (English)</label>
                  <input type="text" name="four_choice[en]" class="form-control">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Other Fields -->
          <div class="row g-3 mt-3">
          <div class="col-md-4">
            <label class="form-label">Doğru Şık *</label>
            <select name="correct_answer" class="form-select" required>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Seviye *</label>
            <select name="question_level" class="form-select" required>
              <option value="easy">Kolay</option>
              <option value="medium">Orta</option>
              <option value="hard">Zor</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Coin Değeri</label>
            <input type="number" name="coin_value" class="form-control" value="10">
          </div>
          <div class="col-md-6">
            <label class="form-label">Kategori *</label>
            <select name="category_id" class="form-select" required>
              <option value="">Kategori Seçin</option>
              @foreach($categories ?? [] as $category)
                <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'tr') }}</option>
              @endforeach
            </select>
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

<!-- Show Question Modal -->
<div class="modal fade" id="questionShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Soru Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Türkçe Soru</label>
              <p id="show-question-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">İngilizce Soru</label>
              <p id="show-question-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">A) Türkçe</label>
              <p id="show-a1-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">A) İngilizce</label>
              <p id="show-a1-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">B) Türkçe</label>
              <p id="show-a2-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">B) İngilizce</label>
              <p id="show-a2-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">C) Türkçe</label>
              <p id="show-a3-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">C) İngilizce</label>
              <p id="show-a3-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">D) Türkçe</label>
              <p id="show-a4-tr" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">D) İngilizce</label>
              <p id="show-a4-en" class="form-control-plaintext"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label fw-bold">Doğru Cevap</label>
              <p id="show-right" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label fw-bold">Seviye</label>
              <p id="show-level" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label fw-bold">Coin</label>
              <p id="show-coin" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-3">
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

<!-- Edit Question Modal -->
<div class="modal fade" id="questionEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Soruyu Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" id="questionEditForm">
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
          <ul class="nav nav-tabs" id="editQuestionTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="edit-q-tr-tab" data-bs-toggle="tab" data-bs-target="#edit-q-tr-pane" type="button" role="tab">
                🇹🇷 Türkçe
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="edit-q-en-tab" data-bs-toggle="tab" data-bs-target="#edit-q-en-pane" type="button" role="tab">
                🇬🇧 English
              </button>
            </li>
          </ul>
          
          <!-- Tab Content -->
          <div class="tab-content mt-3" id="editQuestionTabContent">
            <!-- Turkish Tab -->
            <div class="tab-pane fade show active" id="edit-q-tr-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Soru (Türkçe) *</label>
                  <textarea name="question[tr]" id="edit-q" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 1 (Türkçe) *</label>
                  <input type="text" name="one_choice[tr]" id="edit-a1" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 2 (Türkçe) *</label>
                  <input type="text" name="two_choice[tr]" id="edit-a2" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 3 (Türkçe) *</label>
                  <input type="text" name="three_choice[tr]" id="edit-a3" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 4 (Türkçe) *</label>
                  <input type="text" name="four_choice[tr]" id="edit-a4" class="form-control" required>
                </div>
              </div>
            </div>
            
            <!-- English Tab -->
            <div class="tab-pane fade" id="edit-q-en-pane" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Soru (English)</label>
                  <textarea name="question[en]" id="edit-q-en" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 1 (English)</label>
                  <input type="text" name="one_choice[en]" id="edit-a1-en" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 2 (English)</label>
                  <input type="text" name="two_choice[en]" id="edit-a2-en" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 3 (English)</label>
                  <input type="text" name="three_choice[en]" id="edit-a3-en" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Şık 4 (English)</label>
                  <input type="text" name="four_choice[en]" id="edit-a4-en" class="form-control">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Other Fields -->
          <div class="row g-3 mt-3">
          <div class="col-md-4">
            <label class="form-label">Doğru Şık *</label>
            <select name="correct_answer" id="edit-right" class="form-select" required>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Seviye *</label>
            <select name="question_level" id="edit-level" class="form-select" required>
              <option value="easy">Kolay</option>
              <option value="medium">Orta</option>
              <option value="hard">Zor</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Coin Değeri</label>
            <input type="number" name="coin_value" id="edit-coin" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Kategori *</label>
            <select name="category_id" id="edit-category" class="form-select" required>
              <option value="">Kategori Seçin</option>
              @foreach($categories ?? [] as $category)
                <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'tr') }}</option>
              @endforeach
            </select>
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
@endsection

@push('styles')
<style>
/* Extra spacing to prevent header overlap on questions page */
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

    // Create Question Form
    $('#questionCreateModal form').on('submit', function(e) {
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
                $('#questionCreateModal').modal('hide');
                // Show success message
                toastr.success('Soru başarıyla oluşturuldu!');
                // Reload data without page refresh
                loadQuestions();
                // Reset form
                $('#questionCreateModal form')[0].reset();
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

    // Edit Question Form
    $('#questionEditModal form').on('submit', function(e) {
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
                $('#questionEditModal').modal('hide');
                // Show success message
                toastr.success('Soru başarıyla güncellendi!');
                // Reload data without page refresh
                loadQuestions();
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


    // Load Questions Function
    function loadQuestions(page = 1) {
        $.ajax({
            url: '/admin/questions',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                // Extract table body and pagination from response
                var tableBody = $(response).find('#questionsTableBody').html();
                var pagination = $(response).find('#questionsPagination').html();
                
                // Update table body
                $('#questionsTableBody').html(tableBody);
                
                // Update pagination
                $('#questionsPagination').html(pagination);
                
                // Re-bind edit modal events
                bindQuestionEditModalEvents();
                // Re-bind show modal events
                bindQuestionShowModalEvents();
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Bind Question Edit Modal Events
    function bindQuestionEditModalEvents() {
        $('#questionEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var questionTr = button.data('question-tr');
            var questionEn = button.data('question-en');
            var a1Tr = button.data('a1-tr');
            var a1En = button.data('a1-en');
            var a2Tr = button.data('a2-tr');
            var a2En = button.data('a2-en');
            var a3Tr = button.data('a3-tr');
            var a3En = button.data('a3-en');
            var a4Tr = button.data('a4-tr');
            var a4En = button.data('a4-en');
            var right = button.data('right');
            var level = button.data('level');
            var coin = button.data('coin');
            var active = button.data('active');
            var category = button.data('category');
            
            $('#edit-q').val(questionTr || '');
            $('#edit-q-en').val(questionEn || '');
            $('#edit-a1').val(a1Tr || '');
            $('#edit-a1-en').val(a1En || '');
            $('#edit-a2').val(a2Tr || '');
            $('#edit-a2-en').val(a2En || '');
            $('#edit-a3').val(a3Tr || '');
            $('#edit-a3-en').val(a3En || '');
            $('#edit-a4').val(a4Tr || '');
            $('#edit-a4-en').val(a4En || '');
            $('#edit-right').val(right);
            $('#edit-level').val(level);
            $('#edit-coin').val(coin);
            $('#edit-active').prop('checked', active == 1);
            $('#edit-category').val(category);
            $('#questionEditForm').attr('action', '/admin/questions/' + id);
        });
    }

    // Bind Question Show Modal Events
    function bindQuestionShowModalEvents() {
        $('#questionShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var questionTr = button.data('question-tr');
            var questionEn = button.data('question-en');
            var a1Tr = button.data('a1-tr');
            var a1En = button.data('a1-en');
            var a2Tr = button.data('a2-tr');
            var a2En = button.data('a2-en');
            var a3Tr = button.data('a3-tr');
            var a3En = button.data('a3-en');
            var a4Tr = button.data('a4-tr');
            var a4En = button.data('a4-en');
            var right = button.data('right');
            var level = button.data('level');
            var coin = button.data('coin');
            var active = button.data('active');
            var category = button.data('category');
            
            $('#show-question-tr').text(questionTr || '-');
            $('#show-question-en').text(questionEn || '-');
            $('#show-a1-tr').text(a1Tr || '-');
            $('#show-a1-en').text(a1En || '-');
            $('#show-a2-tr').text(a2Tr || '-');
            $('#show-a2-en').text(a2En || '-');
            $('#show-a3-tr').text(a3Tr || '-');
            $('#show-a3-en').text(a3En || '-');
            $('#show-a4-tr').text(a4Tr || '-');
            $('#show-a4-en').text(a4En || '-');
            $('#show-right').text(right || '-');
            $('#show-level').html(level === 'easy' ? '<span class="badge bg-success">Kolay</span>' : 
                                 level === 'medium' ? '<span class="badge bg-warning">Orta</span>' : 
                                 level === 'hard' ? '<span class="badge bg-danger">Zor</span>' : level);
            $('#show-coin').text(coin || '0');
            $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadQuestions(page);
    });

    // Delete Question
    window.deleteQuestion = function(id) {
        if (confirm('Bu soruyu silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/questions/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        // Reload data without page refresh
                        loadQuestions();
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
                        toastr.error('Soru silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };

    // Initialize question edit modal events on page load
    bindQuestionEditModalEvents();
    // Initialize question show modal events on page load
    bindQuestionShowModalEvents();

    // Anlık soru güncellemeleri
    if (typeof window.socketClient !== 'undefined') {
        // Socket bağlantısı kurulduğunda soru listesini yükle
        window.socketClient.socket.on('connect', function() {
            console.log('Socket bağlandı, soru listesi yükleniyor...');
            loadQuestions();
        });

        // Soru güncellemelerini dinle
        window.socketClient.socket.on('question_created', function(data) {
            console.log('Yeni soru oluşturuldu:', data);
            loadQuestions(); // Sayfayı yenile
            toastr.success('Yeni soru eklendi!', 'BilBakalim');
        });

        window.socketClient.socket.on('question_updated', function(data) {
            console.log('Soru güncellendi:', data);
            loadQuestions(); // Sayfayı yenile
            toastr.info('Soru güncellendi!', 'BilBakalim');
        });

        window.socketClient.socket.on('question_deleted', function(data) {
            console.log('Soru silindi:', data);
            loadQuestions(); // Sayfayı yenile
            toastr.warning('Soru silindi!', 'BilBakalim');
        });

        // Kategori güncellemelerini dinle
        window.socketClient.socket.on('category_updated', function(data) {
            console.log('Kategori güncellendi:', data);
            loadQuestions(); // Sayfayı yenile
            toastr.info('Kategori güncellendi!', 'BilBakalim');
        });
    }

    // Sayfa yüklendiğinde soru listesini yükle
    loadQuestions();

});
</script>
@endpush


@include('admin.layouts.footer')