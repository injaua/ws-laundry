<?php

namespace App\Services\Security;

use App\Models\SiteSetting;

/** Security & compliance settings (stored as site settings). */
class SecuritySettings
{
    public const ROLES = ["admin", "owner", "accountant", "cashier", "courier"];

    public const DEFAULTS = [
        "security_2fa_roles" => [],          // roles that must use two-factor authentication
        "security_idle_minutes" => 60,       // 0 = no idle logout
        "security_backup_retention_days" => 14,
        "security_fiscal_retention_years" => 10,
    ];

    /** @return array<int, string> */
    public static function twoFactorRoles(): array
    {
        $roles = json_decode((string) SiteSetting::get("security_2fa_roles", "[]"), true);

        return is_array($roles) ? array_values(array_intersect($roles, self::ROLES)) : [];
    }

    public static function idleMinutes(): int
    {
        return max(0, (int) SiteSetting::get("security_idle_minutes", self::DEFAULTS["security_idle_minutes"]));
    }

    public static function backupRetentionDays(): int
    {
        return max(1, (int) SiteSetting::get("security_backup_retention_days", self::DEFAULTS["security_backup_retention_days"]));
    }

    public static function fiscalRetentionYears(): int
    {
        return max(1, (int) SiteSetting::get("security_fiscal_retention_years", self::DEFAULTS["security_fiscal_retention_years"]));
    }

    public static function save(array $data): void
    {
        SiteSetting::set("security_2fa_roles", json_encode(array_values(array_intersect($data["two_factor_roles"] ?? [], self::ROLES))));
        SiteSetting::set("security_idle_minutes", (int) $data["idle_minutes"]);
        SiteSetting::set("security_backup_retention_days", (int) $data["backup_retention_days"]);
        SiteSetting::set("security_fiscal_retention_years", (int) $data["fiscal_retention_years"]);
    }
}
