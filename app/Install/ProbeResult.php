<?php

namespace App\Install;

final readonly class ProbeResult
{
    public function __construct(public bool $ok, public string $message) {}

    public static function ok(string $message): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
