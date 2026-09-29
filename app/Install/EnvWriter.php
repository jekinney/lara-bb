<?php

namespace App\Install;

use InvalidArgumentException;

/** Writes KEY=value pairs into an env file atomically, keeping every other line as it was. */
final class EnvWriter
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     * @param  string|null  $template  Contents to start from when the file does not exist yet.
     */
    public function write(string $path, array $values, ?string $template = null): void
    {
        $lines = explode("\n", str_replace("\r\n", "\n", is_file($path) ? (string) file_get_contents($path) : ($template ?? '')));
        $pending = $values;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $pending)) {
                $lines[$i] = $m[1].'='.$this->format($m[1], $pending[$m[1]]);
                unset($pending[$m[1]]);
            }
        }

        $existing = rtrim(implode("\n", $lines));
        $body = $existing === '' ? '' : $existing."\n";
        foreach ($pending as $key => $value) {
            $body .= $key.'='.$this->format($key, $value)."\n";
        }

        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temp, $body);
        chmod($temp, 0600);
        rename($temp, $path);
    }

    private function format(string $key, string|int|bool|null $value): string
    {
        $value = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };

        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException("The value for {$key} contains a line break.");
        }

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+,=-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
