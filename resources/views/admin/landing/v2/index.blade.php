@extends('admin.layouts.app')

@section('title', 'Landing 2')

@push('styles')
<style>
    .landing-v2-wrap {
        margin: 0 -12px;
    }
    .landing-v2-frame {
        width: 100%;
        height: calc(100vh - 140px);
        min-height: 640px;
        border: 0;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
</style>
@endpush

@section('content')
<div class="landing-v2-wrap">
    <iframe
        class="landing-v2-frame"
        src="{{ route('admin.landing.v2.editor') }}"
        title="Landing 2 Editör"
    ></iframe>
</div>
@endsection
