@extends('admin.layouts.app')

@section('title', 'Kullanıcılar')

@section('content')
@php
    $defaultAvatar = asset('assets/images/dashboard/profile.jpg');
    $hasFilters = request()->filled('search')
        || request()->filled('role')
        || request()->filled('status')
        || request()->filled('online')
        || request()->filled('premium')
        || request()->filled('sort_coins');
@endphp

<div class="page-title users-page-title">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Kullanıcılar</h3>
            <p class="text-muted mb-0 small">Liste, filtre ve hızlı işlemler</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            @can('create users')
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userCreateModal">Yeni Kullanıcı</a>
            @endcan
        </div>
    </div>
</div>

{{-- 1) Özet şerit --}}
<div class="users-summary mb-3">
    <a href="{{ route('admin.users.index', ['online' => '']) }}" class="users-summary__card {{ request()->has('online') && request('online') !== '1' && !request()->hasAny(['status','premium']) ? 'is-active' : '' }}">
        <span class="users-summary__label">Toplam</span>
        <strong class="users-summary__value">{{ number_format($summary['total']) }}</strong>
    </a>
    <a href="{{ route('admin.users.index', ['online' => 1]) }}" class="users-summary__card {{ request('online') == '1' ? 'is-active' : '' }}">
        <span class="users-summary__label">Çevrimiçi</span>
        <strong class="users-summary__value text-success">{{ number_format($summary['online']) }}</strong>
    </a>
    <a href="{{ route('admin.users.index', ['status' => 'suspended']) }}" class="users-summary__card {{ request('status') == 'suspended' ? 'is-active' : '' }}">
        <span class="users-summary__label">Askıda</span>
        <strong class="users-summary__value text-danger">{{ number_format($summary['suspended']) }}</strong>
    </a>
    <a href="{{ route('admin.users.index', ['premium' => 1]) }}" class="users-summary__card {{ request('premium') == '1' ? 'is-active' : '' }}">
        <span class="users-summary__label">Premium</span>
        <strong class="users-summary__value text-warning">{{ number_format($summary['premium']) }}</strong>
    </a>
</div>

