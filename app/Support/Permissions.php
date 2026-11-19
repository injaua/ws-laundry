<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Who may do what: every ability of the application and the roles that have it, in one place.
 *
 * Each ability is a Gate, so routes use `->middleware("can:manage-catalog")`, views
 * `@can("manage-catalog")` and code `$user->can("manage-catalog")`. To give a role a new
 * power, change the list here rather than the routes or the views.
 */
final class Permissions
{
    public const ROLES = ["admin", "owner", "cashier", "accountant", "courier"];

    /** @var array<string, string[]> ability => roles */
    public const ABILITIES = [
        // Counter: orders, customers, payments, tills, after-sales, piece tracking, production, logistics
        "work-counter" => ["admin", "owner", "cashier"],
        // Change or delete orders, customers and payments already recorded; the orders trash
        "manage-orders" => ["admin", "owner"],
        // Consult orders, customers, invoices, receipts, credit notes and tills (read-only for the accountant)
        "view-records" => ["admin", "owner", "cashier", "accountant"],
        // Management indicators and reports
        "view-reports" => ["admin", "owner", "accountant"],
        // Finance: expenses, accounting, receivables, wallets, treasury, business invoices, payroll, suppliers
        "manage-finance" => ["admin", "owner", "accountant"],
        // Store management decisions: targets, activity log, prices, promotions, delivery zones,
        // resources (equipment, stock, purchases), data protection, period reopening, production report
        "manage-store" => ["admin", "owner"],
        // Users of the store (the owner: cashiers they created; checked again in UserPolicy)
        "manage-users" => ["admin", "owner"],
        // Services and packages of the store
        "manage-catalog" => ["admin", "owner"],
        // Employees, attendance, leave, vacations, absence justifications
        "manage-hr" => ["admin", "owner"],
        // Settings of the store: business settings, WhatsApp, notifications, payments and mobile money
        "configure-store" => ["admin", "owner"],
        // Mobile money: consult transactions / request payments and refunds
        "view-mobile-payments" => ["admin", "owner", "cashier", "courier", "accountant"],
        "charge-mobile-payments" => ["admin", "owner", "cashier", "courier"],
        // The courier's route and the actions on the road
        "drive-routes" => ["admin", "owner", "cashier", "courier"],
        // Internal chat
        "use-chat" => ["admin", "owner", "cashier", "accountant"],
        // WhatsApp inbox with customers
        "answer-customers" => ["cashier"],
        // Own payslips and attendance
        "hr-self-service" => ["cashier"],
        // Company-wide: stores, security, system settings, languages, currencies, public website,
        // sending a store back to the company configuration, user activation
        "administer-company" => ["admin"],
    ];

    /** @return string[] roles that have the ability */
    public static function roles(string $ability): array
    {
        return self::ABILITIES[$ability] ?? throw new InvalidArgumentException("Unknown ability {$ability}");
    }

    public static function allows(?User $user, string $ability): bool
    {
        return $user !== null && $user->hasRole(self::roles($ability));
    }

    /** Registers every ability as a Gate (AppServiceProvider). */
    public static function register(): void
    {
        foreach (array_keys(self::ABILITIES) as $ability) {
            Gate::define($ability, fn (User $user) => self::allows($user, $ability));
        }
    }
}
