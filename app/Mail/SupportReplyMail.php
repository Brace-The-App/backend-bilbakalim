<?php

namespace App\Mail;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{id:string,label:string,from_address:string,from_name:string}  $account
     */
    public function __construct(
        public SupportMessage $ticket,
        public string $replyBody,
        public array $account,
        public ?string $adminName = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->ticket->subject
            ? 'Re: '.$this->ticket->subject
            : 'Re: Destek talebi #'.$this->ticket->id;

        return new Envelope(
            from: new Address($this->account['from_address'], $this->account['from_name']),
            subject: $subject,
            replyTo: [
                new Address($this->account['from_address'], $this->account['from_name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.support-reply',
            with: [
                'ticket' => $this->ticket,
                'replyBody' => $this->replyBody,
                'account' => $this->account,
                'adminName' => $this->adminName,
                'logoUrl' => rtrim((string) config('app.url'), '/').'/assets/images/logo/logo.png',
                'appName' => 'Bil Bakalım',
            ],
        );
    }
}
