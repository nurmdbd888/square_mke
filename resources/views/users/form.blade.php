@extends('layouts.app')

@section('title', $user->exists ? __('Edit user') : __('Add user'))

@section('content')
    <x-back-link :href="route('users.index')">{{ __('Back to users') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $user->exists ? __('Edit user') : __('Add user') }}</h1>

    @if ($user->exists && $user->trashed())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{{ __('This user is deactivated and cannot sign in.') }}</span>
            <form method="POST" action="{{ route('users.restore', $user) }}">
                @csrf
                <button class="btn-secondary">{{ __('Reactivate') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card">
            <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif
                <label class="block sm:col-span-2">
                    <span class="label">{{ __('Full name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('User name') }}</span>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50" autocapitalize="none" class="input font-mono">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('Used to sign in. Letters, digits, dot, dash and underscore.') }}</span>
                </label>
                <label class="block">
                    <span class="label">{{ __('Role') }}</span>
                    <select name="role" class="input" required>
                        @foreach (\App\Support\Access\PermissionRegistry::ROLES as $option)
                            @continue($option === 'owner' && ! auth()->user()->isOwner())
                            <option value="{{ $option }}" @selected(old('role', $role) === $option)>{{ __('roles.'.$option) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('E-mail') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="191" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Mobile number') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Language') }}</span>
                    <select name="locale" class="input">
                        <option value="">{{ __('Firm default') }}</option>
                        @foreach (config('sdm.supported_locales') as $code => $label)
                            <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @unless ($user->exists)
                    <div></div>
                    <label class="block">
                        <span class="label">{{ __('Temporary password') }}</span>
                        <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
                        <span class="mt-1 block text-xs text-slate-500">{{ __('At least 10 characters. The user must change it at first sign-in.') }}</span>
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Temporary password again') }}</span>
                        <input type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                    </label>
                @endunless
                <div class="sm:col-span-2">
                    <button class="btn-primary">{{ $user->exists ? __('Save') : __('Add user') }}</button>
                </div>
            </form>
        </section>

        @if ($user->exists && ! $user->trashed())
            <aside class="space-y-6">
                <section class="card">
                    <h2 class="font-semibold text-slate-900">{{ __('Reset password') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Sets a temporary password and signs the user out everywhere. They must change it at next sign-in.') }}</p>
                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="{{ __('Temporary password') }}" aria-label="{{ __('Temporary password') }}" class="input">
                        <input type="password" name="password_confirmation" required autocomplete="new-password" placeholder="{{ __('Temporary password again') }}" aria-label="{{ __('Temporary password again') }}" class="input">
                        <button class="btn-secondary">{{ __('Reset password') }}</button>
                    </form>
                </section>

                @if ($user->hasTwoFactorEnabled())
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Two-factor sign-in') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('Turn it off if the user lost their phone and their recovery codes.') }}</p>
                        <form method="POST" action="{{ route('users.reset-two-factor', $user) }}" class="mt-3" data-confirm="{{ __('Turn off two-factor sign-in for this user?') }}">
                            @csrf
                            <button class="btn-secondary">{{ __('Turn off two-factor sign-in') }}</button>
                        </form>
                    </section>
                @endif

                @can('users.delete')
                    @unless ($user->is(auth()->user()))
                        <section class="card">
                            <h2 class="font-semibold text-slate-900">{{ __('Deactivate') }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ __('The user can no longer sign in. Their history stays. You can reactivate them later.') }}</p>
                            <x-delete-form
                                :action="route('users.destroy', $user)"
                                :confirm="__('Deactivate this user?')"
                                :label="__('Deactivate')"
                                class="mt-3"
                            />
                        </section>
                    @endunless
                @endcan
            </aside>
        @endif
    </div>
@endsection
