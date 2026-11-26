<?php

namespace App\Services\Verification\Factories;

use InvalidArgumentException;

use App\Enums\VerificationChannel;

use App\Services\Verification\Contracts\VerificationDriver;
use App\Services\Verification\Drivers\EmailVerificationDriver;
use App\Services\Verification\Drivers\SmsVerificationDriver;

class VerificationDriverFactory
{
    public static function make(
        VerificationChannel $channel
    ): VerificationDriver
    {
        return match ($channel) {

            VerificationChannel::EMAIL =>
                app(EmailVerificationDriver::class),

            VerificationChannel::SMS =>
                app(SmsVerificationDriver::class),

            default =>
                throw new InvalidArgumentException(
                    'Unsupported verification channel.'
                ),

        };
    }
}