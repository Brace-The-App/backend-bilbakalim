<div class="page-header">
    <div class="header-wrapper row m-0 align-items-center">
        <div class="header-logo-wrapper col-auto pe-3">
            <div class="logo-wrapper">
                <a href="{{ route('admin.dashboard') }}">
                    <img class="img-fluid for-light" src="{{ asset('assets/images/logo/logo.png') }}" alt="BilBakalim">
                    <img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo_dark.png') }}" alt="BilBakalim">
                </a>
            </div>
            <div class="toggle-sidebar ms-2"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

    

        <div class="nav-right col-auto ms-auto">
            <ul class="nav-menus d-flex align-items-center gap-3">
                <li class="d-md-none"><span class="header-search"><i data-feather="search"></i></span></li>
               
                <li class="profile-nav onhover-dropdown pe-0 py-0">
                    <div class="media profile-media d-flex align-items-center">
                        <img class="b-r-10" src="{{asset('assets/images/46.png')}}" alt="" style="width: 35px; height: 35px; object-fit: cover;">
                        <div class="media-body ms-2">
                            <span class="d-block fw-bold">{{ auth()->user()->name }}</span>
                            <p class="mb-0 font-roboto small text-muted">{{ auth()->user()->getRoleNames()->first() }} <i class="middle fa fa-angle-down"></i></p>
                        </div>
                    </div>
                    <ul class="profile-dropdown onhover-show-div">
                        <li><a href="#"><i data-feather="user"></i><span>Hesap</span></a></li>
                        <li><a href="#"><i data-feather="settings"></i><span>Ayarlar</span></a></li>
                        <li>
                            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i data-feather="log-out"></i><span>Çıkış</span></a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>
<div class="tap-top"><i data-feather="chevrons-up"></i></div>
