<?php

namespace App\Enums;

enum VerificationChannel: string
{

    case EMAIL='email';

    case SMS='sms';

}