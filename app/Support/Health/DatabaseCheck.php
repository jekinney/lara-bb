<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseCheck implements Check
{
    public function passes(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
