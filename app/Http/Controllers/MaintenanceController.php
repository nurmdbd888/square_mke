<?php

namespace App\Http\Controllers;

use App\Support\Maintenance\AppKey;
use App\Support\Maintenance\EnvironmentCheck;
use App\Support\Maintenance\FolderGuard;
use App\Support\Maintenance\MaintenanceGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Throwable;

/**
 * The owner's replacement for a terminal. Results of actions are rendered
 * straight into the response because there is no session to flash them to.
 */
class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceGate $gate,
        private readonly EnvironmentCheck $check,
        private readonly FolderGuard $guard,
    ) {}

    public function show(Request $request): BaseResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        if (! $this->gate->isAuthenticated($request)) {
            return response()->view('maintenance.login', ['error' => null]);
        }

        return $this->dashboard($request);
    }

    public function login(Request $request): BaseResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        if ($this->gate->tooManyAttempts($request)) {
            return response()->view('maintenance.login', ['error' => __('Too many attempts. Wait 10 minutes and try again.')], 429);
        }

        if (! $this->gate->tokenMatches((string) $request->input('token', ''))) {
            $this->gate->recordFailedAttempt($request);
            Log::warning('Maintenance login failed', ['ip' => $request->ip()]);

            return response()->view('maintenance.login', ['error' => __('That secret phrase is not correct.')], 422);
        }

        $this->gate->clearAttempts($request);

        return redirect()->route('maintenance')->withCookie($this->gate->issueCookie($request));
    }

    public function logout(Request $request): BaseResponse
    {
        return redirect()->route('maintenance')->withCookie($this->gate->forgetCookie());
    }

    public function generateKey(Request $request, AppKey $appKey): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $result = $appKey->generateAndStore();

        $message = match ($result['status']) {
            'written' => __('APP_KEY was written to :path. Reload this page.', ['path' => $result['path']]),
            'exists' => __('APP_KEY is already set. It was not changed.'),
            'no_env' => __('The settings file :path does not exist. Create it first, then add this line:', ['path' => $result['path']]),
            'not_writable' => __('The settings file :path cannot be written. Open it in File Manager and set this line:', ['path' => $result['path']]),
        };

        return $this->dashboard($request, [
            'title' => __('Generate APP_KEY'),
            'success' => in_array($result['status'], ['written', 'exists'], true),
            'message' => $message,
            'output' => $result['key'] ? 'APP_KEY='.$result['key'] : null,
        ]);
    }

    public function migrate(Request $request): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return $this->dashboard($request, $this->artisan(__('Run migrations'), 'migrate', ['--force' => true]));
    }

    public function clearCaches(Request $request): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return $this->dashboard($request, $this->artisan(__('Clear caches'), 'optimize:clear'));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{title: string, success: bool, message: string, output: string|null}
     */
    private function artisan(string $title, string $command, array $parameters = []): array
    {
        @set_time_limit(120);

        try {
            $code = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());
            Log::info("Maintenance ran {$command}", ['exit' => $code]);

            return [
                'title' => $title,
                'success' => $code === 0,
                'message' => $code === 0 ? __('Finished successfully.') : __('Finished with errors.'),
                'output' => $output !== '' ? $output : null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'title' => $title,
                'success' => false,
                'message' => __('Failed.'),
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $action
     */
    private function dashboard(Request $request, ?array $action = null): Response
    {
        $this->guard->ensure();
        $check = $this->check->run();

        return response()->view('maintenance.dashboard', $check + [
            'summary' => EnvironmentCheck::summary($check['results']),
            'action' => $action,
            'formToken' => $this->gate->formToken($request),
        ]);
    }

    private function blocked(): ?Response
    {
        abort_unless($this->gate->enabled(), 404);

        if (! $this->gate->tokenConfigured()) {
            return response()->view('maintenance.not-configured', [
                'min' => config('sdm.maintenance.token_min_length'),
                'envPath' => basename(dirname(app()->environmentFilePath())).'/'.basename(app()->environmentFilePath()),
            ], 403);
        }

        return null;
    }

    private function denied(Request $request): ?BaseResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        if (! $this->gate->isAuthenticated($request)) {
            return redirect()->route('maintenance');
        }

        if (! $this->gate->formTokenValid($request)) {
            return response()->view('maintenance.login', ['error' => __('The form expired. Please try again.')], 419);
        }

        return null;
    }
}
