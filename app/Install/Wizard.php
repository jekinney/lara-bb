<?php

namespace App\Install;

use Illuminate\Contracts\Session\Session;

/** Tracks which installer steps are done, and what was entered, in the visitor's server-side session. */
final class Wizard
{
    public const STEPS = ['token', 'requirements', 'database', 'services', 'site', 'review'];

    public function __construct(private readonly Session $session) {}

    public function isDone(string $step): bool
    {
        return $this->session->has("install.{$step}");
    }

    /** @param  array<string, mixed>  $data */
    public function complete(string $step, array $data = []): void
    {
        $this->session->put("install.{$step}", $data);
    }

    /** @return array<string, mixed> */
    public function data(string $step): array
    {
        return $this->session->get("install.{$step}", []);
    }

    /** The first step that is not done yet. */
    public function current(): string
    {
        foreach (self::STEPS as $step) {
            if (! $this->isDone($step)) {
                return $step;
            }
        }

        return 'review';
    }

    /** A step can be opened once every step before it is done. */
    public function allows(string $step): bool
    {
        foreach (self::STEPS as $earlier) {
            if ($earlier === $step) {
                return true;
            }
            if (! $this->isDone($earlier)) {
                return false;
            }
        }

        return false;
    }

    /** @return array{database: array<string, mixed>, services: array<string, mixed>, site: array<string, mixed>} */
    public function collected(): array
    {
        return [
            'database' => $this->data('database'),
            'services' => $this->data('services'),
            'site' => $this->data('site'),
        ];
    }

    public function reset(): void
    {
        $this->session->forget(array_map(fn (string $step) => "install.{$step}", self::STEPS));
    }
}
