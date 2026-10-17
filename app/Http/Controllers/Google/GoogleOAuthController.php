<?php

namespace App\Http\Controllers\Google;

use App\Domain\Google\GoogleOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleOAuthController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $google) {}

    public function connect(Request $request): RedirectResponse
    {
        $this->authorize('google.manage');

        return redirect()->away($this->google->authorizationUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorize('google.manage');
        $request->validate(['code' => ['required', 'string']]);

        $this->google->connectFromCallback($request->string('code')->toString(), $request->user());

        return redirect()->route('settings.index')->with('status', __('Google account connected.'));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorize('google.manage');
        $this->google->disconnect();

        return redirect()->route('settings.index')->with('status', __('Google disconnected.'));
    }
}
