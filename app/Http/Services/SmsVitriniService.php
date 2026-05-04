<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class SmsVitriniService
{
    private static bool $sdkLoaded = false;

    public function sendSms(string $phone, string $message): array
    {
        $sdkPath = (string) config('services.smsvitrini.sdk_path', base_path('lib/MesajPaneli/MesajPaneliApi.php'));

        if (! is_readable($sdkPath)) {
            Log::error('Mesaj Paneli: SDK bulunamadı.', ['path' => $sdkPath]);

            return [
                'success' => false,
                'message' => 'MesajPaneliApi.php okunamıyor: '.$sdkPath,
            ];
        }

        $apiHash = trim((string) (config('services.smsvitrini.api_hash') ?: env('SMSVITRINI_API_HASH') ?: env('SMSVITRINI_API_KEY', '')));
        $baslik = trim((string) (config('services.smsvitrini.baslik') ?: env('SMSVITRINI_BASLIK', '')));

        if ($apiHash === '') {
            Log::error('Mesaj Paneli: API anahtarı boş.');

            return [
                'success' => false,
                'message' => 'API anahtarı yok: .env içine SMSVITRINI_API_KEY (veya SMSVITRINI_API_HASH) ekleyin. Config cache kullanıyorsanız: php artisan config:clear',
            ];
        }

        if ($baslik === '') {
            Log::error('Mesaj Paneli: SMSVITRINI_BASLIK boş (Mesaj Paneli kayıtlı başlık).');

            return [
                'success' => false,
                'message' => 'SMS başlığı yok: .env içine SMSVITRINI_BASLIK= yazın (Mesaj Paneli’ndeki onaylı başlıkla birebir aynı).',
            ];
        }

        try {
            $this->loadSdk($sdkPath);

            $phone = $this->normalizeTurkishGsm($phone);

            $credentials = new \CredentialsHash($apiHash);
            $verifySsl = (bool) config('services.smsvitrini.verify_ssl', true);
            $smsApi = $verifySsl
                ? new \MesajPaneliApi($credentials)
                : new \MesajPaneliApi($credentials, false);

            $useTrChars = (bool) config('services.smsvitrini.use_turkish_chars', true);

            $data = [
                ['tel' => $phone, 'msg' => $message],
            ];

            $rawResponse = $smsApi->parametrikMesajGonder($baslik, $data, $useTrChars, false);

            Log::info('Mesaj Paneli parametrik gönderim yanıtı', ['response' => $rawResponse]);

            return [
                'success' => true,
                'message' => 'SMS başarıyla gönderildi.',
                'response' => $rawResponse,
            ];
        } catch (Throwable $e) {
            Log::error('Mesaj Paneli hata', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return [
                'success' => false,
                'message' => 'SMS gönderilemedi: '.$e->getMessage(),
            ];
        }
    }

    private function loadSdk(string $sdkPath): void
    {
        if (self::$sdkLoaded) {
            return;
        }

        require_once $sdkPath;
        self::$sdkLoaded = true;
    }

    private function normalizeTurkishGsm(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '90')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }
}
