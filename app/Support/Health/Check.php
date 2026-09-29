<?php

namespace App\Support\Health;

interface Check
{
    /** Whether the dependency is reachable and usable right now. */
    public function passes(): bool;
}
