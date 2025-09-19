<div class="sidebar-wrapper">
    <div>
        <div class="logo-wrapper" style="    padding: 0px 40px;">
            <a href="{{ route('admin.dashboard') }}">
                <img class="img-fluid for-light" src="{{ asset('assets/images/logo/logo.png') }}" alt="BilBakalim">
                <img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo_dark.png') }}" alt="BilBakalim">
            </a>
            <div class="back-btn"><i class="fa fa-angle-left"></i></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>
        <div class="logo-icon-wrapper"><a href="{{ route('admin.dashboard') }}"><img class="img-fluid" src="{{ asset('assets/images/logo/logo-icon.png') }}" alt="BilBakalim"></a></div>
        <nav class="sidebar-main">
            <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
            <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                    <li class="back-btn">
                        <a href="{{ route('admin.dashboard') }}"><img class="img-fluid" src="{{ asset('assets/images/logo/logo-icon.png') }}" alt="BilBakalim"></a>
                        <div class="mobile-back text-end"><span>Geri</span><i class="fa fa-angle-right ps-2" aria-hidden="true"></i></div>
                    </li>
                    
                    <li class="sidebar-main-title">
                        <div>
                            <h6 class="lan-1">Dashboard</h6>
                        </div>
                    </li>
                    
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                           href="{{ route('admin.dashboard') }}">
                            <i data-feather="home"></i>
                            <span class="lan-3">Dashboard</span>
                        </a>
                    </li>

                    @can('view users')
                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('personel'))
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" 
                           href="{{ route('admin.users.index') }}">
                            <i data-feather="users"></i>
                            <span>Kullanıcılar</span>
                        </a>
                    </li>
                    @endif
                    @endcan

                    @can('view categories')
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" 
                           href="{{ route('admin.categories.index') }}">
                            <i data-feather="folder"></i>
                            <span>Kategoriler</span>
                        </a>
                    </li>
                    @endcan

                    @can('view questions')
                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('personel'))
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" 
                           href="{{ route('admin.questions.index') }}">
                           <i data-feather="book"></i>
                            <span>Sorular</span>
                        </a>
                    </li>
                    @endif
                    @endcan

                    @can('view tournaments')
                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('personel'))
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.tournaments.*') ? 'active' : '' }}" 
                           href="{{ route('admin.tournaments.index') }}">
                            <i data-feather="award"></i>
                            <span>Turnuvalar</span>
                        </a>
                    </li>
                    @endif
                    @endcan
                    
              
                   
            

                    @can('view notifications')
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" 
                           href="{{ route('admin.notifications.index') }}">
                            <i data-feather="bell"></i>
                            <span>Bildirimler</span>
                        </a>
                    </li>
                    @endcan
  
         
                    @can('view general settings')
                    @if(auth()->user()->hasRole('admin'))
                    <li class="sidebar-main-title">
                        <div>
                            <h6>Sistem</h6>
                        </div>
                    </li>
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.general-settings.*') ? 'active' : '' }}" 
                           href="{{ route('admin.general-settings.index') }}">
                            <i data-feather="settings"></i>
                            <span>Genel Ayarlar</span>
                        </a>
                    </li>
                    @endif
                    @endcan
                    
                    @can('view permissions')
                    @if(auth()->user()->hasRole('admin'))
                    <li class="sidebar-list">
                        <i class="fa fa-thumb-tack"></i>
                        <a class="sidebar-link sidebar-title {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" 
                           href="{{ route('admin.permissions.index') }}">
                            <i data-feather="shield"></i>
                            <span>Yetki Yönetimi</span>
                        </a>
                    </li>
                    @endif
                    @endcan
                </ul>
            </div>
            <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
        </nav>
    </div>
</div>
