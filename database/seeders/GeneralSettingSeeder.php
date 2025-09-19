<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\GeneralSetting;

class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Site Bilgileri
            [
                'key' => 'site_name',
                'value' => 'BilBakalim',
                'type' => 'text',
                'description' => 'Site adı',
                'is_active' => true
            ],
            [
                'key' => 'site_url',
                'value' => 'https://bilbakalim.com',
                'type' => 'text',
                'description' => 'Site URL\'si',
                'is_active' => true
            ],
            [
                'key' => 'site_logo',
                'value' => null,
                'type' => 'text',
                'description' => 'Site logosu (dosya yolu)',
                'is_active' => true
            ],
            [
                'key' => 'site_favicon',
                'value' => null,
                'type' => 'text',
                'description' => 'Site favicon (dosya yolu)',
                'is_active' => true
            ],
            [
                'key' => 'theme_color',
                'value' => '#0d6efd',
                'type' => 'text',
                'description' => 'Tema rengi (hex kodu)',
                'is_active' => true
            ],
            [
                'key' => 'brand_colors',
                'value' => '{"primary":"#0d6efd","secondary":"#6c757d","success":"#198754","danger":"#dc3545","warning":"#ffc107","info":"#0dcaf0"}',
                'type' => 'json',
                'description' => 'Marka renkleri (JSON)',
                'is_active' => true
            ],
            
            // SEO
            [
                'key' => 'meta_title',
                'value' => 'BilBakalim - Online Quiz Platformu',
                'type' => 'text',
                'description' => 'Meta başlık (SEO)',
                'is_active' => true
            ],
            [
                'key' => 'meta_description',
                'value' => 'BilBakalim ile bilginizi test edin, turnuvalara katılın ve ödüller kazanın!',
                'type' => 'text',
                'description' => 'Meta açıklama (SEO)',
                'is_active' => true
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'quiz, bilgi yarışması, turnuva, online quiz, eğitim',
                'type' => 'text',
                'description' => 'Meta anahtar kelimeler (SEO)',
                'is_active' => true
            ],
            
            // İletişim Bilgileri
            [
                'key' => 'contact_phone',
                'value' => '+90 555 123 45 67',
                'type' => 'text',
                'description' => 'Telefon numarası',
                'is_active' => true
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@bilbakalim.com',
                'type' => 'text',
                'description' => 'E-posta adresi',
                'is_active' => true
            ],
            [
                'key' => 'contact_address',
                'value' => 'İstanbul, Türkiye',
                'type' => 'text',
                'description' => 'Adres',
                'is_active' => true
            ],
            
            // Sosyal Medya
            [
                'key' => 'social_facebook',
                'value' => 'https://facebook.com/bilbakalim',
                'type' => 'text',
                'description' => 'Facebook sayfası',
                'is_active' => true
            ],
            [
                'key' => 'social_twitter',
                'value' => 'https://twitter.com/bilbakalim',
                'type' => 'text',
                'description' => 'Twitter hesabı',
                'is_active' => true
            ],
            [
                'key' => 'social_instagram',
                'value' => 'https://instagram.com/bilbakalim',
                'type' => 'text',
                'description' => 'Instagram hesabı',
                'is_active' => true
            ],
            [
                'key' => 'social_youtube',
                'value' => 'https://youtube.com/bilbakalim',
                'type' => 'text',
                'description' => 'YouTube kanalı',
                'is_active' => true
            ],
            [
                'key' => 'social_linkedin',
                'value' => 'https://linkedin.com/company/bilbakalim',
                'type' => 'text',
                'description' => 'LinkedIn sayfası',
                'is_active' => true
            ],
            
            // Dil ve Yerelleştirme
            [
                'key' => 'default_language',
                'value' => 'tr',
                'type' => 'text',
                'description' => 'Varsayılan dil kodu',
                'is_active' => true
            ],
            [
                'key' => 'supported_languages',
                'value' => '["tr","en"]',
                'type' => 'json',
                'description' => 'Desteklenen diller (JSON array)',
                'is_active' => true
            ],
            [
                'key' => 'timezone',
                'value' => 'Europe/Istanbul',
                'type' => 'text',
                'description' => 'Zaman dilimi',
                'is_active' => true
            ],
            
            // SMS Provider
            [
                'key' => 'sms_provider',
                'value' => 'netgsm',
                'type' => 'text',
                'description' => 'SMS sağlayıcısı (netgsm, iletimerkezi, vs.)',
                'is_active' => true
            ],
            [
                'key' => 'sms_username',
                'value' => '',
                'type' => 'text',
                'description' => 'SMS kullanıcı adı',
                'is_active' => true
            ],
            [
                'key' => 'sms_password',
                'value' => '',
                'type' => 'text',
                'description' => 'SMS şifre',
                'is_active' => true
            ],
            [
                'key' => 'sms_sender',
                'value' => 'BILBAKALIM',
                'type' => 'text',
                'description' => 'SMS gönderici adı',
                'is_active' => true
            ],
            
            // Email Provider
            [
                'key' => 'email_provider',
                'value' => 'smtp',
                'type' => 'text',
                'description' => 'E-posta sağlayıcısı (smtp, mailgun, sendgrid, vs.)',
                'is_active' => true
            ],
            [
                'key' => 'email_host',
                'value' => 'smtp.gmail.com',
                'type' => 'text',
                'description' => 'SMTP sunucu adresi',
                'is_active' => true
            ],
            [
                'key' => 'email_port',
                'value' => '587',
                'type' => 'number',
                'description' => 'SMTP port numarası',
                'is_active' => true
            ],
            [
                'key' => 'email_username',
                'value' => '',
                'type' => 'text',
                'description' => 'E-posta kullanıcı adı',
                'is_active' => true
            ],
            [
                'key' => 'email_password',
                'value' => '',
                'type' => 'text',
                'description' => 'E-posta şifre',
                'is_active' => true
            ],
            [
                'key' => 'email_encryption',
                'value' => 'tls',
                'type' => 'text',
                'description' => 'E-posta şifreleme (tls, ssl)',
                'is_active' => true
            ],
            [
                'key' => 'email_from_address',
                'value' => 'noreply@bilbakalim.com',
                'type' => 'text',
                'description' => 'Gönderen e-posta adresi',
                'is_active' => true
            ],
            [
                'key' => 'email_from_name',
                'value' => 'BilBakalim',
                'type' => 'text',
                'description' => 'Gönderen adı',
                'is_active' => true
            ],
            
            // Diğer Ayarlar
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Bakım modu',
                'is_active' => true
            ],
            [
                'key' => 'registration_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Kayıt olma özelliği aktif mi?',
                'is_active' => true
            ],
            [
                'key' => 'max_questions_per_day',
                'value' => '50',
                'type' => 'number',
                'description' => 'Günlük maksimum soru sayısı',
                'is_active' => true
            ],
            [
                'key' => 'default_coin_reward',
                'value' => '10',
                'type' => 'number',
                'description' => 'Varsayılan coin ödülü',
                'is_active' => true
            ]
        ];

        foreach ($settings as $setting) {
            GeneralSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
