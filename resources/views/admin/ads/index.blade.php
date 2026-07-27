@extends('admin.layouts.app')

@section('title', 'Reklamlar')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6"><h3>Reklamlar</h3></div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Reklamlar</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Reklam Listesi</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adCreateModal">Yeni Reklam</button>
            </div>
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
});
</script>
@endpush
