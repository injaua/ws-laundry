<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * System settings (Admin → System settings): company identity and branding (General) and
 * how numbers, money and dates are shown (Localization). Read once per request.
 */
class SystemSettings
{
    public const DATE_FORMATS = ["d/m/Y", "d-m-Y", "d.m.Y", "Y-m-d", "m/d/Y", "j M Y"];

    public const TIME_FORMATS = ["H:i", "H:i:s", "h:i A"];

    /** Separators: [decimal, thousands]. */
    public const NUMBER_FORMATS = [
        "dot_comma" => [".", ","],    // 1,234.50
        "comma_dot" => [",", "."],    // 1.234,50
        "comma_space" => [",", " "],  // 1 234,50
        "dot_space" => [".", " "],    // 1 234.50
        "dot_none" => [".", ""],      // 1234.50
    ];

    public const BRANDING_FILES = ["brand_logo", "brand_logo_dark", "brand_favicon"];

    public const DEFAULTS = [
        // General
        "company_name" => "WS Clean & Laundry",
        "company_legal_name" => "WS Clean & Laundry Serviços, Lda",
        "company_nuit" => "",
        "company_phone" => "",
        "company_email" => "",
        "company_address" => "",
        "company_share_capital" => "",
        "invoice_bank_details" => "",
        "document_footer" => "",
        "brand_logo" => "",
        "brand_logo_dark" => "",
        "brand_favicon" => "",
        // Localization
        "locale_default" => "pt",
        "currency_code" => "MZN",
        "currency_symbol" => "Mts",
        "currency_position" => "after",
        "currency_decimals" => "0",
        "number_format" => "dot_comma",
        "timezone" => "UTC",
        "date_format" => "d/m/Y",
        "time_format" => "H:i",
        "text_direction" => "ltr",
        "phone_country_code" => "+258",
    ];

    /** @var array<string, array> per store ("" = company level) */
    private static array $cache = [];

    private static ?Currency $currency = null;

    public static function all(): array
    {
        $scope = (string) CurrentStore::id();
        if (!isset(self::$cache[$scope])) {
            try {
                // Identity (name, NUIT, logo…) can differ per store; localization is company-wide.
                $stored = SiteSetting::many(array_keys(self::DEFAULTS));
            } catch (Throwable) {
                $stored = []; // table not migrated yet
            }
            // Empty stored values fall back to the default only for settings that cannot be blank.
            $keepBlank = ["company_nuit", "company_phone", "company_email", "company_address", "company_share_capital", "invoice_bank_details", "document_footer", "brand_logo", "brand_logo_dark", "brand_favicon"];
            foreach ($stored as $k => $v) {
                if (($v === null || $v === "") && !in_array($k, $keepBlank, true)) {
                    unset($stored[$k]);
                }
            }
            self::$cache[$scope] = array_merge(self::DEFAULTS, $stored);
        }

        return self::$cache[$scope];
    }

    public static function get(string $key): string
    {
        return (string) (self::all()[$key] ?? "");
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$currency = null;
    }

