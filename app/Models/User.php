<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\Loggable;
use App\Services\Security\SecuritySettings;
use App\Services\Security\UserSessions;
use App\Support\CurrentStore;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'created_by', 'store_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Loggable, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * A new password ends the user's other sessions (all of them when an administrator
     * changed it) and is recorded in the audit trail.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });

        static::updated(function (User $user) {
            if (!$user->wasChanged('password')) {
                return;
            }

            $actor = auth()->user();
            $self = $actor && $actor->is($user);
            $current = $self && app()->bound('session') ? session()->getId() : null;
            app(UserSessions::class)->revoke($user, $current);

            ActivityLog::security('password_changed', $user, "Password changed: {$user->email}" . ($self || !$actor ? '' : " (by {$actor->email})"));
        });
    }

    /** Did this account record anything (audit trail, payments, orders)? */
    public function hasHistory(): bool
    {
        return ActivityLog::where('actor_type', self::class)->where('actor_id', $this->id)->exists()
            || Payment::where('user_id', $this->id)->exists()
            || CashSession::where('user_id', $this->id)->exists();
    }

    /** Two-factor authentication is set up and confirmed. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** The business requires two-factor authentication for this user's role. */
    public function twoFactorRequired(): bool
    {
        return in_array($this->role, SecuritySettings::twoFactorRoles(), true);
    }

    /**
     * Whether the given user may edit or delete this account.
     * Owners manage the staff (accountants, cashiers, couriers) of their own store.
     */
    public function manageableBy(User $actor): bool
    {
        return $actor->isAdmin()
            || ($actor->isOwner() && in_array($this->role, ["cashier", "courier", "accountant"], true)
                && $actor->store_id !== null && (int) $this->store_id === (int) $actor->store_id);
    }

    /** Users of a store (the current one by default). */
    public function scopeInStore($query, ?int $storeId = null)
    {
        return $query->where("store_id", $storeId ?? CurrentStore::id());
    }

    /** People one can work with in the current store: its staff plus the admins. */
    public function scopeColleagues($query)
    {
        return $query->where(fn ($q) => $q->where("store_id", CurrentStore::id())->orWhere("role", "admin"));
    }

    /** The store this user works in (null for admins: they work in every store). */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roles);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is cashier.
     */
    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    /**
     * Check if user is owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Accountant: everything about accounting, tax, treasury, receivables, expenses, payroll and
     * financial reports; operational records (orders, customers, tills…) are read-only.
     */
    public function isAccountant(): bool
    {
        return $this->role === 'accountant';
    }

    /** Admin or owner: the people who run the business (edit / delete operational records). */
    public function isManager(): bool
    {
        return $this->hasRole(['admin', 'owner']);
    }

    /** Sees the financial side of the business: managers and the accountant. */
    public function seesFinance(): bool
    {
        return $this->hasRole(['admin', 'owner', 'accountant']);
    }

    /**
     * Check if user is a courier (home pickup & delivery). Couriers only see their route.
     */
    public function isCourier(): bool
    {
        return $this->role === 'courier';
    }

    /**
     * Self-registration is only open while the system has no admin.
     */
    public static function hasAdmin(): bool
    {
        return static::where('role', 'admin')->exists();
    }

    /**
     * The employee record linked to this account, if any.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, "conversation_user")->withPivot("last_read_at");
    }

    public function unreadChatCount(): int
    {
        return $this->conversations()
            ->with(["participants:id", "messages:id,conversation_id,user_id,created_at"])
            ->get()
            ->sum(fn (Conversation $c) => $c->unreadCountFor($this));
    }

    /**
     * Check if this user is the only active admin left.
     */
    public function isLastActiveAdmin(): bool
    {
        if (!$this->isAdmin() || !$this->is_active) {
            return false;
        }

        return static::where('role', 'admin')->where('is_active', true)
            ->whereKeyNot($this->id)
            ->doesntExist();
    }
}
