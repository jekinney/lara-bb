<?php

namespace App\Install;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * A one-time token that proves the person opening the installer controls the server.
 * It is printed to the application log and kept in a file, and expires after a short window.
 */
final class SetupToken
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** Returns the current token, issuing a new one when none exists or it has expired. */
    public function ensure(): string
    {
        return $this->read() ?? $this->issue();
    }

    public function verify(string $input): bool
    {
        $current = $this->read();

        return $current !== null
            && hash_equals($this->normalise($current), $this->normalise($input));
    }

    public function forget(): void
    {
        File::delete($this->file());
    }

    public function file(): string
    {
        return config('larabb.install.token_file');
    }

    private function issue(): string
    {
        $characters = '';
        for ($i = 0; $i < 12; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $token = implode('-', str_split($characters, 4));
        $minutes = (int) config('larabb.install.token_ttl_minutes');

        File::ensureDirectoryExists(dirname($this->file()));
        File::put($this->file(), json_encode([
            'token' => $token,
            'expires_at' => now()->addMinutes($minutes)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
        chmod($this->file(), 0600);

        Log::warning("laraBB setup token: {$token} (valid for {$minutes} minutes)");

        return $token;
    }

    private function read(): ?string
    {
        if (! is_file($this->file())) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->file()), true);

        if (! is_array($data) || ! is_string($data['token'] ?? null) || ! is_int($data['expires_at'] ?? null)) {
            return null;
        }

        return $data['expires_at'] > now()->getTimestamp() ? $data['token'] : null;
    }

    private function normalise(string $token): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $token));
    }
}
