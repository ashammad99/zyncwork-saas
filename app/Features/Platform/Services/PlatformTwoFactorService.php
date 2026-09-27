<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Features\Platform\Models\PlatformAdmin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) for platform admins, plus single-use recovery codes.
 *
 * A code is accepted at most once: the last accepted 30-second step is
 * stored, and only a later step passes. Recovery codes are stored as
 * SHA-256 hashes (they are long random strings, so no slow hash is needed).
 */
class PlatformTwoFactorService extends BaseService
{
    public function __construct(
        protected Google2FA $google2fa
    ) {}

    /** Session key for "password was right, code still needed". */
    private const PENDING = 'platform.two_factor.pending';

    /** Session key for a secret shown during setup but not confirmed yet. */
    private const SETUP_SECRET = 'platform.two_factor.setup_secret';

    public function rememberPending(Session $session, PlatformAdmin $admin): void
    {
        $session->put(self::PENDING, [
            'id' => $admin->id,
            'expires_at' => now()->addMinutes(config('platform.two_factor.pending_minutes'))->getTimestamp(),
        ]);
    }

    /** The admin whose password was accepted, while the code step is still open. */
    public function pendingAdmin(Session $session): ?PlatformAdmin
    {
        $pending = $session->get(self::PENDING);

        if (! $pending || $pending['expires_at'] < now()->getTimestamp()) {
            $this->forgetPending($session);

            return null;
        }

        return PlatformAdmin::where('is_active', true)->find($pending['id']);
    }

    public function forgetPending(Session $session): void
    {
        $session->forget([self::PENDING, self::SETUP_SECRET]);
    }

    /** The secret to enroll, kept in the session only until a code confirms it. */
    public function setupSecret(Session $session): string
    {
        if (! $session->has(self::SETUP_SECRET)) {
            $session->put(self::SETUP_SECRET, $this->generateSecret());
        }

        return $session->get(self::SETUP_SECRET);
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** Inline SVG of the otpauth:// URI, for authenticator apps. Nothing leaves the server. */
    public function qrCodeSvg(PlatformAdmin $admin, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(config('app.name').' Platform', $admin->email, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    /**
     * Check a code against a secret that is not saved yet (setup).
     * Returns the matching time step, or null.
     */
    public function matchingStep(string $secret, string $code): ?int
    {
        $step = $this->google2fa->verifyKeyNewer($secret, $this->digits($code), 0, config('platform.two_factor.window'));

        return $step === false ? null : $step;
    }

    /**
     * Check a code for an enrolled admin and, if it passes, record its time
     * step so the same code cannot be used again.
     */
    public function verify(PlatformAdmin $admin, string $code): bool
    {
        return DB::transaction(function () use ($admin, $code) {
            $admin = PlatformAdmin::whereKey($admin->id)->lockForUpdate()->first();

            if (! $admin?->hasTwoFactor()) {
                return false;
            }

            // With a previous step given, the library returns the matching step (an int) or false.
            $step = $this->google2fa->verifyKeyNewer(
                $admin->two_factor_secret,
                $this->digits($code),
                $admin->two_factor_last_step ?? 0,
                config('platform.two_factor.window')
            );

            if ($step === false) {
                return false;
            }

            $admin->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        });
    }

    /**
     * Save a confirmed secret and return fresh recovery codes (shown once).
     * $step is the one the confirming code matched, so that code is spent.
     *
     * @return list<string>
     */
    public function enable(PlatformAdmin $admin, string $secret, int $step): array
    {
        $codes = $this->newRecoveryCodes();

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();

        return $codes;
    }

    /** Use up one recovery code. Returns how many are left, or null if the code is not valid. */
    public function useRecoveryCode(PlatformAdmin $admin, string $code): ?int
    {
        return DB::transaction(function () use ($admin, $code) {
            $admin = PlatformAdmin::whereKey($admin->id)->lockForUpdate()->first();
            $hashes = $admin?->two_factor_recovery_codes ?? [];
            $hash = hash('sha256', strtoupper(trim($code)));

            $index = array_search($hash, $hashes, true);

            if ($index === false || ! $admin->hasTwoFactor()) {
                return null;
            }

            unset($hashes[$index]);
            $admin->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

            return count($hashes);
        });
    }

    /** Remove 2FA entirely; the admin must set it up again at next sign-in. */
    public function reset(PlatformAdmin $admin): void
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, config('platform.two_factor.recovery_codes')))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    private function digits(string $code): string
    {
        return preg_replace('/\D/', '', $code);
    }
}
