<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class CacheCheck implements Check
{
    public function passes(): bool
    {
        try {
            $key = 'health:'.Str::random(12);

            Cache::put($key, 'ok', 10);
            $readBack = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $readBack;
        } catch (Throwable) {
            return false;
        }
    }
}
