@extends('admin.layouts.app')

@section('title', 'Reklamlar')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Reklamlar</h3>
            <p class="text-muted mb-0 small">Ya görsel ya video (ikisi birden değil) · max 10 sn · yönlendirme linki · izleme jetonu</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            <button type="button" class="btn btn-outline-secondary me-2" id="btnAdWatchStats" data-bs-toggle="modal" data-bs-target="#adWatchStatsModal">
                <i class="fa fa-bar-chart me-1"></i> İzleme Özeti
            </button>
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
                                <th>Önizleme</th>
                                <th>Medya</th>
                                <th>Jeton</th>
                                <th>Link</th>
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
                                    @if($ad->isVideoAd())
                                        <div class="ad-thumb-video" title="Video reklam">
                                            <i class="fa fa-play-circle"></i>
                                            <span>Video</span>
                                        </div>
                                    @else
                                        <img src="{{ $ad->image_url }}" alt="Ad {{ $ad->id }}" class="ad-thumb"
                                             style="width:120px;height:70px;object-fit:contain;background:#fff;border:1px solid #eee;border-radius:4px;padding:4px;">
                                    @endif
                                </td>
                                <td>
                                    @if($ad->isVideoAd())
                                        <span class="badge bg-info text-dark">Video ≤10sn</span>
                                    @else
                                        <span class="badge bg-secondary">Görsel</span>
                                    @endif
                                </td>
                                <td><strong>+{{ (int) ($ad->reward_coins ?? 1) }}</strong></td>
                                <td class="small" style="max-width:180px;">
                                    @if($ad->link)
                                        <a href="{{ $ad->link }}" target="_blank" rel="noopener" class="text-break">{{ $ad->resolvedCtaText() }}</a>
                                    @else
                                        —
                                    @endif
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
                                            data-image-url="{{ $ad->image_path === \App\Models\Ad::VIDEO_PLACEHOLDER_PATH ? '' : $ad->image_url }}"
                                            data-video-url="{{ $ad->video_url }}"
                                            data-link="{{ $ad->link }}"
                                            data-cta-text="{{ $ad->cta_text }}"
                                            data-reward-coins="{{ (int) ($ad->reward_coins ?? 1) }}"
                                            data-media-type="{{ $ad->mediaType() }}"
                                            data-has-video="{{ $ad->isVideoAd() ? 1 : 0 }}"
                                            data-sort="{{ $ad->sort_order }}"
                                            data-active="{{ $ad->is_active ? 1 : 0 }}">Düzenle</button>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-ad" data-id="{{ $ad->id }}">Sil</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">Henüz reklam yok.</td>
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

