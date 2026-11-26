<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Enums\VerificationPurpose;

class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
    )
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) { VerificationPurpose::REGISTER => 'Complete your BOAConnect Registration', VerificationPurpose::LOGIN => 'Your BOAConnect Login Verification Code', VerificationPurpose::RESET_PASSWORD => 'Reset Your BOAConnect Password', VerificationPurpose::CHANGE_EMAIL => 'Confirm Your New Email Address', VerificationPurpose::CHANGE_MOBILE => 'Confirm Your Mobile Number', default => 'BOAConnect Verification Code', };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',

            with: [

                'code' => $this->code,

                'purpose' => $this->purpose,

                'expires' => config('verification.otp.expire_after'),

            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

}
