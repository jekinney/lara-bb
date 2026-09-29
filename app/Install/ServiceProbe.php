<?php

namespace App\Install;

use Illuminate\Mail\Message;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Connectivity checks for the optional services. Nothing here is saved. */
class ServiceProbe
{
    /** @param  array<string, mixed>  $input  Needs redis_host and redis_port, optionally redis_password. */
    public function redis(array $input): ProbeResult
    {
        try {
            $this->redisManager($input)->connection('default')->ping();

            return ProbeResult::ok('Redis answered.');
        } catch (Throwable $e) {
            return ProbeResult::fail('Could not reach Redis: '.mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * @param  array<string, mixed>  $config  A Laravel filesystem disk definition.
     */
    public function storage(array $config): ProbeResult
    {
        try {
            $disk = Storage::build($config + ['throw' => true]);
            $name = '.larabb-probe-'.bin2hex(random_bytes(4));

            $disk->put($name, 'ok');
            $readBack = $disk->get($name);
            $disk->delete($name);

            return $readBack === 'ok'
                ? ProbeResult::ok('Files can be written, read and deleted.')
                : ProbeResult::fail('The storage disk did not return what was written.');
        } catch (Throwable $e) {
            return ProbeResult::fail('Could not use this storage: '.mb_substr($e->getMessage(), 0, 200));
        }
    }

    /**
     * @param  array<string, mixed>  $transport  A Laravel mailer definition.
     */
    public function mail(array $transport, string $from, string $to): ProbeResult
    {
        try {
            $mailer = Mail::build($transport);
            $mailer->alwaysFrom($from, 'laraBB');
            $mailer->raw('This is a test message from the laraBB installer.', fn (Message $message) => $message->to($to)->subject('laraBB test email'));

            return ProbeResult::ok("Test email sent to {$to}.");
        } catch (Throwable $e) {
            return ProbeResult::fail('Could not send email: '.mb_substr($e->getMessage(), 0, 200));
        }
    }

    /** @param  array<string, mixed>  $input */
    protected function redisManager(array $input): RedisManager
    {
        return new RedisManager(app(), config('database.redis.client', 'phpredis'), [
            'default' => [
                'host' => (string) $input['redis_host'],
                'port' => (int) $input['redis_port'],
                'password' => ($input['redis_password'] ?? '') === '' ? null : (string) $input['redis_password'],
                'database' => 0,
                'timeout' => 3,
            ],
        ]);
    }
}
