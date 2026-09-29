<?php

namespace App\Http\Controllers;

use App\Install\DatabaseProbe;
use App\Install\Installer;
use App\Install\InstallFailed;
use App\Install\ProbeResult;
use App\Install\Requirements;
use App\Install\ServiceProbe;
use App\Install\SetupToken;
use App\Install\Wizard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class InstallController extends Controller
{
    public function __construct(
        private readonly SetupToken $token,
        private readonly Requirements $requirements,
        private readonly DatabaseProbe $database,
        private readonly ServiceProbe $services,
        private readonly Installer $installer,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('install.'.$this->wizard($request)->current());
    }

    // ---- 1. Setup token ----

    public function token(Request $request): View
    {
        $this->token->ensure();

        return $this->page('install.token', 'token', ['tokenFile' => $this->token->file()]);
    }

    public function submitToken(Request $request): RedirectResponse
    {
        $input = $request->validate(['token' => ['required', 'string', 'max:40']]);

        if (! $this->token->verify($input['token'])) {
            return back()->withErrors(['token' => 'That token is not valid, or it has expired. Reload this page to get a new one.']);
        }

        $this->wizard($request)->complete('token');

        return redirect()->route('install.requirements');
    }

    // ---- 2. Requirements ----

    public function requirements(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'requirements', fn () => $this->page('install.requirements', 'requirements', [
            'checks' => $this->requirements->evaluate($request),
        ]));
    }

    public function submitRequirements(Request $request): RedirectResponse
    {
        return $this->guarded($request, 'requirements', function () use ($request) {
            if (! $this->requirements->passes($this->requirements->evaluate($request))) {
                return back()->withErrors(['requirements' => 'Fix the failed checks, then try again.']);
            }

            $this->wizard($request)->complete('requirements');

            return redirect()->route('install.database');
        });
    }

    // ---- 3. Database ----

    public function database(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'database', fn () => $this->page('install.database', 'database', [
            'values' => $this->wizard($request)->data('database') + [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'larabb', 'tls' => false,
            ],
        ]));
    }

    public function submitDatabase(Request $request): RedirectResponse
    {
        return $this->guarded($request, 'database', function () use ($request) {
            $request->merge(['tls' => $request->boolean('tls')]);
            $sqlite = $request->input('driver') === 'sqlite';

            $data = $request->validate([
                'driver' => ['required', Rule::in(['mysql', 'mariadb', 'sqlite'])],
                'host' => [Rule::requiredIf(! $sqlite), 'nullable', 'string', 'max:255'],
                'port' => [Rule::requiredIf(! $sqlite), 'nullable', 'integer', 'between:1,65535'],
                'database' => ['required', 'string', 'max:255'],
                'username' => [Rule::requiredIf(! $sqlite), 'nullable', 'string', 'max:128'],
                'password' => ['nullable', 'string', 'max:255'],
                'tls' => ['boolean'],
                'ca_cert' => [Rule::requiredIf(! $sqlite && $request->boolean('tls')), 'nullable', 'string', 'max:20000'],
                'action' => ['required', Rule::in(['test', 'continue'])],
            ]);

            $action = Arr::pull($data, 'action');
            $probe = $this->database->test($data);

            if ($action === 'test' || ! $probe->ok) {
                return $this->probed($probe, 'database');
            }

            $this->wizard($request)->complete('database', $data);

            return redirect()->route('install.services');
        });
    }

    // ---- 4. Services ----

    public function services(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'services', fn () => $this->page('install.services', 'services', [
            'values' => $this->wizard($request)->data('services') + [
                'cache_driver' => 'database', 'redis_host' => '127.0.0.1', 'redis_port' => 6379,
                'mail_mailer' => 'log', 'mail_port' => 587, 'mail_encryption' => 'tls',
                'storage' => 'local', 's3_region' => 'nyc3',
            ],
        ]));
    }

    public function submitServices(Request $request): RedirectResponse
    {
        return $this->guarded($request, 'services', function () use ($request) {
            $redis = $request->input('cache_driver') === 'redis';
            $smtp = $request->input('mail_mailer') === 'smtp';
            $s3 = $request->input('storage') === 's3';

            $data = $request->validate([
                'cache_driver' => ['required', Rule::in(['database', 'redis'])],
                'redis_host' => [Rule::requiredIf($redis), 'nullable', 'string', 'max:255'],
                'redis_port' => [Rule::requiredIf($redis), 'nullable', 'integer', 'between:1,65535'],
                'redis_password' => ['nullable', 'string', 'max:255'],
                'mail_mailer' => ['required', Rule::in(['log', 'smtp'])],
                'mail_from' => ['required', 'email', 'max:255'],
                'mail_host' => [Rule::requiredIf($smtp), 'nullable', 'string', 'max:255'],
                'mail_port' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'between:1,65535'],
                'mail_username' => ['nullable', 'string', 'max:255'],
                'mail_password' => ['nullable', 'string', 'max:255'],
                'mail_encryption' => ['nullable', Rule::in(['none', 'tls', 'ssl'])],
                'storage' => ['required', Rule::in(['local', 's3'])],
                's3_key' => [Rule::requiredIf($s3), 'nullable', 'string', 'max:255'],
                's3_secret' => [Rule::requiredIf($s3), 'nullable', 'string', 'max:255'],
                's3_region' => [Rule::requiredIf($s3), 'nullable', 'string', 'max:64'],
                's3_bucket' => [Rule::requiredIf($s3), 'nullable', 'string', 'max:255'],
                's3_endpoint' => ['nullable', 'url', 'max:255'],
                'test_email' => [Rule::requiredIf($request->input('action') === 'test_mail'), 'nullable', 'email'],
                'action' => ['required', Rule::in(['continue', 'test_redis', 'test_storage', 'test_mail'])],
            ]);

            $action = Arr::pull($data, 'action');
            $testEmail = Arr::pull($data, 'test_email');

            return match ($action) {
                'test_redis' => $this->probed($this->services->redis($data), 'services'),
                'test_storage' => $this->probed($this->services->storage($this->storageDisk($data)), 'services'),
                'test_mail' => $this->probed($this->services->mail($this->mailTransport($data), $data['mail_from'], $testEmail), 'services'),
                default => $this->continueServices($request, $data),
            };
        });
    }

    /** @param  array<string, mixed>  $data */
    private function continueServices(Request $request, array $data): RedirectResponse
    {
        // Services that the board cannot run without must answer before the wizard moves on.
        $required = [
            $data['cache_driver'] === 'redis' ? $this->services->redis($data) : ProbeResult::ok(''),
            $data['storage'] === 's3' ? $this->services->storage($this->storageDisk($data)) : ProbeResult::ok(''),
        ];

        foreach ($required as $probe) {
            if (! $probe->ok) {
                return $this->probed($probe, 'services');
            }
        }

        $this->wizard($request)->complete('services', $data);

        return redirect()->route('install.site');
    }

    // ---- 5. Board and founder ----

    public function site(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'site', fn () => $this->page('install.site', 'site', [
            'values' => array_diff_key($this->wizard($request)->data('site'), ['founder_password' => 1]) + [
                'board_name' => 'laraBB', 'board_url' => rtrim($request->getSchemeAndHttpHost(), '/'),
                'timezone' => 'UTC', 'theme' => 'default', 'editor' => 'markdown',
            ],
            'timezones' => timezone_identifiers_list(),
        ]));
    }

    public function submitSite(Request $request): RedirectResponse
    {
        return $this->guarded($request, 'site', function () use ($request) {
            $data = $request->validate([
                'board_name' => ['required', 'string', 'max:60'],
                'board_url' => ['required', 'url', 'max:255'],
                'timezone' => ['required', Rule::in(timezone_identifiers_list())],
                'theme' => ['required', Rule::in(['default'])],
                'editor' => ['required', Rule::in(['markdown', 'bbcode'])],
                'founder_username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_.-]+$/'],
                'founder_email' => ['required', 'email', 'max:255'],
                'founder_password' => ['required', 'confirmed', Password::min(12)->max(200)],
            ]);

            $this->wizard($request)->complete('site', $data);

            return redirect()->route('install.review');
        });
    }

    // ---- 6. Review and install ----

    public function review(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'review', fn () => $this->page('install.review', 'review', [
            'summary' => $this->wizard($request)->collected(),
        ]));
    }

    public function run(Request $request): View|RedirectResponse
    {
        return $this->guarded($request, 'review', function () use ($request) {
            $wizard = $this->wizard($request);

            try {
                $tasks = $this->installer->run($wizard->collected());
            } catch (InstallFailed $e) {
                return back()->withErrors(['install' => $e->getMessage()]);
            }

            $wizard->reset();
            $request->session()->invalidate();

            return view('install.done', ['tasks' => $tasks, 'boardUrl' => url('/')]);
        });
    }

    // ---- helpers ----

    private function wizard(Request $request): Wizard
    {
        return new Wizard($request->session());
    }

    /**
     * Runs $step only when every earlier step is done. Otherwise sends the visitor back to the first
     * step they have not finished.
     *
     * @template T of View|RedirectResponse
     *
     * @param  callable(): T  $show
     * @return T|RedirectResponse
     */
    private function guarded(Request $request, string $step, callable $show): View|RedirectResponse
    {
        $wizard = $this->wizard($request);

        return $wizard->allows($step)
            ? $show()
            : redirect()->route('install.'.$wizard->current());
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, string $step, array $data = []): View
    {
        return view($view, $data + ['step' => $step, 'steps' => Wizard::STEPS]);
    }

    private function probed(ProbeResult $probe, string $section): RedirectResponse
    {
        return back()->withInput()->with('probe', ['ok' => $probe->ok, 'message' => $probe->message, 'section' => $section]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storageDisk(array $data): array
    {
        if ($data['storage'] === 'local') {
            return ['driver' => 'local', 'root' => storage_path('app/private')];
        }

        return [
            'driver' => 's3',
            'key' => $data['s3_key'],
            'secret' => $data['s3_secret'],
            'region' => $data['s3_region'],
            'bucket' => $data['s3_bucket'],
            'endpoint' => $data['s3_endpoint'] ?? null,
            'use_path_style_endpoint' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mailTransport(array $data): array
    {
        if ($data['mail_mailer'] === 'log') {
            return ['transport' => 'log'];
        }

        return [
            'transport' => 'smtp',
            'host' => $data['mail_host'],
            'port' => (int) $data['mail_port'],
            'username' => $data['mail_username'] ?? null,
            'password' => $data['mail_password'] ?? null,
            'scheme' => ($data['mail_encryption'] ?? 'none') === 'ssl' ? 'smtps' : 'smtp',
        ];
    }
}
