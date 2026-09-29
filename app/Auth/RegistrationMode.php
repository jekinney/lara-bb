<?php

namespace App\Auth;

use App\Models\Setting;

enum RegistrationMode: string
{
    /** Anyone can register and log in straight away. */
    case Open = 'open';

    /** Anyone can register, but must confirm their email address before logging in. */
    case Email = 'email';

    /** Nobody can register. */
    case Closed = 'closed';

    public static function current(): self
    {
        return self::tryFrom((string) Setting::read('registration.mode', self::Open->value)) ?? self::Open;
    }
}
