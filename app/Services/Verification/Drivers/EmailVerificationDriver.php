<?php

namespace App\Services\Verification\Drivers;

use Illuminate\Support\Facades\Mail;

use App\Enums\VerificationPurpose;
use App\Mail\VerificationCodeMail;
use App\Services\Verification\Contracts\VerificationDriver;

class EmailVerificationDriver implements VerificationDriver
{
    public function send(
        string $recipient,
        string $code,
        string $purpose,
    ): void
    {
        Mail::to($recipient)

            ->queue(

                new VerificationCodeMail(
                    $code,

                    VerificationPurpose::from($purpose),
                )

            );
    }
}