<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\SmsVitriniService;
use Illuminate\Contracts\View\View;

class SmsVitriniTestController extends Controller
{
    /**
     * SMS Vitrini entegrasyon testi.
     */
    public function __invoke(SmsVitriniService $sms): View
    {
        $phone = (string) config('services.smsvitrini.test_phone', '');
        $message = 'SMS Vitrini test — smsvitrininden gelmiştir.';

        $result = $sms->sendSms($phone, $message);

        return view('admin.sms-vitrini-test', [
            'result' => $result,
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
