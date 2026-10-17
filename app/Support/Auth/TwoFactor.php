<?php

namespace App\Support\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238, 30-second codes, as in Google Authenticator) with one-time
 * recovery codes. The QR code is an inline SVG, so no image extension is needed.
 */
class TwoFactor
{
    public const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = 'Square Distributor Manager';

        return $this->google2fa->getQRCodeUrl($issuer, $user->username, $secret);
    }

    public function qrSvg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /**
     * Checks a 6-digit code. A code that was already used, or an older one,
     * is refused so a code seen over someone's shoulder cannot be replayed.
     */
    public function verify(User $user, string $code, ?string $secret = null): bool
    {
        $secret ??= $user->two_factor_secret;
        $code = preg_replace('/\D/', '', $code) ?? '';

        if ($secret === null || strlen($code) !== 6) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer($secret, $code, $user->two_factor_last_step, 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step === true ? $this->google2fa->getTimestamp() : $step])->saveQuietly();

        return true;
    }

    /**
     * Uses up a recovery code. Returns true when it was valid.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function newRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::upper(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODES),
        );
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->saveQuietly();
    }
}
