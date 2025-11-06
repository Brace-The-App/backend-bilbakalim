<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetGsmService
{
    private string $username;
    private string $password;
    private string $msgheader;
    private string $apiUrl;

    public function __construct()
    {
        $this->username = config('services.netgsm.username', env('NETGSM_USERNAME', '8503055373'));
        $this->password = config('services.netgsm.password', env('NETGSM_PASSWORD', 'F1AF196'));
        $this->msgheader = config('services.netgsm.msgheader', env('NETGSM_MSGHEADER', 'ATOM GIDA'));
        $this->apiUrl = config('services.netgsm.api_url', env('NETGSM_API_URL', 'https://api.netgsm.com.tr/sms/rest/v2/send'));
    }

    /**
     * Send SMS via NetGSM
     *
     * @param string $phone Phone number (should be in format: 5333154031)
     * @param string $message SMS message
     * @return array
     */
    public function sendSms(string $phone, string $message): array
    {
        try {
            // Clean phone number (remove +, spaces, dashes)
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Remove leading 0 if exists
            if (substr($phone, 0, 1) === '0') {
                $phone = substr($phone, 1);
            }

            // If phone starts with 90, remove it (NetGSM expects without country code)
            if (substr($phone, 0, 2) === '90') {
                $phone = substr($phone, 2);
            }

            $payload = [
                'msgheader' => $this->msgheader,
                'messages' => [
                    [
                        'msg' => $message,
                        'no' => $phone
                    ]
                ]
            ];

            Log::info('NetGSM SMS Request', [
                'url' => $this->apiUrl,
                'payload' => $payload
            ]);

            // NetGSM REST API v2 uses Basic Auth
            $response = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, $payload);

            $responseData = $response->json();

            Log::info('NetGSM SMS Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS başarıyla gönderildi.',
                    'response' => $responseData
                ];
            } else {
                Log::error('NetGSM SMS Error', [
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS gönderilirken bir hata oluştu.',
                    'error' => $responseData ?? 'Unknown error',
                    'status_code' => $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('NetGSM SMS Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'SMS gönderilirken bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }
}

