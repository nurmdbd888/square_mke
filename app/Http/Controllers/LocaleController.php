<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('sdm.supported_locales')), 404);

        return redirect()->to($this->backUrl($request))
            ->withCookie(cookie(SetLocale::COOKIE, $locale, 60 * 24 * 365, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));
    }

    private function backUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return url('/');
    }
}
