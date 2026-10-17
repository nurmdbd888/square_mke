{{-- Owner accounts: create the first owner with no terminal, add more owners, and owner recovery. --}}
@if ($recovery)
    <section class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900" data-testid="recovery-mode">
        <p class="font-semibold">{{ __('Owner recovery is on (MAINTENANCE_OWNER_RECOVERY=true).') }}</p>
        <p class="mt-1 text-sm">{{ __('The secret phrase alone opens this page and can reset an owner\'s password. Set it back to false in .env as soon as you are done.') }}</p>
        @if (($owners ?? 0) > 0)
            <form method="POST" action="{{ route('maintenance.recover-owner') }}" class="mt-4 grid gap-3 sm:grid-cols-4 sm:items-end">
                <input type="hidden" name="_maintenance_token" value="{{ $formToken }}">
                <label class="block">
                    <span class="label">{{ __('Owner user name') }}</span>
                    <input type="text" name="username" value="{{ $old['username'] ?? '' }}" required autocapitalize="none" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('New password') }}</span>
                    <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('New password again') }}</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                </label>
                <button class="btn-primary">{{ __('Reset an owner\'s password') }}</button>
            </form>
        @endif
    </section>
@endif

@if ($usersReady)
    <section @class([
        'mt-4 rounded-xl border p-4',
        'border-brand-200 bg-brand-50' => ($owners ?? 0) === 0,
        'border-slate-200 bg-white' => ($owners ?? 0) > 0,
    ]) data-testid="owner-section">
        <h2 class="font-semibold text-slate-900">{{ ($owners ?? 0) === 0 ? __('Create the owner account') : __('Create owner account') }}</h2>
        <p class="mt-1 text-sm text-slate-700">
            @if (($owners ?? 0) === 0)
                {{ __('No one can sign in yet. Create the first owner: this person can do everything, add other users and change permissions.') }}
            @else
                {{ __(':count owner account(s). You are signed in as :username. Add another owner only if needed; other users are added on the Users page.', ['count' => $owners, 'username' => $signedInOwner?->username ?? '—']) }}
            @endif
        </p>

        @if ($formErrors->any())
            <ul class="mt-3 list-inside list-disc rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert" data-testid="owner-errors">
                @foreach ($formErrors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if (($owners ?? 0) === 0 || $signedInOwner)
            <form method="POST" action="{{ route('maintenance.create-owner') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                <input type="hidden" name="_maintenance_token" value="{{ $formToken }}">
                <label class="block">
                    <span class="label">{{ __('Full name') }}</span>
                    <input type="text" name="name" value="{{ $old['name'] ?? '' }}" required maxlength="120" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('User name') }}</span>
                    <input type="text" name="username" value="{{ $old['username'] ?? '' }}" required minlength="3" maxlength="50" autocapitalize="none" class="input font-mono">
                </label>
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('E-mail') }} <span class="font-normal text-slate-500">({{ __('optional, for password reset links') }})</span></span>
                    <input type="email" name="email" value="{{ $old['email'] ?? '' }}" maxlength="191" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Password') }}</span>
                    <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('At least 10 characters.') }}</span>
                </label>
                <label class="block">
                    <span class="label">{{ __('Password again') }}</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                </label>
                <div class="sm:col-span-2">
                    <button class="btn-primary">{{ __('Create owner account') }}</button>
                </div>
            </form>
        @endif
    </section>
@endif
