<?php

namespace App\Http\Controllers;

use App\Actions\CreateOwner;
use App\Domain\Backup\BackupCatalog;
use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\TwoFactor;
use App\Support\Maintenance\AppKey;
use App\Support\Maintenance\EnvironmentCheck;
use App\Support\Maintenance\FolderGuard;
use App\Support\Maintenance\MaintenanceGate;
use App\Support\Maintenance\OwnerAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Throwable;

/**
 * The owner's replacement for a terminal. Results of actions are rendered
 * straight into the response because there may be no session to flash them to.
 * Before any owner exists the secret phrase opens it; afterwards the visitor
 * must also be signed in as an owner (OwnerAccess).
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

        if (! $this->owners()->allows($request)) {
            return $this->ownerLoginNeeded($request);
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
        AuditLogger::log('maintenance.generate_key', null, null, ['status' => $result['status']]);

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

        $result = $this->artisan(__('Run migrations'), 'migrate', ['--force' => true]);
        $this->owners()->forget();
        AuditLogger::log('maintenance.migrate', null, null, ['success' => $result['success']]);

        return $this->dashboard($request, $result);
    }

    public function clearCaches(Request $request): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $result = $this->artisan(__('Clear caches'), 'optimize:clear');
        AuditLogger::log('maintenance.clear_caches', null, null, ['success' => $result['success']]);

        return $this->dashboard($request, $result);
    }

    public function backup(Request $request): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $result = $this->artisan(__('Backup now'), 'sdm:backup', ['--no-drive' => false]);
        AuditLogger::log('maintenance.backup', null, null, ['success' => $result['success']]);

        return $this->dashboard($request, $result);
    }

    public function restore(Request $request): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data = $request->validate([
            'basename' => ['required', 'string', 'max:191'],
            'confirm' => ['required', 'string', 'in:RESTORE'],
        ]);

        $result = $this->artisan(__('Restore backup'), 'sdm:restore', [
            'basename' => $data['basename'],
            '--force' => true,
        ]);
        AuditLogger::log('maintenance.restore', null, null, [
            'success' => $result['success'],
            'basename' => $data['basename'],
        ]);

        return $this->dashboard($request, $result);
    }

    public function createOwner(Request $request, CreateOwner $createOwner): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        if (! $this->owners()->usersTableReady()) {
            return $this->dashboard($request, [
                'title' => __('Create owner account'),
                'success' => false,
                'message' => __('Press "Run migrations" first.'),
                'output' => null,
            ]);
        }

        try {
            $user = $createOwner($request->only('name', 'username', 'email', 'password', 'password_confirmation'),
                AuditLogger::SOURCE_MAINTENANCE, $this->owners()->signedInOwner($request));
        } catch (ValidationException $e) {
            return $this->dashboard($request, null, $e->errors(), $request->only('name', 'username', 'email'));
        }

        $this->owners()->forget();

        return $this->dashboard($request, [
            'title' => __('Create owner account'),
            'success' => true,
            'message' => __('Owner :username was created. Sign in at :url.', ['username' => $user->username, 'url' => route('login')]),
            'output' => null,
        ]);
    }

    public function recoverOwner(Request $request, TwoFactor $twoFactor): BaseResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        abort_unless($this->owners()->recoveryMode(), 404);

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $owner = User::query()->role(PermissionRegistry::OWNER)->where('username', mb_strtolower(trim((string) $request->input('username'))))->first();

        if ($validator->fails() || ! $owner) {
            $errors = $validator->errors()->toArray();

            if (! $owner) {
                $errors['username'][] = __('No owner with that user name.');
            }

            return $this->dashboard($request, null, $errors, $request->only('username'));
        }

        $owner->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->saveQuietly();
        $twoFactor->disable($owner);
        AuditLogger::log('maintenance.owner_recovered', $owner, null, ['password' => 'reset', 'two_factor' => 'off']);

        return $this->dashboard($request, [
            'title' => __('Reset an owner\'s password'),
            'success' => true,
            'message' => __('The password of :username was reset and two-factor sign-in was turned off. Now set MAINTENANCE_OWNER_RECOVERY=false in .env.', ['username' => $owner->username]),
            'output' => null,
        ]);
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
     * @param  array<string, list<string>>  $errors  form errors (no session to flash them to)
     * @param  array<string, mixed>  $old
     */
    private function dashboard(Request $request, ?array $action = null, array $errors = [], array $old = []): Response
    {
        $this->guard->ensure();
        $check = $this->check->run();

        return response()->view('maintenance.dashboard', $check + [
            'summary' => EnvironmentCheck::summary($check['results']),
            'backups' => app(BackupCatalog::class)->listLocal(),
            'action' => $action,
            'formToken' => $this->gate->formToken($request),
            'owners' => $this->owners()->ownerCount(),
            'usersReady' => $this->owners()->usersTableReady(),
            'recovery' => $this->owners()->recoveryMode(),
            'signedInOwner' => $this->owners()->signedInOwner($request),
            'formErrors' => new MessageBag($errors),
            'old' => $old,
        ]);
    }

    private function owners(): OwnerAccess
    {
        return app(OwnerAccess::class);
    }

    private function ownerLoginNeeded(Request $request): BaseResponse
    {
        if ($request->hasSession()) {
            $request->session()->put('url.intended', route('maintenance'));
        }

        return response()->view('maintenance.owner-login', [], 403);
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

        if (! $this->owners()->allows($request)) {
            return $this->ownerLoginNeeded($request);
        }

        return null;
    }
}
