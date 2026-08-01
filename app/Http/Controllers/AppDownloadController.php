<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AppDownloadController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('app-download', [
            'iosUrl' => (string) config('mobile-app.ios_store_url'),
            'androidUrl' => (string) config('mobile-app.android_store_url'),
        ]);
    }
}
