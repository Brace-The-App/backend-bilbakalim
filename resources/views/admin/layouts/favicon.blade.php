@php
    $faviconVer = @filemtime(public_path('favicon.ico')) ?: time();
@endphp
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVer }}" sizes="any">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVer }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon.png') }}?v={{ $faviconVer }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/admin/apple-touch-icon.png') }}?v={{ $faviconVer }}">
