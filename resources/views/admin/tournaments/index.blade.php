@extends('admin.layouts.app')

@section('title', 'Turnuvalar')

@push('styles')
<style>
.page-title {
    margin-top: 2rem !important;
    padding-top: 1rem !important;
}
</style>
@endpush

@section('content')

<div class="page-title">
    <div class="row">
        <div class="col-6"><h3>Turnuvalar</h3></div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Turnuvalar</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">Turnuvalar</h4>
                @can('create tournaments')
                <div class="card-header-right">
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tournamentCreateModal">Yeni Turnuva</a>
                </div>
                @endcan
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Arama</label>
                        <input type="text" name="search" class="form-control" placeholder="Turnuva adı..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Durum</label>
                        <select name="status" class="form-select">
                            <option value="">Tüm Durumlar</option>
                            <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>Yaklaşan</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="finished" {{ request('status') == 'finished' ? 'selected' : '' }}>Tamamlandı</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>İptal</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small text-muted">Zorluk</label>
                        <select name="difficulty" class="form-select">
                            <option value="">Tüm Zorluklar</option>
                            <option value="easy" {{ request('difficulty') == 'easy' ? 'selected' : '' }}>Kolay</option>
                            <option value="medium" {{ request('difficulty') == 'medium' ? 'selected' : '' }}>Orta</option>
                            <option value="hard" {{ request('difficulty') == 'hard' ? 'selected' : '' }}>Zor</option>
                        </select>
                    </div>
                    <div class="col-md-12 col-lg-3 text-md-start text-lg-end">
                        <button type="submit" class="btn btn-primary me-2"><i data-feather="filter" class="me-1"></i>Filtrele</button>
                        <a href="{{ route('admin.tournaments.index') }}" class="btn btn-outline-secondary">Temizle</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Resim</th>
                                <th>Başlık</th>
                                <th>Kota</th>
                                <th>Katılımcı</th>
                                <th>Başlangıç</th>
                                <th>Bitiş</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="tournamentsTableBody">
                            @foreach($tournaments as $tournament)
                            <tr>
                                <td>{{ $tournament->id }}</td>
                                <td>
                                    @if($tournament->image)
                                        <img src="{{ asset('storage/' . $tournament->image) }}" width="40" height="40" class="rounded" alt="Tournament">
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i data-feather="award" class="text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $tournament->title }}</td>
                                <td>{{ $tournament->quota }}</td>
                                <td>{{ $tournament->tournament_users_count ?? 0 }}</td>
                                <td>{{ $tournament->start_date->format('d.m.Y H:i') }}</td>
                                <td>{{ $tournament->end_date->format('d.m.Y H:i') }}</td>
                              
                            
                                <td>
                                    @switch($tournament->status)
                                        @case('upcoming')
                                            <span class="badge bg-info">Yaklaşan</span>
                                            @break
                                        @case('active')
                                            <span class="badge bg-success">Aktif</span>
                                            @break
                                        @case('finished')
                                            <span class="badge bg-secondary">Tamamlandı</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge bg-danger">İptal</span>
                                            @break
                                    @endswitch
                                </td>
                                
                                <td>
                                    @can('view tournaments')
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#tournamentShowModal"
                                            data-id="{{ $tournament->id }}"
                                            data-title="{{ $tournament->title }}"
                                            data-description="{{ $tournament->description }}"
                                            data-quota="{{ $tournament->quota }}"
                                            data-start-date="{{ $tournament->start_date->format('d.m.Y') }}"
                                            data-end-date="{{ $tournament->end_date->format('d.m.Y') }}"
                                            data-start-time="{{ $tournament->start_time->format('H:i') }}"
                                            data-duration="{{ $tournament->duration_minutes }}"
                                            data-entry-fee="{{ number_format($tournament->entry_fee, 2) }}"
                                            data-question-count="{{ $tournament->question_count }}"
                                            data-difficulty="{{ $tournament->difficulty_level }}"
                                            data-status="{{ $tournament->status }}"
                                            data-featured="{{ $tournament->is_featured }}"
                                            data-participants="{{ $tournament->tournament_users_count ?? 0 }}"
                                            data-image="{{ $tournament->image }}">Görüntüle</button>
                                    @endcan
                                    @can('edit tournaments')
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#tournamentEditModal"
                                            data-id="{{ $tournament->id }}"
                                            data-title="{{ $tournament->title }}"
                                            data-description="{{ $tournament->description }}"
                                            data-quota="{{ $tournament->quota }}"
                                            data-start-date="{{ $tournament->start_date->format('Y-m-d') }}"
                                            data-end-date="{{ $tournament->end_date->format('Y-m-d') }}"
                                            data-start-time="{{ $tournament->start_time->format('H:i') }}"
                                            data-duration="{{ $tournament->duration_minutes }}"
                                            data-entry-fee="{{ $tournament->entry_fee }}"
                                            data-question-count="{{ $tournament->question_count }}"
                                            data-difficulty="{{ $tournament->difficulty_level }}"
                                            data-status="{{ $tournament->status }}"
                                            data-featured="{{ $tournament->is_featured }}">Düzenle</button>
                                    @endcan
                                    @can('delete tournaments')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTournament({{ $tournament->id }})">Sil</button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="tournamentsPagination" class="d-flex justify-content-center mt-3">
                    {{ $tournaments->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Create Tournament Modal -->
<div class="modal fade" id="tournamentCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Turnuva</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="tournamentCreateForm" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="title" class="form-label">Başlık <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="quota" class="form-label">Kota <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quota" name="quota" min="1" required>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="start_date" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="start_date" name="start_date" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="end_date" class="form-label">Bitiş Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="end_date" name="end_date" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="start_time" class="form-label">Başlangıç Saati <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="start_time" name="start_time" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="duration_minutes" class="form-label">Süre (Dakika) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="1" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="entry_fee" class="form-label">Giriş Tokeni (₺) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="entry_fee" name="entry_fee" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="question_count" class="form-label">Soru Sayısı <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="question_count" name="question_count" min="1" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="difficulty_level" class="form-label">Zorluk Seviyesi <span class="text-danger">*</span></label>
                <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                  <option value="">Seçiniz</option>
                  <option value="easy">Kolay</option>
                  <option value="medium">Orta</option>
                  <option value="hard">Zor</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="status" class="form-label">Durum <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                  <option value="">Seçiniz</option>
                  <option value="upcoming">Yaklaşan</option>
                  <option value="active">Aktif</option>
                  <option value="finished">Tamamlandı</option>
                  <option value="cancelled">İptal</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="image" class="form-label">Resim</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured">
                  <label class="form-check-label" for="is_featured">
                    Öne Çıkan
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Oluştur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Tournament Modal -->
<div class="modal fade" id="tournamentEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Turnuva Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="tournamentEditForm" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-title" class="form-label">Başlık <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit-title" name="title" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-quota" class="form-label">Kota <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="edit-quota" name="quota" min="1" required>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="edit-description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-start_date" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="edit-start_date" name="start_date" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-end_date" class="form-label">Bitiş Tarihi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="edit-end_date" name="end_date" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-start_time" class="form-label">Başlangıç Saati <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="edit-start_time" name="start_time" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-duration_minutes" class="form-label">Süre (Dakika) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="edit-duration_minutes" name="duration_minutes" min="1" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-entry_fee" class="form-label">Giriş Tokeni (₺) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="edit-entry_fee" name="entry_fee" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-question_count" class="form-label">Soru Sayısı <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="edit-question_count" name="question_count" min="1" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-difficulty_level" class="form-label">Zorluk Seviyesi <span class="text-danger">*</span></label>
                <select class="form-select" id="edit-difficulty_level" name="difficulty_level" required>
                  <option value="">Seçiniz</option>
                  <option value="easy">Kolay</option>
                  <option value="medium">Orta</option>
                  <option value="hard">Zor</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-status" class="form-label">Durum <span class="text-danger">*</span></label>
                <select class="form-select" id="edit-status" name="status" required>
                  <option value="">Seçiniz</option>
                  <option value="upcoming">Yaklaşan</option>
                  <option value="active">Aktif</option>
                  <option value="finished">Tamamlandı</option>
                  <option value="cancelled">İptal</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-image" class="form-label">Resim</label>
                <input type="file" class="form-control" id="edit-image" name="image" accept="image/*">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="edit-is_featured" name="is_featured">
                  <label class="form-check-label" for="edit-is_featured">
                    Öne Çıkan
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Show Tournament Modal -->
<div class="modal fade" id="tournamentShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Turnuva Detayları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Başlık</label>
              <p id="show-title" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Kota</label>
              <p id="show-quota" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Açıklama</label>
              <p id="show-description" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Başlangıç Tarihi</label>
              <p id="show-start-date" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Bitiş Tarihi</label>
              <p id="show-end-date" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Başlangıç Saati</label>
              <p id="show-start-time" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Süre (Dakika)</label>
              <p id="show-duration" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Giriş Tokeni</label>
              <p id="show-entry-fee" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Soru Sayısı</label>
              <p id="show-question-count" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Zorluk Seviyesi</label>
              <p id="show-difficulty" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Durum</label>
              <p id="show-status" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Öne Çıkan</label>
              <p id="show-featured" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Katılımcı Sayısı</label>
              <p id="show-participants" class="form-control-plaintext"></p>
            </div>
          </div>
          <div class="col-12">
            <div class="mb-3">
              <label class="form-label fw-bold">Resim</label>
              <div id="show-image" class="text-center">
                <!-- Image will be loaded here -->
              </div>
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Create Tournament
    $('#tournamentCreateForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var url = '{{ route("admin.tournaments.store") }}';
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#tournamentCreateModal').modal('hide');
                toastr.success('Turnuva başarıyla oluşturuldu!');
                loadTournaments();
                $('#tournamentCreateForm')[0].reset();
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
                    toastr.error('Turnuva oluşturulurken bir hata oluştu!');
                }
            }
        });
    });

    // Show Tournament Modal
    $('#tournamentShowModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var description = button.data('description');
        var quota = button.data('quota');
        var startDate = button.data('start-date');
        var endDate = button.data('end-date');
        var startTime = button.data('start-time');
        var duration = button.data('duration');
        var entryFee = button.data('entry-fee');
        var questionCount = button.data('question-count');
        var difficulty = button.data('difficulty');
        var status = button.data('status');
        var featured = button.data('featured');
        var participants = button.data('participants');
        var image = button.data('image');
        
        $('#show-title').text(title);
        $('#show-description').text(description || '-');
        $('#show-quota').text(quota);
        $('#show-start-date').text(startDate);
        $('#show-end-date').text(endDate);
        $('#show-start-time').text(startTime);
        $('#show-duration').text(duration + ' dakika');
        $('#show-entry-fee').text(entryFee + ' ₺');
        $('#show-question-count').text(questionCount);
        
        // Difficulty
        var difficultyText = '';
        if (difficulty === 'easy') difficultyText = 'Kolay';
        else if (difficulty === 'medium') difficultyText = 'Orta';
        else if (difficulty === 'hard') difficultyText = 'Zor';
        $('#show-difficulty').text(difficultyText);
        
        // Status
        var statusText = '';
        if (status === 'upcoming') statusText = 'Yaklaşan';
        else if (status === 'active') statusText = 'Aktif';
        else if (status === 'finished') statusText = 'Tamamlandı';
        else if (status === 'cancelled') statusText = 'İptal';
        $('#show-status').text(statusText);
        
        // Featured
        $('#show-featured').text(featured ? 'Evet' : 'Hayır');
        $('#show-participants').text(participants);
        
        // Image
        if (image) {
            $('#show-image').html('<img src="' + image + '" class="img-fluid rounded" style="max-width: 300px;" alt="Tournament Image">');
        } else {
            $('#show-image').html('<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 300px; height: 200px; margin: 0 auto;"><i data-feather="award" class="text-muted" style="width: 48px; height: 48px;"></i></div>');
        }
    });

    // Edit Tournament Modal
    $('#tournamentEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var title = button.data('title');
        var description = button.data('description');
        var quota = button.data('quota');
        var startDate = button.data('start-date');
        var endDate = button.data('end-date');
        var startTime = button.data('start-time');
        var duration = button.data('duration');
        var entryFee = button.data('entry-fee');
        var questionCount = button.data('question-count');
        var difficulty = button.data('difficulty');
        var status = button.data('status');
        var featured = button.data('featured');
        
        $('#edit-title').val(title);
        $('#edit-description').val(description);
        $('#edit-quota').val(quota);
        $('#edit-start_date').val(startDate);
        $('#edit-end_date').val(endDate);
        $('#edit-start_time').val(startTime);
        $('#edit-duration_minutes').val(duration);
        $('#edit-entry_fee').val(entryFee);
        $('#edit-question_count').val(questionCount);
        $('#edit-difficulty_level').val(difficulty);
        $('#edit-status').val(status);
        $('#edit-is_featured').prop('checked', featured);
        $('#tournamentEditForm').attr('action', '/private/lesley/admin/tournaments/' + id);
    });

    // Update Tournament
    $('#tournamentEditForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var url = $(this).attr('action');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#tournamentEditModal').modal('hide');
                toastr.success('Turnuva başarıyla güncellendi!');
                loadTournaments();
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
                    toastr.error('Turnuva güncellenirken bir hata oluştu!');
                }
            }
        });
    });

    // Load Tournaments Function
    function loadTournaments(page = 1) {
        $.ajax({
            url: '/private/lesley/admin/tournaments',
            type: 'GET',
            data: { page: page },
            success: function(response) {
                var tableBody = $(response).find('#tournamentsTableBody').html();
                var pagination = $(response).find('#tournamentsPagination').html();
                
                $('#tournamentsTableBody').html(tableBody);
                $('#tournamentsPagination').html(pagination);
            },
            error: function() {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            }
        });
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadTournaments(page);
    });

    // Delete Tournament
    window.deleteTournament = function(id) {
        if (confirm('Bu turnuvayı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/private/lesley/admin/tournaments/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadTournaments();
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
                        toastr.error('Turnuva silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };
});
</script>
@endpush

@include('admin.layouts.footer')