<div class="card users-card">
    <div class="card-body">
        {{-- Filtreler — responsive --}}
        <form method="GET" action="{{ route('admin.users.index') }}" class="users-filter mb-3">
            @if(request('sort_coins'))
                <input type="hidden" name="sort_coins" value="{{ request('sort_coins') }}">
            @endif

            <div class="users-filter__grid">
                <div class="users-filter__field users-filter__field--search">
                    <label class="form-label small text-muted mb-1">Ara</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i data-feather="search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Ad, email, telefon, ID..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="users-filter__field">
                    <label class="form-label small text-muted mb-1">Rol</label>
                    <select name="role" class="form-select">
                        <option value="">Tümü</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="users-filter__field">
                    <label class="form-label small text-muted mb-1">Hesap</label>
                    <select name="status" class="form-select">
                        <option value="">Tümü</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Askıda</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Beklemede</option>
                    </select>
                </div>

                <div class="users-filter__field">
                    <label class="form-label small text-muted mb-1">Çevrimiçi</label>
                    <select name="online" class="form-select">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('online') == '1' ? 'selected' : '' }}>Evet</option>
                    </select>
                </div>

                <div class="users-filter__field">
                    <label class="form-label small text-muted mb-1">Premium</label>
                    <select name="premium" class="form-select">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('premium') == '1' ? 'selected' : '' }}>Evet</option>
                    </select>
                </div>

                <div class="users-filter__field">
                    <label class="form-label small text-muted mb-1">Sayfa</label>
                    <select name="per_page" class="form-select">
                        <option value="10" {{ (int)$perPage === 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ (int)$perPage === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ (int)$perPage === 50 ? 'selected' : '' }}>50</option>
                    </select>
                </div>

                <div class="users-filter__actions">
                    <button type="submit" class="btn btn-primary users-filter__btn">
                        <i data-feather="filter"></i><span>Filtrele</span>
                    </button>
                    <a href="{{ route('admin.users.index', ['online' => '']) }}" class="btn btn-outline-secondary users-filter__btn {{ $hasFilters ? '' : 'disabled' }}">
                        <i data-feather="x"></i><span>Temizle</span>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle users-table mb-0">
                <thead>
                    @php
                        $coinSort = request('sort_coins');
                        $nextCoinSort = $coinSort === 'desc' ? 'asc' : 'desc';
                        $coinSortUrl = request()->fullUrlWithQuery(['sort_coins' => $nextCoinSort, 'page' => null]);
                    @endphp
                    <tr>
                        <th>Kullanıcı</th>
                        <th class="d-none d-md-table-cell">İletişim</th>
                        <th>Rol</th>
                        <th>
                            <a href="{{ $coinSortUrl }}" class="text-decoration-none text-dark">
                                Jeton
                                @if($coinSort === 'desc') ↓ @elseif($coinSort === 'asc') ↑ @else ⇅ @endif
                            </a>
                        </th>
                        <th>Durum</th>
                        <th class="d-none d-lg-table-cell">Giriş / Aktif</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                        $isOnline = (bool) ($user->is_online ?? false);
                        $fullName = trim($user->name . ' ' . ($user->surname ?? ''));
                        $avatarUrl = $defaultAvatar;
                        if (!empty($user->profile_image)) {
                            // Kullanıcının seçtiği / yüklediği profil fotoğrafı öncelikli
                            $avatarUrl = filter_var($user->profile_image, FILTER_VALIDATE_URL)
                                ? $user->profile_image
                                : asset('storage/' . ltrim(preg_replace('#^storage/#', '', $user->profile_image), '/'));
                        } elseif ($user->avatarModel && $user->avatarModel->image_url) {
                            $avatarUrl = $user->avatarModel->image_url;
                        }
                        $statusLabel = match($user->account_status) {
                            'suspended' => 'Askıda',
                            'pending' => 'Beklemede',
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="users-person">
                                <div class="users-person__avatar-wrap">
                                    <img src="{{ $avatarUrl }}" alt="" class="users-person__avatar" onerror="this.src='{{ $defaultAvatar }}'">
                                    <span class="users-person__dot {{ $isOnline ? 'is-on' : '' }}" title="{{ $isOnline ? 'Çevrimiçi' : 'Çevrimdışı' }}"></span>
                                </div>
                                <div class="users-person__meta">
                                    <div class="users-person__name">
                                        {{ $fullName ?: 'İsimsiz' }}
                                        @if(!empty($user->is_bot))
                                            <span class="badge bg-dark ms-1" style="font-size:0.65rem;">BOT</span>
                                        @endif
                                    </div>
                                    <div class="users-person__id text-muted">
                                        #{{ $user->id }}
                                        @if($user->is_premium)
                                            · <span class="text-warning">Premium{{ !empty($user->premium_package_name) ? ': '.$user->premium_package_name : '' }}</span>
                                        @elseif(!empty($user->premium_package_name))
                                            · {{ $user->premium_package_name }}
                                        @endif
                                    </div>
                                    <div class="d-md-none small text-muted mt-1">
                                        {{ $user->email ?: '—' }}@if($user->phone)<br>{{ $user->phone }}@endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div class="small">{{ $user->email ?: '—' }}</div>
                            <div class="small text-muted">{{ $user->phone ?: '—' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-primary">{{ $user->getRoleNames()->first() ?? '—' }}</span>
                        </td>
                        <td><strong>{{ number_format((int) $user->coins) }}</strong></td>
                        <td>
                            @if($isOnline)
                                <span class="badge bg-success">Çevrimiçi</span>
                            @endif
                            @if($statusLabel === 'Askıda')
                                <span class="badge bg-danger">Askıda</span>
                            @elseif($statusLabel === 'Beklemede')
                                <span class="badge bg-warning text-dark">Beklemede</span>
                            @elseif(!$isOnline)
                                <span class="badge bg-light text-muted border">Normal</span>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell small text-muted">
                            @php
                                $lastLogin = $user->last_login_at
                                    ? \Illuminate\Support\Carbon::parse($user->last_login_at)
                                    : null;
                                $lastActive = $isOnline
                                    ? now()
                                    : ($user->last_active_at
                                        ? \Illuminate\Support\Carbon::parse($user->last_active_at)
                                        : null);
                            @endphp
                            <div title="{{ $lastLogin ? $lastLogin->format('d.m.Y H:i:s') : '' }}">
                                <span class="text-muted">Giriş:</span>
                                {{ $lastLogin ? $lastLogin->diffForHumans() : '—' }}
                            </div>
                            <div title="{{ $lastActive && !$isOnline ? $lastActive->format('d.m.Y H:i:s') : ($isOnline ? 'Çevrimiçi' : '') }}">
                                <span class="text-muted">Aktif:</span>
                                @if($isOnline)
                                    <span class="text-success">Şimdi</span>
                                @elseif($lastActive)
                                    {{ $lastActive->diffForHumans() }}
                                @else
                                    —
                                @endif
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="users-actions">
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        data-bs-toggle="modal" data-bs-target="#userShowModal"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $fullName }}"
                                        data-email="{{ $user->email }}"
                                        data-phone="{{ $user->phone }}"
                                        data-coins="{{ $user->coins ?? 0 }}"
                                        data-status="{{ $user->account_status }}"
                                        data-online="{{ $isOnline ? 1 : 0 }}"
                                        data-premium="{{ $user->is_premium ? 1 : 0 }}"
                                        data-package-name="{{ $user->premium_package_name ?? '' }}"
                                        data-premium-expires="{{ $user->premium_expires_at ? \Illuminate\Support\Carbon::parse($user->premium_expires_at)->format('d.m.Y H:i') : '' }}"
                                        data-last-login="{{ $lastLogin ? $lastLogin->format('d.m.Y H:i') : '—' }}"
                                        data-last-active="{{ $isOnline ? 'Şimdi (çevrimiçi)' : ($lastActive ? $lastActive->format('d.m.Y H:i') : '—') }}"
                                        data-duels="{{ (int) ($user->finished_duels_count ?? 0) }}"
                                        data-rewards="{{ (int) ($user->reward_requests_count ?? 0) }}"
                                        data-registered="{{ \Illuminate\Support\Carbon::parse($user->created_at)->format('d.m.Y H:i') }}">Detay</button>

                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        data-bs-toggle="modal" data-bs-target="#userEditModal"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-phone="{{ $user->phone }}"
                                        data-coins="{{ $user->coins ?? 0 }}"
                                        data-status="{{ $user->account_status }}"
                                        data-role="{{ $user->roles->first()->name ?? '' }}"
                                        data-package="{{ $user->package_id }}">Düzenle</button>

                                @can('edit users')
                                @if($user->id !== auth()->id())
                                <button type="button"
                                        class="btn btn-sm {{ $user->account_status === 'suspended' ? 'btn-outline-success' : 'btn-outline-danger' }} btn-toggle-status"
                                        data-id="{{ $user->id }}"
                                        data-status="{{ $user->account_status }}">
                                    {{ $user->account_status === 'suspended' ? 'Aktif Et' : 'Askıya Al' }}
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
            <div class="small text-muted">
                Toplam {{ number_format($users->total()) }} kayıt · Sayfa {{ $users->currentPage() }}/{{ max($users->lastPage(), 1) }}
            </div>
            <div>
                {{ $users->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>
</div>

{{-- Create --}}
<div class="modal fade" id="userCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Kullanıcı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="modal-body row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">İsim</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Şifre</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Rol</label>
            <select name="role" class="form-select" required>
              @foreach($roles as $role)
                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Paket</label>
            <select name="package_id" class="form-select">
              <option value="">Seçiniz</option>
              @foreach($packages as $package)
                <option value="{{ $package->id }}">{{ $package->title ?? $package->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Show / Detay --}}
<div class="modal fade" id="userShowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcı Detayı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0 users-detail">
          <dt class="col-5">İsim</dt><dd class="col-7" id="show-name"></dd>
          <dt class="col-5">Email</dt><dd class="col-7" id="show-email"></dd>
          <dt class="col-5">Telefon</dt><dd class="col-7" id="show-phone"></dd>
          <dt class="col-5">Jeton</dt><dd class="col-7" id="show-coins"></dd>
          <dt class="col-5">Durum</dt><dd class="col-7" id="show-status"></dd>
          <dt class="col-5">Son Giriş</dt><dd class="col-7" id="show-last-login"></dd>
          <dt class="col-5">Son Aktiflik</dt><dd class="col-7" id="show-last-active"></dd>
          <dt class="col-5">Kayıt</dt><dd class="col-7" id="show-registered"></dd>
          <dt class="col-5">Meydan Okuma</dt><dd class="col-7" id="show-duels"></dd>
          <dt class="col-5">Ödül Talebi</dt><dd class="col-7" id="show-rewards"></dd>
          <dt class="col-5">Premium</dt><dd class="col-7" id="show-premium"></dd>
          <dt class="col-5">Paket</dt><dd class="col-7" id="show-package"></dd>
          <dt class="col-5">Premium Bitiş</dt><dd class="col-7" id="show-premium-expires"></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
      </div>
    </div>
  </div>
</div>

{{-- Edit --}}
<div class="modal fade" id="userEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kullanıcıyı Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="userEditForm">
        @csrf
        @method('PUT')
        <div class="modal-body row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">İsim</label>
            <input type="text" name="name" id="edit-name" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="edit-email" class="form-control" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" id="edit-phone" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Durum</label>
            <select name="account_status" id="edit-status" class="form-select">
              <option value="active">Aktif</option>
              <option value="suspended">Askıya Alınmış</option>
              <option value="pending">Beklemede</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Jeton</label>
            <input type="number" min="0" name="coins" id="edit-coins" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Rol</label>
            <select name="role" id="edit-role" class="form-select" required>
              @foreach($roles as $role)
                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Paket</label>
            <select name="package_id" id="edit-package" class="form-select">
              <option value="">Paket Seçin</option>
              @foreach($packages as $package)
                <option value="{{ $package->id }}">{{ $package->title ?? $package->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Yeni Şifre (Opsiyonel)</label>
            <input type="password" name="password" id="edit-password" class="form-control" placeholder="Boş bırakılabilir">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
          <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.users-page-title { margin-top: 1rem; }
.users-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
}
@media (max-width: 991.98px) {
    .users-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 420px) {
    .users-summary { grid-template-columns: 1fr; }
}
.users-summary__card {
    display: flex;
    flex-direction: column;
    gap: .15rem;
    padding: .9rem 1rem;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    color: inherit;
    transition: box-shadow .15s ease, border-color .15s ease;
}
.users-summary__card:hover,
.users-summary__card.is-active {
    border-color: #a5b4fc;
    box-shadow: 0 6px 16px rgba(15,23,42,.06);
}
.users-summary__label { font-size: .78rem; color: #6b7280; }
.users-summary__value { font-size: 1.25rem; font-weight: 700; }

.users-filter__grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: .75rem;
    align-items: end;
}
.users-filter__field { grid-column: span 2; }
.users-filter__field--search { grid-column: span 4; }
.users-filter__actions {
    grid-column: span 2;
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}
.users-filter__btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    flex: 1 1 auto;
    justify-content: center;
    min-height: 38px;
}
.users-filter__btn svg { width: 16px; height: 16px; }
.users-filter__btn.disabled { pointer-events: none; opacity: .55; }

@media (max-width: 1199.98px) {
    .users-filter__field { grid-column: span 4; }
    .users-filter__field--search { grid-column: span 8; }
    .users-filter__actions { grid-column: span 4; }
}
@media (max-width: 767.98px) {
    .users-filter__field,
    .users-filter__field--search,
    .users-filter__actions { grid-column: span 12; }
    .users-filter__actions { flex-direction: column; }
    .users-filter__btn { width: 100%; }
}

.users-person { display: flex; align-items: center; gap: .75rem; min-width: 0; }
.users-person__avatar-wrap { position: relative; flex-shrink: 0; }
.users-person__avatar {
    width: 42px; height: 42px; border-radius: 50%; object-fit: cover;
    border: 1px solid #e5e7eb; background: #fff;
}
.users-person__dot {
    position: absolute; right: 0; bottom: 0; width: 10px; height: 10px;
    border-radius: 50%; background: #cbd5e1; border: 2px solid #fff;
}
.users-person__dot.is-on { background: #22c55e; }
.users-person__name { font-weight: 600; line-height: 1.2; }
.users-person__id { font-size: .75rem; }
.users-actions {
    display: flex; flex-wrap: wrap; gap: .35rem; justify-content: flex-end;
}
.users-detail dt { color: #6b7280; font-weight: 500; }
.users-card { border: 0; box-shadow: 0 1px 3px rgba(15,23,42,.06); border-radius: 14px; }
.users-table thead th { white-space: nowrap; font-size: .82rem; color: #6b7280; }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function () {
    if (typeof feather !== 'undefined') feather.replace();

    toastr.options = {
        closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000
    };

    $('#userCreateModal form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function () {
                $('#userCreateModal').modal('hide');
                toastr.success('Kullanıcı oluşturuldu');
                setTimeout(function () { location.reload(); }, 500);
            },
            error: function (xhr) {
                var msg = 'Bir hata oluştu';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                toastr.error(msg);
            }
        });
    });

    $('#userEditModal form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function () {
                $('#userEditModal').modal('hide');
                toastr.success('Kullanıcı güncellendi');
                setTimeout(function () { location.reload(); }, 500);
            },
            error: function (xhr) {
                var msg = 'Bir hata oluştu';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                toastr.error(msg);
            }
        });
    });

    $('#userShowModal').on('show.bs.modal', function (event) {
        var b = $(event.relatedTarget);
        $('#show-name').text(b.data('name') || '—');
        $('#show-email').text(b.data('email') || '—');
        $('#show-phone').text(b.data('phone') || '—');
        $('#show-coins').text(Number(b.data('coins') || 0).toLocaleString('tr-TR'));
        $('#show-last-login').text(b.data('last-login') || '—');
        $('#show-last-active').text(b.attr('data-last-active') || '—');
        $('#show-registered').text(b.data('registered') || '—');
        $('#show-duels').text(b.data('duels') || 0);
        $('#show-rewards').text(b.data('rewards') || 0);
        $('#show-premium').html(String(b.data('premium')) === '1'
            ? '<span class="badge bg-warning text-dark">Premium</span>'
            : '<span class="text-muted">Hayır</span>');
        var packageName = b.attr('data-package-name') || '';
        $('#show-package').html(packageName
            ? '<span class="badge bg-info text-dark">' + $('<div>').text(packageName).html() + '</span>'
            : '<span class="text-muted">—</span>');
        var premiumExpires = b.attr('data-premium-expires') || '';
        $('#show-premium-expires').text(premiumExpires || '—');

        var online = String(b.data('online')) === '1';
        var status = b.data('status');
        var statusHtml = '';
        if (online) statusHtml += '<span class="badge bg-success me-1">Çevrimiçi</span>';
        if (status === 'suspended') statusHtml += '<span class="badge bg-danger">Askıda</span>';
        else if (status === 'pending') statusHtml += '<span class="badge bg-warning text-dark">Beklemede</span>';
        else if (!online) statusHtml += '<span class="badge bg-light text-muted border">Normal</span>';
        $('#show-status').html(statusHtml);
    });

    $('#userEditModal').on('show.bs.modal', function (event) {
        var b = $(event.relatedTarget);
        $('#edit-name').val(b.data('name'));
        $('#edit-email').val(b.data('email'));
        $('#edit-phone').val(b.data('phone'));
        $('#edit-status').val(b.data('status'));
        $('#edit-coins').val(b.data('coins'));
        $('#edit-role').val(b.data('role'));
        $('#edit-package').val(b.data('package'));
        $('#edit-password').val('');
        $('#userEditForm').attr('action', '/admin/users/' + b.data('id'));
    });

    $(document).on('click', '.btn-toggle-status', function () {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var confirmMsg = status === 'suspended'
            ? 'Bu kullanıcıyı aktif etmek istiyor musunuz?'
            : 'Bu kullanıcıyı askıya almak istiyor musunuz?';
        if (!confirm(confirmMsg)) return;

        $.ajax({
            url: '/admin/users/' + id + '/toggle-status',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (res) {
                toastr.success(res.message || 'Güncellendi');
                setTimeout(function () { location.reload(); }, 400);
            },
            error: function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'İşlem başarısız');
            }
        });
    });
});
</script>
@endpush
