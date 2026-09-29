<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A username that is well formed, not reserved, and not taken in any capitalisation. */
final class AvailableUsername implements ValidationRule
{
    private const RESERVED = ['admin', 'administrator', 'moderator', 'mod', 'guest', 'system', 'root', 'staff', 'larabb'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clean = mb_strtolower((string) $value);

        if (! preg_match('/^[A-Za-z0-9_.-]{3,30}$/', (string) $value)) {
            $fail('The username must be 3 to 30 letters, numbers, dots, dashes or underscores.');
        } elseif (in_array($clean, self::RESERVED, true)) {
            $fail('That username is reserved.');
        } elseif (User::query()->where('username_clean', $clean)->exists()) {
            $fail('That username is already taken.');
        }
    }
}
