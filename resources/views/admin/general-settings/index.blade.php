@extends('admin.layouts.app')

@section('title', 'Genel Ayarlar')

@push('styles')
<style>
.page-title {
    margin-top: 2rem !important;
    padding-top: 1rem !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Genel Ayarlar</h4>
                   
                </div>
                <div class="card-body">
                    <!-- Kategoriler -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <ul class="nav nav-pills nav-fill" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="site-tab" data-bs-toggle="pill" data-bs-target="#site" type="button" role="tab">
                                        <i data-feather="globe" class="me-1"></i> Site Bilgileri
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="seo-tab" data-bs-toggle="pill" data-bs-target="#seo" type="button" role="tab">
                                        <i data-feather="search" class="me-1"></i> SEO
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="contact-tab" data-bs-toggle="pill" data-bs-target="#contact" type="button" role="tab">
                                        <i data-feather="phone" class="me-1"></i> İletişim
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="social-tab" data-bs-toggle="pill" data-bs-target="#social" type="button" role="tab">
                                        <i data-feather="share-2" class="me-1"></i> Sosyal Medya
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="language-tab" data-bs-toggle="pill" data-bs-target="#language" type="button" role="tab">
                                        <i data-feather="globe" class="me-1"></i> Dil & Yerelleştirme
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="sms-tab" data-bs-toggle="pill" data-bs-target="#sms" type="button" role="tab">
                                        <i data-feather="message-circle" class="me-1"></i> SMS
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                                        <i data-feather="mail" class="me-1"></i> E-posta
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="other-tab" data-bs-toggle="pill" data-bs-target="#other" type="button" role="tab">
                                        <i data-feather="settings" class="me-1"></i> Diğer
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab İçerikleri -->
                    <div class="tab-content" id="settingsTabContent">
                        @php
                            $siteSettings = $settings->whereIn('key', ['site_name', 'site_url', 'theme_color', 'brand_colors']);
                            $logoFaviconSettings = $settings->whereIn('key', ['site_logo', 'site_favicon']);
                            $seoSettings = $settings->whereIn('key', ['meta_title', 'meta_description', 'meta_keywords']);
                            $contactSettings = $settings->whereIn('key', ['contact_phone', 'contact_email', 'contact_address']);
                            $socialSettings = $settings->whereIn('key', ['social_facebook', 'social_twitter', 'social_instagram', 'social_youtube', 'social_linkedin']);
                            $languageSettings = $settings->whereIn('key', ['default_language', 'supported_languages', 'timezone']);
                            $smsSettings = $settings->whereIn('key', ['sms_provider', 'sms_username', 'sms_password', 'sms_sender']);
                            $emailSettings = $settings->whereIn('key', ['email_provider', 'email_host', 'email_port', 'email_username', 'email_password', 'email_encryption', 'email_from_address', 'email_from_name']);
                            $otherSettings = $settings->whereNotIn('key', array_merge($siteSettings->pluck('key')->toArray(), $logoFaviconSettings->pluck('key')->toArray(), $seoSettings->pluck('key')->toArray(), $contactSettings->pluck('key')->toArray(), $socialSettings->pluck('key')->toArray(), $languageSettings->pluck('key')->toArray(), $smsSettings->pluck('key')->toArray(), $emailSettings->pluck('key')->toArray()));
                        @endphp

                    

                        <!-- Site Bilgileri -->
                        <div class="tab-pane fade show active" id="site" role="tabpanel">
                             <!-- Logo Yönetimi -->
                             <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Logo Yönetimi</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Site Logosu</label>
                                                    <input type="file" class="form-control" id="site_logo_upload" accept="image/*">
                                                    <div class="form-text">PNG, JPG, GIF formatları desteklenir. Maksimum 2MB.</div>
                                                    @if($logoFaviconSettings->where('key', 'site_logo')->first() && $logoFaviconSettings->where('key', 'site_logo')->first()->value)
                                                        <div class="mt-2">
                                                            <img src="{{ asset('storage/' . $logoFaviconSettings->where('key', 'site_logo')->first()->value) }}" 
                                                                 alt="Site Logo" 
                                                                 style="max-width: 100px; max-height: 50px;" 
                                                                 class="img-fluid rounded">
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Favicon</label>
                                                    <input type="file" class="form-control" id="site_favicon_upload" accept="image/*">
                                                    <div class="form-text">ICO, PNG formatları desteklenir. 32x32 veya 16x16 piksel önerilir.</div>
                                                    @if($logoFaviconSettings->where('key', 'site_favicon')->first() && $logoFaviconSettings->where('key', 'site_favicon')->first()->value)
                                                        <div class="mt-2">
                                                            <img src="{{ asset('storage/' . $logoFaviconSettings->where('key', 'site_favicon')->first()->value) }}" 
                                                                 alt="Favicon" 
                                                                 style="max-width: 32px; max-height: 32px;" 
                                                                 class="img-fluid rounded">
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Marka Renkleri ve Diğer Ayarlar -->
                            <div class="row mb-4">
                                @foreach($siteSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if($setting->key === 'brand_colors' && $setting->value)
                                                    @php
                                                        $colors = json_decode($setting->value, true);
                                                    @endphp
                                                    @if($colors)
                                                        <div class="d-flex gap-2 flex-wrap">
                                                            @foreach($colors as $name => $color)
                                                                <div class="d-flex align-items-center">
                                                                    <div style="width: 20px; height: 20px; background-color: {{ $color }}; border-radius: 3px; margin-right: 5px;"></div>
                                                                    <small>{{ $name }}</small>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <code>{{ Str::limit($setting->value, 50) }}</code>
                                                    @endif
                                                @elseif($setting->type === 'boolean')
                                                    @if($setting->value)
                                                        <span class="badge bg-success">Evet</span>
                                                    @else
                                                        <span class="badge bg-danger">Hayır</span>
                                                    @endif
                                                @elseif($setting->type === 'json')
                                                    <code>{{ Str::limit($setting->value, 50) }}</code>
                                                @else
                                                    {{ Str::limit($setting->value, 50) ?: '-' }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                           
                        </div>

                        <!-- SEO -->
                        <div class="tab-pane fade" id="seo" role="tabpanel">
                            <div class="row">
                                @foreach($seoSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">{{ Str::limit($setting->value, 100) ?: '-' }}</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- İletişim -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <div class="row">
                                @foreach($contactSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">{{ $setting->value ?: '-' }}</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Sosyal Medya -->
                        <div class="tab-pane fade" id="social" role="tabpanel">
                            <div class="row">
                                @foreach($socialSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if($setting->value)
                                                    <a href="{{ $setting->value }}" target="_blank" class="text-decoration-none">
                                                        {{ Str::limit($setting->value, 50) }}
                                                        <i data-feather="external-link" class="ms-1" style="width: 14px; height: 14px;"></i>
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Dil & Yerelleştirme -->
                        <div class="tab-pane fade" id="language" role="tabpanel">
                            <div class="row">
                                @foreach($languageSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if($setting->type === 'json')
                                                    <code>{{ $setting->value }}</code>
                                                @else
                                                    {{ $setting->value ?: '-' }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- SMS -->
                        <div class="tab-pane fade" id="sms" role="tabpanel">
                            <div class="row">
                                @foreach($smsSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if(in_array($setting->key, ['sms_password']) && $setting->value)
                                                    ••••••••
                                                @else
                                                    {{ $setting->value ?: '-' }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- E-posta -->
                        <div class="tab-pane fade" id="email" role="tabpanel">
                            <div class="row">
                                @foreach($emailSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if(in_array($setting->key, ['email_password']) && $setting->value)
                                                    ••••••••
                                                @else
                                                    {{ $setting->value ?: '-' }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Diğer -->
                        <div class="tab-pane fade" id="other" role="tabpanel">
                            <div class="row">
                                @foreach($otherSettings as $setting)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $setting->description }}</h6>
                                            <p class="card-text">
                                                @if($setting->type === 'boolean')
                                                    @if($setting->value)
                                                        <span class="badge bg-success">Evet</span>
                                                    @else
                                                        <span class="badge bg-danger">Hayır</span>
                                                    @endif
                                                @else
                                                    {{ $setting->value ?: '-' }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><code>{{ $setting->key }}</code></small>
                                                <div class="btn-group btn-group-sm">
                                                    @can('edit general settings')
                                                    <button class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#settingEditModal"
                                                            data-id="{{ $setting->id }}"
                                                            data-key="{{ $setting->key }}"
                                                            data-value="{{ $setting->value }}"
                                                            data-type="{{ $setting->type }}"
                                                            data-description="{{ $setting->description }}"
                                                            data-active="{{ $setting->is_active }}">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Setting Modal -->
<div class="modal fade" id="settingCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Yeni Ayar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="settingCreateForm">
        @csrf
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="key" class="form-label">Anahtar <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="key" name="key" required>
                <div class="form-text">Örnek: site_title, max_questions_per_day</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="type" class="form-label">Tip <span class="text-danger">*</span></label>
                <select class="form-select" id="type" name="type" required>
                  <option value="">Seçiniz</option>
                  <option value="text">Metin</option>
                  <option value="number">Sayı</option>
                  <option value="boolean">Boolean (Evet/Hayır)</option>
                  <option value="json">JSON</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="value" class="form-label">Değer</label>
                <textarea class="form-control" id="value" name="value" rows="3"></textarea>
                <div class="form-text">Boolean için: true/false, JSON için: {"key": "value"}</div>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                  <label class="form-check-label" for="is_active">
                    Aktif
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

<!-- Edit Setting Modal -->
<div class="modal fade" id="settingEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ayar Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="settingEditForm">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-key" class="form-label">Anahtar <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit-key" name="key" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit-type" class="form-label">Tip <span class="text-danger">*</span></label>
                <select class="form-select" id="edit-type" name="type" required>
                  <option value="">Seçiniz</option>
                  <option value="text">Metin</option>
                  <option value="number">Sayı</option>
                  <option value="boolean">Boolean (Evet/Hayır)</option>
                  <option value="json">JSON</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="edit-value" class="form-label">Değer</label>
                <textarea class="form-control" id="edit-value" name="value" rows="3"></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <label for="edit-description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="edit-description" name="description" rows="2"></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="edit-is_active" name="is_active">
                  <label class="form-check-label" for="edit-is_active">
                    Aktif
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Create Setting
    $('#settingCreateForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var url = '{{ route("admin.general-settings.store") }}';
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#settingCreateModal').modal('hide');
                toastr.success('Ayar başarıyla oluşturuldu!');
                loadSettings();
                $('#settingCreateForm')[0].reset();
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
                    toastr.error('Ayar oluşturulurken bir hata oluştu!');
                }
            }
        });
    });

    // Edit Setting Modal
    $('#settingEditModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var key = button.data('key');
        var value = button.data('value');
        var type = button.data('type');
        var description = button.data('description');
        var active = button.data('active');
        
        // Handle object values
        if (typeof value === 'object' && value !== null) {
            value = JSON.stringify(value);
        }
        
        $('#edit-key').val(key);
        $('#edit-value').val(value || '');
        $('#edit-type').val(type);
        $('#edit-description').val(description || '');
        $('#edit-is_active').prop('checked', active == 1 || active === true);
        $('#settingEditForm').attr('action', '/admin/general-settings/' + id);
    });

    // Update Setting
    $('#settingEditForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        // Checkbox değerini doğru şekilde ekle
        formData.set('is_active', $('#edit-is_active').is(':checked') ? '1' : '0');
        var url = $(this).attr('action');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#settingEditModal').modal('hide');
                toastr.success('Ayar başarıyla güncellendi!');
                loadSettings();
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
                    toastr.error('Ayar güncellenirken bir hata oluştu!');
                }
            }
        });
    });

    // Logo Upload
    $('#site_logo_upload').on('change', function() {
        var file = this.files[0];
        console.log('Logo file selected:', file);
        
        if (file) {
            var formData = new FormData();
            formData.append('logo', file);
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            
            console.log('Sending logo upload request...');
            
            $.ajax({
                url: '/admin/general-settings/upload-logo',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Logo upload response:', response);
                    if (response.success) {
                        toastr.success('Logo başarıyla yüklendi!');
                        loadSettings();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    console.log('Logo upload error:', xhr);
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        if (errors.logo) {
                            toastr.error(errors.logo[0]);
                        }
                    } else {
                        toastr.error('Logo yüklenirken bir hata oluştu!');
                    }
                }
            });
        }
    });

    // Favicon Upload
    $('#site_favicon_upload').on('change', function() {
        var file = this.files[0];
        console.log('Favicon file selected:', file);
        
        if (file) {
            var formData = new FormData();
            formData.append('favicon', file);
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            
            console.log('Sending favicon upload request...');
            
            $.ajax({
                url: '/admin/general-settings/upload-favicon',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Favicon upload response:', response);
                    if (response.success) {
                        toastr.success('Favicon başarıyla yüklendi!');
                        loadSettings();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    console.log('Favicon upload error:', xhr);
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        if (errors.favicon) {
                            toastr.error(errors.favicon[0]);
                        }
                    } else {
                        toastr.error('Favicon yüklenirken bir hata oluştu!');
                    }
                }
            });
        }
    });

    // Load Settings Function
    function loadSettings() {
        location.reload();
    }

    // Pagination Click Handler
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = $(this).attr('href').split('page=')[1];
        loadSettings(page);
    });

    // Delete Setting
    window.deleteSetting = function(id) {
        if (confirm('Bu ayarı silmek istediğinizden emin misiniz?')) {
            $.ajax({
                url: '/admin/general-settings/' + id,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        loadSettings();
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
                        toastr.error('Ayar silinirken bir hata oluştu!');
                    }
                }
            });
        }
    };
});
</script>
@endpush

@include('admin.layouts.footer')
