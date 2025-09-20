<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:send-test {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test email to specified address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        try {
            Mail::raw('Bu bir test mailidir. Mail sistemi çalışıyor!', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Mail - Bilbakalim');
            });
            
            $this->info("Test email sent successfully to {$email}");
            Log::info("Test email sent to: {$email}");
            
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            Log::error("Email sending failed: " . $e->getMessage());
        }
    }
}
