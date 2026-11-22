<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Milon\Barcode\DNS2D;

/**
 * Time-based one-time passwords (RFC 6238: 6 digits, 30 s, HMAC-SHA1), compatible with
 * Google Authenticator, Microsoft Authenticator, Authy, etc. Recovery codes are stored
 * hashed and can each be used once.
 */
class TwoFactor
{
    private const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = "";
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, "0", STR_PAD_LEFT);
        }

        return implode("", array_map(fn ($chunk) => self::ALPHABET[bindec(str_pad($chunk, 5, "0"))], str_split($bits, 5)));
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = SystemSettings::companyName();

        return "otpauth://totp/" . rawurlencode($issuer . ":" . $user->email)
            . "?secret={$secret}&issuer=" . rawurlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
    }

    /** QR code drawn on the server: the secret never goes to a third-party service. */
    public function qrSvg(string $url): string
    {
        return (new DNS2D)->getBarcodeSVG($url, "QRCODE", 4, 4);
    }

    /** Current code for a secret (also used by the tests). */
    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $hash = hash_hmac("sha1", pack("N*", 0, $counter), $this->decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1000000), 6, "0", STR_PAD_LEFT);
    }

    /** Accepts the previous, current and next 30-second window (clock drift). */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace("/\D/", "", $code);
        if (strlen($code) !== 6) {
            return false;
        }

        foreach ([-30, 0, 30] as $drift) {
            if (hash_equals($this->code($secret, time() + $drift), $code)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{plain: array<int, string>, hashed: array<int, string>} */
    public function recoveryCodes(int $count = 8): array
    {
        $plain = collect(range(1, $count))->map(fn () => Str::upper(Str::random(5) . "-" . Str::random(5)))->all();

        return ["plain" => $plain, "hashed" => array_map(fn ($c) => Hash::make($c), $plain)];
    }

    /** Uses (and removes) a recovery code. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $hashed) {
            if (Hash::check($code, $hashed)) {
                unset($codes[$i]);
                $user->forceFill(["two_factor_recovery_codes" => array_values($codes)])->saveQuietly();

                return true;
            }
        }

        return false;
    }

    private function decode(string $secret): string
    {
        $bits = "";
        foreach (str_split(strtoupper($secret)) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, "0", STR_PAD_LEFT);
            }
        }

        return implode("", array_map(fn ($byte) => chr(bindec($byte)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}
