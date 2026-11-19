<?php

namespace App\Models;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Audit trail. Entries are append-only: the model refuses updates and deletes, and each
 * row carries a SHA-256 hash of its content chained to the previous row, so a change made
 * directly in the database is detected by verifyChain() (php artisan audit:verify).
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    /** Security events (logins, 2FA, sessions, privacy requests, backups). */
    public const SECURITY_ACTIONS = [
        "login", "login_failed", "logout", "lockout", "password_changed", "password_reset",
        "two_factor_enabled", "two_factor_disabled", "two_factor_failed", "recovery_code_used",
        "sessions_revoked", "session_expired", "customer_exported", "customer_anonymized", "backup_created", "backup_downloaded",
    ];

    /** Never stored in the trail, whatever the model. */
    private const SECRET_KEYS = ["password", "remember_token", "two_factor_secret", "two_factor_recovery_codes", "payload"];

    /** Setting keys whose values are secrets (masked in the trail). */
    private const SECRET_SETTING = "/(password|secret|token|api_key|public_key|private_key)/i";

    protected $fillable = ["actor_type", "actor_id", "action", "subject_type", "subject_id", "description", "old_values", "new_values", "ip_address", "user_agent", "store_id"];

    protected function casts(): array
    {
        return [
            "old_values" => "json",
            "new_values" => "json",
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("The audit trail is append-only."));
        static::deleting(fn () => throw new LogicException("The audit trail is append-only."));
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSecurityEvent(): bool
    {
        return in_array($this->action, self::SECURITY_ACTIONS, true);
    }

    /** Removes secrets from a set of attribute values before they reach the trail. */
    public static function scrub(?array $values, ?string $subjectType = null, array $hidden = []): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (array_merge(self::SECRET_KEYS, $hidden) as $key) {
            unset($values[$key]);
        }

        // Settings are stored as key/value rows: mask the value of secret keys.
        if ($subjectType === SiteSetting::class && isset($values["key"]) && preg_match(self::SECRET_SETTING, (string) $values["key"])) {
            if (array_key_exists("value", $values) && $values["value"] !== null && $values["value"] !== "") {
                $values["value"] = "••••";
            }
        }

        return $values;
    }

    public static function log($actor, $action, $subject, $description = null, $old = null, $new = null): self
    {
        $subjectType = $subject ? get_class($subject) : User::class; // id 0 = no account (e.g. unknown e-mail)
        $hidden = $subject instanceof Model ? $subject->getHidden() : [];

        // Updates of a setting row carry the key only in the original values.
        if ($subject instanceof SiteSetting && is_array($new) && !isset($new["key"])) {
            $new = ["key" => $subject->key] + $new;
        }

        $request = app()->runningInConsole() && !app()->runningUnitTests() ? null : request();

        return DB::transaction(function () use ($actor, $action, $subjectType, $subject, $description, $old, $new, $hidden, $request) {
            $log = self::create([
                "actor_type" => $actor ? get_class($actor) : null,
                "actor_id" => $actor?->id,
                "action" => $action,
                "subject_type" => $subjectType,
                "subject_id" => $subject?->id ?? 0,
                "description" => $description,
                "old_values" => self::scrub($old, $subjectType, $hidden),
                "new_values" => self::scrub($new, $subjectType, $hidden),
                "ip_address" => $request?->ip(),
                "user_agent" => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                "store_id" => self::storeOf($actor, $subject),
            ]);

            // Hash after the insert: the write lock is held, so the previous row is final.
            $row = DB::table("activity_logs")->where("id", $log->id)->first();
            $previous = DB::table("activity_logs")->where("id", "<", $log->id)->orderByDesc("id")->value("hash");
            $hash = self::hashRow($row, $previous);
            DB::table("activity_logs")->where("id", $log->id)->update(["hash" => $hash]);
            $log->setRawAttributes(array_merge($log->getAttributes(), ["hash" => $hash]), true);

            return $log;
        });
    }

    /**
     * The store an entry belongs to (owners see only their store's entries; admins see all):
     *  - the record's own store (an order, a customer, a store's user or setting);
     *  - the store itself, for changes to a store;
     *  - otherwise the store the actor works in — except for an administrator, whose actions on
     *    records without a store (their own login, global settings, backups, other admins)
     *    are company-wide (null) and must not show up in the store chosen in the switcher.
     */
    public static function storeOf($actor, $subject): ?int
    {
        if ($subject instanceof Model && $subject->getAttribute("store_id")) {
            return (int) $subject->getAttribute("store_id");
        }
        if ($subject instanceof Store && $subject->exists) {
            return (int) $subject->getKey();
        }
        if ($actor instanceof User && $actor->isAdmin()) {
            return null;
        }

        return CurrentStore::id();
    }

    /** A security event (login, 2FA, sessions, privacy, backups). */
    public static function security(string $action, ?Model $subject, string $description, ?User $actor = null, ?array $details = null): self
    {
        return self::log($actor ?? auth()->user(), $action, $subject, $description, null, $details);
    }

    public static function hashRow(object $row, ?string $previous): string
    {
        return hash("sha256", implode("|", [
            $previous ?? "",
            $row->id,
            $row->actor_type ?? "",
            $row->actor_id ?? "",
            $row->action,
            $row->subject_type,
            $row->subject_id,
            $row->description ?? "",
            $row->old_values ?? "",
            $row->new_values ?? "",
            $row->ip_address ?? "",
            $row->user_agent ?? "",
            $row->created_at ?? "",
        ]));
    }

    /**
     * Recomputes the chain and reports the first entry that no longer matches.
     *
     * @return array{ok: bool, checked: int, broken_id: ?int}
     */
    public static function verifyChain(): array
    {
        $previous = null;
        $checked = 0;
        $broken = null;

        DB::table("activity_logs")->orderBy("id")->chunk(1000, function ($rows) use (&$previous, &$checked, &$broken) {
            foreach ($rows as $row) {
                $checked++;
                if ($row->hash === null || !hash_equals(self::hashRow($row, $previous), $row->hash)) {
                    $broken = $row->id;

                    return false;
                }
                $previous = $row->hash;
            }
        });

        return ["ok" => $broken === null, "checked" => $checked, "broken_id" => $broken];
    }

    /** Seals all entries (used once, when the chain is introduced). */
    public static function rehashAll(): void
    {
        $previous = null;
        DB::table("activity_logs")->orderBy("id")->chunk(500, function ($rows) use (&$previous) {
            foreach ($rows as $row) {
                $hash = self::hashRow($row, $previous);
                DB::table("activity_logs")->where("id", $row->id)->update(["hash" => $hash]);
                $previous = $hash;
            }
        });
    }
}
