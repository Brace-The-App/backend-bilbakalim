@extends('admin.layouts.app')

@section('title', 'Reklamlar')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Reklamlar</h3>
            <p class="text-muted mb-0 small">Liste ve içerik yönetimi</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adCreateModal">Yeni Reklam</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Başlık</th>
                                <th>Görsel</th>
                                <th>Sıra</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ads as $ad)
                            <tr>
                                <td>{{ $ad->id }}</td>
                                <td>{{ $ad->title ?: '—' }}</td>
                                <td>
                                    <img src="{{ $ad->image_url }}" alt="Ad {{ $ad->id }}" class="ad-thumb"
                                         style="width:120px;height:70px;object-fit:contain;background:#fff;border:1px solid #eee;border-radius:4px;padding:4px;">
                                </td>
                                <td>{{ $ad->sort_order }}</td>
                                <td>
                                    @if($ad->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-danger">Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning btn-edit-ad"
                                            data-id="{{ $ad->id }}"
                                            data-title="{{ $ad->title }}"
                                            data-image-url="{{ $ad->image_url }}"
                                            data-sort="{{ $ad->sort_order }}"
                                            data-active="{{ $ad->is_active ? 1 : 0 }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-ad" data-id="{{ $ad->id }}">Sil</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Henüz reklam yok.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    {{ $ads->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="adCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.ads.store') }}" enctype="multipart/form-data" id="adCreateForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Reklam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control" placeholder="Opsiyonel">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Görsel <span class="text-danger">*</span></label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>
                    {{--
                    VIDEO (şimdilik gizli — ileride yorumu kaldır):
                    Görsel yerine / yanında kısa video yüklenebilir. Max 10 sn.
                    <div class="mb-3">
                        <label class="form-label">Video <span class="text-muted small">(opsiyonel, max 10 sn)</span></label>
                        <input type="file" name="video" class="form-control ad-video-input" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm">
                        <div class="form-text text-warning">Uyarı: Video en fazla 10 saniye olmalıdır. Daha uzun videolar reddedilir. Önerilen format: MP4.</div>
                    </div>
                    --}}
                    {{--
                    LINK (şimdilik gizli — ileride yorumu kaldır):
                    API'de link dönmeye devam eder; mevcut kayıtlar korunur.
                    <div class="mb-3">
                        <label class="form-label">Link <span class="text-muted small">(mobilde tıklanınca açılır)</span></label>
                        <input type="url" name="link" class="form-control" placeholder="https://yudengames.com/">
                    </div>
                    --}}
                    <div class="mb-3">
                        <label class="form-label">Sıra</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="create_is_active" checked>
                        <label class="form-check-label" for="create_is_active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="adEditModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" id="adEditForm">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reklam Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <img id="edit-preview" src="" alt="Önizleme" style="max-width:100%;max-height:120px;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:4px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" id="edit-title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Görsel (opsiyonel)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    {{--
                    VIDEO (şimdilik gizli — ileride yorumu kaldır):
                    <div class="mb-3">
                        <label class="form-label">Video <span class="text-muted small">(opsiyonel, max 10 sn)</span></label>
                        <input type="file" name="video" class="form-control ad-video-input" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm">
                        <div class="form-text text-warning">Uyarı: Video en fazla 10 saniye olmalıdır. Daha uzun videolar reddedilir.</div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_video" id="edit_remove_video" value="1">
                            <label class="form-check-label" for="edit_remove_video">Mevcut videoyu kaldır</label>
                        </div>
                    </div>
                    --}}
                    {{--
                    LINK (şimdilik gizli — ileride yorumu kaldır):
                    <div class="mb-3">
                        <label class="form-label">Link <span class="text-muted small">(mobilde tıklanınca açılır)</span></label>
                        <input type="url" name="link" id="edit-link" class="form-control" placeholder="https://yudengames.com/">
                    </div>
                    --}}
                    {{-- link + video_path alt yapı hazır; panel UI yorumda --}}
                    <div class="mb-3">
                        <label class="form-label">Sıra</label>
                        <input type="number" name="sort_order" id="edit-sort" class="form-control" min="0">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.page-title { margin-top: 2rem !important; padding-top: 1rem !important; }
.ad-thumb { transition: transform .15s ease; }
.ad-thumb:hover { transform: scale(1.15); position: relative; z-index: 2; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000 };

    $('.btn-edit-ad').on('click', function () {
        var id = $(this).data('id');
        $('#adEditForm').attr('action', '/admin/ads/' + id);
        $('#edit-title').val($(this).data('title') || '');
        $('#edit-sort').val($(this).data('sort'));
        $('#edit-preview').attr('src', $(this).data('image-url'));
        $('#edit_is_active').prop('checked', String($(this).data('active')) === '1');
        $('#adEditModal').modal('show');
    });

    $('#adCreateForm, #adEditForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var formData = new FormData(this);
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (res) {
                toastr.success(res.message || 'Kaydedildi');
                setTimeout(function () { location.reload(); }, 600);
            },
            error: function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'İşlem başarısız';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                toastr.error(msg);
            }
        });
    });

    $('.btn-delete-ad').on('click', function () {
        var id = $(this).data('id');
        if (!confirm('Bu reklamı silmek istediğinize emin misiniz?')) return;
        $.ajax({
            url: '/admin/ads/' + id,
            type: 'POST',
            data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (res) {
                toastr.success(res.message || 'Silindi');
                setTimeout(function () { location.reload(); }, 600);
            },
            error: function () { toastr.error('Silinemedi'); }
        });
    });

    /*
    // VIDEO max 10 sn istemci kontrolü — panel video alanı açılınca yorumu kaldır
    var AD_VIDEO_MAX_SEC = 10;
    $(document).on('change', '.ad-video-input', function () {
        var input = this;
        var file = input.files && input.files[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) {
            toastr.error('Video en fazla 20 MB olabilir.');
            input.value = '';
            return;
        }
        var url = URL.createObjectURL(file);
        var video = document.createElement('video');
        video.preload = 'metadata';
        video.onloadedmetadata = function () {
            URL.revokeObjectURL(url);
            if (video.duration > AD_VIDEO_MAX_SEC + 0.05) {
                toastr.error('Video en fazla ' + AD_VIDEO_MAX_SEC + ' saniye olabilir. Seçilen: ' + video.duration.toFixed(1) + ' sn.');
                input.value = '';
            } else {
                toastr.info('Video süresi uygun: ' + video.duration.toFixed(1) + ' sn.');
            }
        };
        video.onerror = function () {
            URL.revokeObjectURL(url);
            toastr.warning('Video süresi okunamadı. Sunucu kontrolü uygulanacak.');
        };
        video.src = url;
    });
    */
});
</script>
@endpush
