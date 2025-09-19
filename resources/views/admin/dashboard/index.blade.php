@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-title">
    <div class="row">
        <div class="col-6">
            <h3>Dashboard</h3>
        </div>
        <div class="col-6">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </div>
    </div>
</div>

<!-- Container-fluid starts-->
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-sm-6 col-xl-3 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-primary b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="users" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Toplam Kullanıcı</span>
                        <h4 class="mb-0 counter">{{ $stats['total_users'] }}</h4>
                        <i class="icon-bg" data-feather="users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-success b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="user-check" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Aktif Kullanıcı</span>
                        <h4 class="mb-0 counter">{{ $stats['active_users'] }}</h4>
                        <i class="icon-bg" data-feather="user-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-warning b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="help-circle" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Toplam Soru</span>
                        <h4 class="mb-0 counter">{{ $stats['total_questions'] }}</h4>
                        <i class="icon-bg" data-feather="help-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-info b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="folder" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Kategoriler</span>
                        <h4 class="mb-0 counter">{{ $stats['total_categories'] }}</h4>
                        <i class="icon-bg" data-feather="folder"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Additional Statistics -->
    <div class="col-sm-6 col-xl-4 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-secondary b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="award" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Turnuvalar</span>
                        <h4 class="mb-0 counter">{{ $stats['total_tournaments'] }}</h4>
                        <i class="icon-bg" data-feather="award"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-success b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="check-circle" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Doğru Cevap</span>
                        <h4 class="mb-0 counter">{{ $stats['correct_answers'] }}</h4>
                        <i class="icon-bg" data-feather="check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4 col-lg-6">
        <div class="card o-hidden">
            <div class="bg-primary b-r-4 card-body">
                <div class="media static-top-widget">
                    <div class="align-self-center text-center">
                        <i data-feather="edit" class="font-light"></i>
                    </div>
                    <div class="media-body">
                        <span class="m-0">Toplam Cevap</span>
                        <h4 class="mb-0 counter">{{ $stats['total_answers'] }}</h4>
                        <i class="icon-bg" data-feather="edit"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Users -->
    <div class="col-xl-6 col-md-12">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Son Kayıt Olan Kullanıcılar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive theme-scrollbar">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <img class="img-40 rounded-circle" src="{{ asset('assets/images/dashboard/profile.jpg') }}" alt="user">
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <a href="{{ route('admin.users.show', $user) }}">
                                                <h6 class="mb-0">{{ $user->name }}</h6>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge bg-primary">
                                        {{ $user->getRoleNames()->first() ?? 'Rol Yok' }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Questions -->
    <div class="col-xl-6 col-md-12">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Son Eklenen Sorular</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive theme-scrollbar">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Soru</th>
                                <th>Kategori</th>
                                <th>Seviye</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_questions as $question)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.questions.show', $question) }}">
                                        {{ Str::limit($question->question, 40) }}
                                    </a>
                                </td>
                                <td>
                                    @if($question->category)
                                        <span class="badge bg-info">{{ $question->category->name }}</span>
                                    @else
                                        <span class="badge bg-secondary">Kategori Yok</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($question->question_level)
                                        @case('easy')
                                            <span class="badge bg-success">Kolay</span>
                                            @break
                                        @case('medium')
                                            <span class="badge bg-warning">Orta</span>
                                            @break
                                        @case('hard')
                                            <span class="badge bg-danger">Zor</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ $question->question_level }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $question->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @can('create users')
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-block w-100">
                            <i data-feather="user-plus" class="me-2"></i>Yeni Kullanıcı
                        </a>
                    </div>
                    @endcan

                    @can('create categories')
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('admin.categories.create') }}" class="btn btn-success btn-block w-100">
                            <i data-feather="folder-plus" class="me-2"></i>Yeni Kategori
                        </a>
                    </div>
                    @endcan

                    @can('create questions')
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('admin.questions.create') }}" class="btn btn-warning btn-block w-100">
                            <i data-feather="plus-circle" class="me-2"></i>Yeni Soru
                        </a>
                    </div>
                    @endcan

                    @can('create notifications')
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('admin.notifications.create') }}" class="btn btn-info btn-block w-100">
                            <i data-feather="bell" class="me-2"></i>Bildirim Gönder
                        </a>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Simple counter animation
$(document).ready(function() {
    $('.counter').each(function() {
        var $this = $(this);
        var countTo = parseInt($this.text()) || 0;
        
        $({ countNum: 0 }).animate({
            countNum: countTo
        }, {
            duration: 2000,
            easing: 'linear',
            step: function() {
                $this.text(Math.floor(this.countNum));
            },
            complete: function() {
                $this.text(countTo);
            }
        });
    });
});
</script>
@endpush
