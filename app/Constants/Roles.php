<?php

namespace App\Constants;

use ReflectionClass;

class Roles
{
    public const SUPER_ADMIN = 'Super Administrator';

    public const ADMINISTRATOR = 'Administrator';

    public const CHAIRPERSON = 'Chairperson';

    public const SECRETARY = 'Secretary';

    public const TREASURER = 'Treasurer';

    public const STAFF = 'Staff';

    public const BHW_BNS = 'BHW_BNS';

    public const RESIDENT = 'Resident';

    public static function all(): array
    {
        return array_values(
            (new ReflectionClass(self::class))
                ->getConstants()
        );
    }
}