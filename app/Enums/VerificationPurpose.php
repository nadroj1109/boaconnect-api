<?php

namespace App\Enums;

enum VerificationPurpose: string
{

    case REGISTER = 'register';

    case LOGIN = 'login';

    case RESET_PASSWORD = 'reset_password';

    case CHANGE_EMAIL = 'change_email';

    case CHANGE_MOBILE = 'change_mobile';

}