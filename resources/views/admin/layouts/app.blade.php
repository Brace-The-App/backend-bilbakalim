<!DOCTYPE html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
          content="Cuba admin is super flexible, powerful, clean &amp; modern responsive bootstrap 5 admin template with unlimited possibilities.">
    <meta name="keywords"
          content="admin template, Cuba admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="pixelstrap">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="api-token" content="{{ auth()->user()->createToken('admin')->plainTextToken ?? '' }}">
    @include('admin.layouts.favicon')
    <title>Bil Bakalim - Admin Panel</title>
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&amp;display=swap"
          rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900&amp;display=swap"
          rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/font-awesome.css')}}">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/icofont.css')}}">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css')}}">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/flag-icon.css')}}">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/feather-icon.css')}}">
    <!-- Plugins css start-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/scrollbar.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/animate.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/chartist.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/date-picker.css')}}">
    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css')}}">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css')}}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css')}}" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/responsive.css')}}">
    @stack('css')
    @stack('styles')

    {{-- Fixed page-header akışta değil; tüm admin sayfalarında içerik üst boşluğu --}}
    <style>
        .page-wrapper .page-body-wrapper .page-body > .container-fluid {
            padding-top: 1.5rem;
        }
    </style>

    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>
<body >
<!-- loader starts-->
<div class="loader-wrapper">
    <div class="loader-index"><span></span></div>
    <svg>
        <defs></defs>
        <filter id="goo">
            <fegaussianblur in="SourceGraphic" stddeviation="11" result="blur"></fegaussianblur>
            <fecolormatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"></fecolormatrix>
        </filter>
    </svg>
</div>
<!-- loader ends-->

<!-- page-wrapper Start-->
<div class="page-wrapper compact-wrapper" id="pageWrapper">
    <!-- Page Header Start-->
    @include('admin.layouts.header')
    <!-- Page Header Ends-->

    <!-- Page Body Start-->
    <div class="page-body-wrapper sidebar-icon">
        <!-- Page Sidebar Start-->
        @include('admin.layouts.sidebar')
        <!-- Page Sidebar Ends-->

        <div class="page-body ">
            <!-- Container-fluid starts-->
            <div class="container-fluid">
                @yield('content')
            </div>
            <!-- Container-fluid Ends-->
        </div>

        <!-- footer start-->
        @include('admin.layouts.footer')
    </div>
</div>
<!-- page-wrapper Ends-->

<!-- latest jquery-->
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<!-- Bootstrap js-->
<script src="{{ asset('assets/js/bootstrap/bootstrap.bundle.min.js') }}"></script>
<!-- feather icon js-->
<script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
<!-- scrollbar js-->
<script src="{{ asset('assets/js/scrollbar/simplebar.js') }}"></script>
<script src="{{ asset('assets/js/scrollbar/custom.js') }}"></script>
<!-- Sidebar jquery-->
<script src="{{ asset('assets/js/config.js') }}"></script>
<script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<!-- Toastr Custom CSS -->
<style>
    .toast-success, .toast-error, .toast-info, .toast-warning {
        color: white !important;
    }
    .toast-success .toast-message,
    .toast-error .toast-message,
    .toast-info .toast-message,
    .toast-warning .toast-message {
        color: white !important;
    }
    .toast-success .toast-title,
    .toast-error .toast-title,
    .toast-info .toast-title,
    .toast-warning .toast-title {
        color: white !important;
    }
    .toast-close-button {
        color: white !important;
        opacity: 0.8;
    }
    .toast-close-button:hover {
        color: white !important;
        opacity: 1;
    }

    /* Pagination Styles */
    .pagination {
        margin: 0;
    }

    .pagination .page-link {
        color: #6c757d;
        background-color: #fff;
        border: 1px solid #dee2e6;
        padding: 0.5rem 0.75rem;
        margin: 0 2px;
        border-radius: 0.375rem;
    }

    .pagination .page-link:hover {
        color: #495057;
        background-color: #e9ecef;
        border-color: #dee2e6;
    }

    .pagination .page-item.active .page-link {
        color: #fff;
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .pagination .page-item.disabled .page-link {
        color: #6c757d;
        background-color: #fff;
        border-color: #dee2e6;
        cursor: not-allowed;
    }
</style>

<!-- Plugins JS start-->
@stack('scripts')
<!-- Plugins JS Ends-->
<!-- Theme js-->
<script src="{{ asset('assets/js/script.js') }}"></script>

@if (file_exists(public_path('js/socket-client.js')))
<!-- Socket.IO Client (yalnızca dosya varsa) -->
<script src="{{ asset('js/socket-client.js') }}" defer></script>
@endif

<script>
    // CSRF token setup for AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Flash mesajları: tek kanal (toastr) — sayfa içinde tekrar alert basma
    (function () {
        if (typeof toastr === 'undefined') return;
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500,
            extendedTimeOut: 1500,
        };
        @if(session('success'))
            toastr.success(@json(session('success')));
        @endif
        @if(session('error'))
            toastr.error(@json(session('error')));
        @endif
        @if(session('warning'))
            toastr.warning(@json(session('warning')));
        @endif
        @if(session('info'))
            toastr.info(@json(session('info')));
        @endif
    })();

    // Sol menü yüksekliğini ekran boyutuna göre ayarla (tema CSS'ine dokunmadan)
    (function () {
        function fitAdminSidebar() {
            var links = document.querySelector('.page-wrapper.compact-wrapper .sidebar-wrapper .sidebar-links');
            if (!links) return;
            var logo = document.querySelector('.page-wrapper.compact-wrapper .sidebar-wrapper .logo-wrapper');
            var logoH = logo ? logo.offsetHeight : 80;
            var h = Math.max(160, window.innerHeight - logoH - 24);
            links.style.height = h + 'px';
            links.style.marginBottom = '0';
            if (window.SimpleBar && links.SimpleBar) {
                try { links.SimpleBar.recalculate(); } catch (e) {}
            }
            var scrollEl = links.querySelector('.simplebar-content-wrapper');
            if (scrollEl) scrollEl.scrollTop = 0;
        }
        function resetSidebarTop() {
            document.querySelectorAll('.sidebar-wrapper .simplebar-content-wrapper').forEach(function (el) {
                el.scrollTop = 0;
            });
        }
        window.addEventListener('resize', fitAdminSidebar);
        window.addEventListener('load', function () {
            fitAdminSidebar();
            resetSidebarTop();
        });
        document.addEventListener('DOMContentLoaded', fitAdminSidebar);
        setTimeout(function () { fitAdminSidebar(); resetSidebarTop(); }, 300);
    })();
</script>
</body>
</html>