    /** The default currency; falls back to the stored symbol settings if the list is missing. */
    public static function currency(): Currency
    {
        if (self::$currency === null) {
            try {
                self::$currency = Currency::where("code", self::get("currency_code"))->first();
            } catch (Throwable) {
                self::$currency = null;
            }
            self::$currency ??= new Currency([
                "code" => self::get("currency_code"), "name" => self::get("currency_code"),
                "symbol" => self::get("currency_symbol"), "position" => self::get("currency_position"),
                "decimals" => (int) self::get("currency_decimals"), "is_active" => true,
            ]);
        }

        return self::$currency;
    }

    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                SiteSetting::set($key, (string) ($value ?? ""));
            }
        }
        self::flush();
    }

    /** Applies the settings to the running application (called at boot). */
    public static function apply(): void
    {
        $tz = self::timezone();
        config(["app.timezone" => $tz, "app.name" => self::companyName()]);
        date_default_timezone_set($tz);
    }

    // ---------- Identity ----------

    public static function companyName(): string
    {
        return self::get("company_name");
    }

    public static function legalName(): string
    {
        return self::get("company_legal_name") ?: self::companyName();
    }

    public static function logoUrl(): string
    {
        return self::fileUrl("brand_logo") ?? asset("images/logo.png");
    }

    /**
     * The logo as a data: URI, for PDFs (dompdf never fetches URLs). Null when there is no
     * PNG / JPEG / GIF logo to embed.
     */
    public static function logoDataUri(): ?string
    {
        $path = self::get("brand_logo");
        $file = $path !== "" && Storage::disk("public")->exists($path)
            ? Storage::disk("public")->path($path)
            : public_path("images/logo.png");

        if (!is_file($file)) {
            return null;
        }
        $mime = mime_content_type($file) ?: "";
        if (!in_array($mime, ["image/png", "image/jpeg", "image/gif"], true)) {
            return null;
        }

        // Shown ~45 px high: a 360 px wide PNG is plenty and keeps each PDF small.
        return Cache::rememberForever("pdf-logo:" . md5($file . filemtime($file)), function () use ($file, $mime) {
            $image = function_exists("imagecreatefromstring") ? @imagecreatefromstring((string) file_get_contents($file)) : false;
            if (!$image || imagesx($image) <= 360) {
                return "data:{$mime};base64," . base64_encode((string) file_get_contents($file));
            }
            $small = imagescale($image, 360, -1, IMG_BICUBIC);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            ob_start();
            imagepng($small, null, 9);

            return "data:image/png;base64," . base64_encode((string) ob_get_clean());
        });
    }

    /** Logo for dark backgrounds (falls back to the regular logo once one is uploaded). */
    public static function logoDarkUrl(): string
    {
        return self::fileUrl("brand_logo_dark") ?? self::fileUrl("brand_logo") ?? asset("images/logo-light.png");
    }

    public static function faviconUrl(): string
    {
        return self::fileUrl("brand_favicon") ?? self::fileUrl("brand_logo") ?? asset("images/logo.png");
    }

    private static function fileUrl(string $key): ?string
    {
        $path = self::get($key);

        return $path !== "" ? Storage::disk("public")->url($path) . "?v=" . substr(md5($path), 0, 8) : null;
    }

    // ---------- Localization ----------

    public static function defaultLocale(): string
    {
        $locale = self::get("locale_default");

        return Languages::isActive($locale) ? $locale : (Languages::active()->keys()->first() ?? "pt");
    }

    public static function timezone(): string
    {
        $tz = self::get("timezone");

        return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : "UTC";
    }

    /** "+258" -> "258": prefix for local 9-digit numbers in WhatsApp links. */
    public static function phoneCountryDigits(): string
    {
        return preg_replace("/\D+/", "", self::get("phone_country_code")) ?: "258";
    }

    /** Right-to-left when the current language is RTL (or the whole site is set to RTL). */
    public static function isRtl(): bool
    {
        return self::get("text_direction") === "rtl" || Languages::isRtl(app()->getLocale());
    }

    public static function dateFormat(): string
    {
        return self::get("date_format");
    }

    public static function dateTimeFormat(): string
    {
        return self::get("date_format") . " " . self::get("time_format");
    }

    public static function currencySymbol(): string
    {
        return self::currency()->symbol;
    }

    public static function separators(): array
    {
        return self::NUMBER_FORMATS[self::get("number_format")] ?? self::NUMBER_FORMATS["dot_comma"];
    }

    /** A number with the configured separators (no currency). */
    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        [$dec, $thousands] = self::separators();

        return number_format((float) $value, $decimals, $dec, $thousands);
    }

    /** A quantity or rate without trailing zeros: 2.50 -> "2.5", 3.00 -> "3". */
    public static function quantity(float|int|string|null $value, int $decimals = 2): string
    {
        $formatted = self::number($value, $decimals);
        if ($decimals > 0) {
            $dec = self::separators()[0];
            $formatted = rtrim(rtrim($formatted, "0"), $dec);
        }

        return $formatted;
    }

    /** An amount with the currency symbol in the configured position. */
    public static function money(float|int|string|null $value, ?int $decimals = null): string
    {
        $value = (float) $value;
        $currency = self::currency();
        $formatted = self::number(abs($value), $decimals ?? $currency->decimals);
        $symbol = $currency->symbol;
        $text = $currency->position === "before" ? "{$symbol} {$formatted}" : "{$formatted} {$symbol}";

        return ($value < 0 ? "-" : "") . $text;
    }

    public static function formatDate(DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === "") {
            return "";
        }
        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $carbon->translatedFormat($withTime ? self::dateTimeFormat() : self::dateFormat());
    }

    /** Settings handed to front-end scripts (charts, totals calculated in the browser). */
    public static function forJs(): array
    {
        [$dec, $thousands] = self::separators();

        return [
            "symbol" => self::currency()->symbol,
            "position" => self::currency()->position,
            "decimals" => self::currency()->decimals,
            "decimal" => $dec,
            "thousands" => $thousands,
        ];
    }

    /** Examples shown next to each option in the form. */
    public static function examples(): array
    {
        $now = now();

        return [
            "date" => collect(self::DATE_FORMATS)->mapWithKeys(fn ($f) => [$f => $now->translatedFormat($f)])->all(),
            "time" => collect(self::TIME_FORMATS)->mapWithKeys(fn ($f) => [$f => $now->format($f)])->all(),
            "number" => collect(self::NUMBER_FORMATS)->map(fn ($s) => number_format(1234567.5, 2, $s[0], $s[1]))->all(),
        ];
    }
}