{{-- Ad watch stats modal --}}
<div class="modal fade" id="adWatchStatsModal" tabindex="-1" aria-labelledby="adWatchStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adWatchStatsModalLabel">Reklam İzleme Özeti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body" id="adWatchStatsBody">
                <div class="text-center py-5 text-muted" id="adWatchStatsLoading">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Yükleniyor…
                </div>
                <div id="adWatchStatsContent" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="adWatchStatsGenerated"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-outline-primary" id="btnAdWatchStatsRefresh">Yenile</button>
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
                        <label class="form-label d-block">Medya tipi <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check create-media-type" name="media_type" id="create_media_image" value="image" checked autocomplete="off">
                            <label class="btn btn-outline-primary" for="create_media_image">Görsel</label>
                            <input type="radio" class="btn-check create-media-type" name="media_type" id="create_media_video" value="video" autocomplete="off">
                            <label class="btn btn-outline-primary" for="create_media_video">Video</label>
                        </div>
                        <div class="form-text">Ya görsel ya video — ikisi birden seçilemez.</div>
                    </div>
                    <div class="mb-3 create-image-block">
                        <label class="form-label">Görsel <span class="text-danger">*</span></label>
                        <input type="file" name="image" id="create-image" class="form-control" accept="image/*">
                        <div class="form-text">JPEG / PNG / GIF / WebP · max 4 MB</div>
                    </div>
                    <div class="mb-3 create-video-block d-none">
                        <label class="form-label">Video <span class="text-danger">*</span></label>
                        <input type="file" name="video" id="create-video" class="form-control ad-video-input" accept="video/*,.mp4,.mov,.webm,.mkv,.avi,.m4v,.3gp,.mpeg,.mpg,.wmv,.flv,.ogv">
                        <div class="form-text text-warning">
                            <strong>Max 10 saniye.</strong> Yeni video yüklersen eski değişir.
                            Format: yaygın video türleri (MP4, MOV, WebM, MKV, AVI vb.) · max 20 MB.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yönlendirme linki</label>
                        <input type="url" name="link" class="form-control" placeholder="https://yudengames.com/" value="https://yudengames.com/">
                        <div class="form-text">Site, Play Store, App Store vb. CTA tıklanınca açılır.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CTA metni</label>
                        <input type="text" name="cta_text" class="form-control" value="Daha fazla bilgi al" maxlength="120">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İzleme jeton ödülü <span class="text-danger">*</span></label>
                        <input type="number" name="reward_coins" class="form-control" value="1" min="1" max="1000" required>
                        <div class="form-text">Kullanıcı bu reklamı izleyince kaç jeton alacak.</div>
                    </div>
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
                        <img id="edit-preview" src="" alt="Önizleme" class="d-none" style="max-width:100%;max-height:120px;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:4px;">
                        <div id="edit-video-wrap" class="mt-2 d-none">
                            <video id="edit-video-preview" controls playsinline muted style="max-width:100%;max-height:160px;background:#000;border-radius:4px;"></video>
                            <div class="small text-muted mt-1">Mevcut video</div>
                        </div>
                        <div id="edit-media-empty" class="text-muted small d-none">Önizleme yok</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" id="edit-title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Medya tipi <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check edit-media-type" name="media_type" id="edit_media_image" value="image" autocomplete="off">
                            <label class="btn btn-outline-primary" for="edit_media_image">Görsel</label>
                            <input type="radio" class="btn-check edit-media-type" name="media_type" id="edit_media_video" value="video" autocomplete="off">
                            <label class="btn btn-outline-primary" for="edit_media_video">Video</label>
                        </div>
                        <div class="form-text">Tip değişince diğer medya kaldırılır. Ya görsel ya video.</div>
                    </div>
                    <div class="mb-3 edit-image-block">
                        <label class="form-label">Görsel</label>
                        <input type="file" name="image" id="edit-image" class="form-control" accept="image/*">
                        <div class="form-text edit-image-hint">Görsel tipinde yeni dosya seçmezsen mevcut kalır.</div>
                    </div>
                    <div class="mb-3 edit-video-block d-none">
                        <label class="form-label">Video</label>
                        <input type="file" name="video" id="edit-video" class="form-control ad-video-input" accept="video/*,.mp4,.mov,.webm,.mkv,.avi,.m4v,.3gp,.mpeg,.mpg,.wmv,.flv,.ogv">
                        <div class="form-text text-warning">
                            <strong>Max 10 saniye.</strong> Yeni video yüklersen eski değişir. Yaygın video formatları · max 20 MB.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yönlendirme linki</label>
                        <input type="url" name="link" id="edit-link" class="form-control" placeholder="https://yudengames.com/">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CTA metni</label>
                        <input type="text" name="cta_text" id="edit-cta-text" class="form-control" maxlength="120" placeholder="Daha fazla bilgi al">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İzleme jeton ödülü <span class="text-danger">*</span></label>
                        <input type="number" name="reward_coins" id="edit-reward-coins" class="form-control" value="1" min="1" max="1000" required>
                    </div>
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
.ad-thumb-video {
    width: 120px; height: 70px; display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: .15rem; background: #111; color: #fff; border-radius: 4px; font-size: .75rem;
}
.ad-thumb-video i { font-size: 1.4rem; }
.ad-stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
@media (min-width: 768px) { .ad-stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.ad-stat-card { border: 1px solid #e9ecef; border-radius: .5rem; padding: .75rem 1rem; background: #fafafa; }
.ad-stat-card .label { font-size: .75rem; color: #6c757d; margin-bottom: .25rem; }
.ad-stat-card .value { font-size: 1.25rem; font-weight: 700; color: #212529; line-height: 1.2; }
.ad-stat-card .sub { font-size: .7rem; color: #868e96; margin-top: .15rem; }
.ad-stats-section-title { font-size: .85rem; font-weight: 600; color: #495057; margin: 1rem 0 .5rem; }
.ad-stats-table { font-size: .85rem; }
.ad-stats-table th { white-space: nowrap; }
.ad-stats-bar-wrap { display: flex; align-items: center; gap: .5rem; margin-bottom: .35rem; font-size: .8rem; }
.ad-stats-bar { flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
.ad-stats-bar > span { display: block; height: 100%; background: #7366ff; border-radius: 4px; min-width: 2px; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000 };

    var adStatsLoaded = false;
    var editHasVideo = false;
    var editHasImage = false;

    function fmt(n) {
        return Number(n || 0).toLocaleString('tr-TR');
    }

    function esc(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    function syncCreateMedia() {
        var type = $('input[name="media_type"]:checked', '#adCreateForm').val();
        if (type === 'video') {
            $('.create-image-block').addClass('d-none');
            $('.create-video-block').removeClass('d-none');
            $('#create-image').prop('required', false).val('');
            $('#create-video').prop('required', true);
        } else {
            $('.create-video-block').addClass('d-none');
            $('.create-image-block').removeClass('d-none');
            $('#create-video').prop('required', false).val('');
            $('#create-image').prop('required', true);
        }
    }

    function syncEditMedia() {
        var type = $('input[name="media_type"]:checked', '#adEditForm').val();
        if (type === 'video') {
            $('.edit-image-block').addClass('d-none');
            $('.edit-video-block').removeClass('d-none');
            $('#edit-image').val('');
            $('#edit-video').prop('required', !editHasVideo);
            if (editHasVideo) {
                $('#edit-video-wrap').removeClass('d-none');
            }
            if (editHasImage) {
                $('#edit-preview').removeClass('d-none');
            } else {
                $('#edit-preview').addClass('d-none');
            }
        } else {
            $('.edit-video-block').addClass('d-none');
            $('.edit-image-block').removeClass('d-none');
            $('#edit-video').prop('required', false).val('');
            $('#edit-video-wrap').addClass('d-none');
            $('#edit-image').prop('required', !editHasImage);
            if (editHasImage) {
                $('#edit-preview').removeClass('d-none');
            } else {
                $('#edit-preview').addClass('d-none');
            }
        }
        $('#edit-media-empty').toggleClass('d-none', editHasImage || (type === 'video' && editHasVideo));
    }

    $(document).on('change', '.create-media-type', syncCreateMedia);
    $(document).on('change', '.edit-media-type', syncEditMedia);
    syncCreateMedia();

    function renderUserTable(rows, emptyMsg) {
        if (!rows || !rows.length) {
            return '<p class="text-muted small mb-0">' + esc(emptyMsg) + '</p>';
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-striped ad-stats-table mb-0"><thead><tr><th>#</th><th>Kullanıcı</th><th class="text-end">İzleme</th></tr></thead><tbody>';
        rows.forEach(function (row, i) {
            var label = row.name;
            if (row.email) label += ' <span class="text-muted">(' + esc(row.email) + ')</span>';
            html += '<tr><td>' + (i + 1) + '</td><td>#' + row.user_id + ' · ' + label + '</td><td class="text-end"><strong>' + fmt(row.watch_count) + '</strong></td></tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function renderDailyBars(days, maxVal) {
        if (!days || !days.length) return '<p class="text-muted small mb-0">Veri yok.</p>';
        maxVal = maxVal || 1;
        var html = '';
        days.forEach(function (d) {
            var pct = Math.round((d.watch_count / maxVal) * 100);
            html += '<div class="ad-stats-bar-wrap"><span style="min-width:72px">' + esc(d.label) + '</span><div class="ad-stats-bar"><span style="width:' + pct + '%"></span></div><strong style="min-width:28px;text-align:right">' + fmt(d.watch_count) + '</strong><span class="text-muted" style="min-width:52px;text-align:right">' + fmt(d.unique_users) + ' kişi</span></div>';
        });
        return html;
    }

    function renderAdWatchStats(data) {
        var q = data.quota || {};
        var today = data.today || {};
        var all = data.all_time || {};
        var daily = data.daily_last_7_days || [];
        var maxDaily = Math.max.apply(null, daily.map(function (d) { return d.watch_count; }).concat([1]));

        var html = '';
        html += '<p class="text-muted small mb-3">24 saatte en fazla <strong>' + fmt(q.max_per_window) + '</strong> izleme hakkı · jeton ödülü <strong>reklama göre</strong> (reward_coins) değişir · medya: görsel <em>veya</em> video</p>';

        html += '<div class="ad-stats-grid mb-2">';
        html += '<div class="ad-stat-card"><div class="label">Bugün izleme</div><div class="value">' + fmt(today.total_watches) + '</div><div class="sub">' + fmt(today.unique_users) + ' kullanıcı</div></div>';
        html += '<div class="ad-stat-card"><div class="label">Bugün jeton</div><div class="value">' + fmt(today.coins_given) + '</div><div class="sub">verilen ödül (toplam)</div></div>';
        html += '<div class="ad-stat-card"><div class="label">Toplam izleme</div><div class="value">' + fmt(all.total_watches) + '</div><div class="sub">' + fmt(all.unique_users) + ' kullanıcı</div></div>';
        html += '<div class="ad-stat-card"><div class="label">Toplam jeton</div><div class="value">' + fmt(all.coins_given) + '</div><div class="sub">bugüne kadar</div></div>';
        html += '</div>';

        html += '<div class="ad-stats-grid mb-1" style="grid-template-columns:repeat(2,minmax(0,1fr))">';
        html += '<div class="ad-stat-card"><div class="label">Aktif pencere</div><div class="value">' + fmt(q.active_windows) + '</div><div class="sub">son ' + fmt(q.window_hours) + ' saat içinde izleyen</div></div>';
        html += '<div class="ad-stat-card"><div class="label">Hakkı dolmuş</div><div class="value">' + fmt(q.exhausted_now) + '</div><div class="sub">şu an ' + fmt(q.max_per_window) + '/' + fmt(q.max_per_window) + ' kullanan</div></div>';
        html += '</div>';

        html += '<div class="ad-stats-section-title">Son 7 gün <span class="text-muted fw-normal">(çubuk: izleme · sağ: benzersiz kullanıcı)</span></div>';
        html += renderDailyBars(daily, maxDaily);

        html += '<div class="ad-stats-section-title">Bugün — kullanıcı bazlı</div>';
        html += renderUserTable(data.today_by_user, 'Bugün henüz reklam izleme kaydı yok.');

        html += '<div class="ad-stats-section-title">Tüm zamanlar — en çok izleyenler</div>';
        html += renderUserTable(data.top_users_all_time, 'Henüz kayıt yok.');

        if (all.first_watch_at || all.last_watch_at) {
            html += '<p class="text-muted small mt-3 mb-0">İlk kayıt: ' + esc(all.first_watch_at || '—') + ' · Son kayıt: ' + esc(all.last_watch_at || '—') + '</p>';
        }

        $('#adWatchStatsContent').html(html).removeClass('d-none');
        $('#adWatchStatsGenerated').text(data.generated_at ? ('Güncellendi: ' + new Date(data.generated_at).toLocaleString('tr-TR')) : '');
    }

    function loadAdWatchStats(force) {
        if (adStatsLoaded && !force) return;
        $('#adWatchStatsLoading').removeClass('d-none');
        $('#adWatchStatsContent').addClass('d-none').empty();
        $.ajax({
            url: @json(route('admin.ads.watch-stats')),
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (res) {
                adStatsLoaded = true;
                $('#adWatchStatsLoading').addClass('d-none');
                if (res.success && res.data) {
                    renderAdWatchStats(res.data);
                } else {
                    $('#adWatchStatsContent').removeClass('d-none').html('<p class="text-danger mb-0">Özet alınamadı.</p>');
                }
            },
            error: function () {
                $('#adWatchStatsLoading').addClass('d-none');
                $('#adWatchStatsContent').removeClass('d-none').html('<p class="text-danger mb-0">Özet yüklenemedi.</p>');
            }
        });
    }

    $('#adWatchStatsModal').on('show.bs.modal', function () { loadAdWatchStats(false); });
    $('#btnAdWatchStatsRefresh').on('click', function () { adStatsLoaded = false; loadAdWatchStats(true); });

    $('.btn-edit-ad').on('click', function () {
        var id = $(this).data('id');
        var videoUrl = $(this).data('video-url') || '';
        var imageUrl = $(this).data('image-url') || '';
        var mediaType = $(this).data('media-type') || 'image';
        editHasVideo = String($(this).data('has-video')) === '1' && !!videoUrl;
        editHasImage = !!imageUrl;

        $('#adEditForm').attr('action', '/admin/ads/' + id);
        $('#edit-title').val($(this).data('title') || '');
        $('#edit-sort').val($(this).data('sort'));
        $('#edit-link').val($(this).data('link') || '');
        $('#edit-cta-text').val($(this).data('cta-text') || 'Daha fazla bilgi al');
        $('#edit-reward-coins').val($(this).data('reward-coins') || 1);
        $('#edit_is_active').prop('checked', String($(this).data('active')) === '1');
        $('#edit-image').val('');
        $('#edit-video').val('');

        if (editHasImage) {
            $('#edit-preview').attr('src', imageUrl).removeClass('d-none');
        } else {
            $('#edit-preview').removeAttr('src').addClass('d-none');
        }

        if (editHasVideo) {
            $('#edit-video-preview').attr('src', videoUrl);
            $('#edit-video-wrap').removeClass('d-none');
        } else {
            $('#edit-video-preview').removeAttr('src');
            $('#edit-video-wrap').addClass('d-none');
        }

        if (mediaType === 'video') {
            $('#edit_media_video').prop('checked', true);
        } else {
            $('#edit_media_image').prop('checked', true);
        }
        syncEditMedia();
        $('#adEditModal').modal('show');
    });

    $('#adCreateForm, #adEditForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var type = $('input[name="media_type"]:checked', $form).val();
        if ($form.attr('id') === 'adCreateForm') {
            if (type === 'image' && !$('#create-image')[0].files.length) {
                toastr.error('Görsel reklam için görsel seçin.');
                return;
            }
            if (type === 'video' && !$('#create-video')[0].files.length) {
                toastr.error('Video reklam için video seçin.');
                return;
            }
        } else {
            if (type === 'image' && !editHasImage && !$('#edit-image')[0].files.length) {
                toastr.error('Görsel reklam için görsel seçin.');
                return;
            }
            if (type === 'video' && !editHasVideo && !$('#edit-video')[0].files.length) {
                toastr.error('Video reklam için video seçin.');
                return;
            }
        }

        var formData = new FormData(this);
        // XOR: seçilmeyen medya alanını gönderme
        if (type === 'image') {
            formData.delete('video');
        } else {
            formData.delete('image');
        }

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
});
</script>
@endpush
