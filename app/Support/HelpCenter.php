<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * In-app help: the user manual cut into topics, each shown only to the roles that can do
 * what it describes. Topics live in resources/help/{locale}/{key}.md (Portuguese is the
 * fallback). Inside a topic, a block wrapped in
 *     <!-- roles:admin,owner --> … <!-- /roles -->
 * is kept only for those roles, so a page shared by several roles never explains an
 * action the reader is not allowed to take.
 */
final class HelpCenter
{
    private const ALL = ["admin", "owner", "accountant", "cashier", "courier"];

    private const STAFF = ["admin", "owner", "accountant", "cashier"];

    private const COUNTER = ["admin", "owner", "cashier"];

    private const FINANCE = ["admin", "owner", "accountant"];

    private const MANAGERS = ["admin", "owner"];

    /** Section → topics (key => [icon, roles]), in reading order. */
    public const SECTIONS = [
        "start" => [
            "getting-started" => ["bi-rocket-takeoff", self::ALL],
            "dashboard" => ["bi-speedometer2", self::STAFF],
        ],
        "counter" => [
            "orders" => ["bi-basket", self::COUNTER],
            "customers" => ["bi-people", self::COUNTER],
            "payments-till" => ["bi-cash-coin", self::COUNTER],
            "after-sales" => ["bi-life-preserver", self::COUNTER],
            "whatsapp-inbox" => ["bi-whatsapp", ["cashier"]],
            "records-readonly" => ["bi-eye", ["accountant"]],
        ],
        "operations" => [
            "production" => ["bi-gear-wide-connected", self::COUNTER],
            "logistics" => ["bi-truck", self::COUNTER],
            "courier-route" => ["bi-signpost-split", ["admin", "owner", "cashier", "courier"]],
        ],
        "finance" => [
            "receivables-invoicing" => ["bi-receipt", self::FINANCE],
            "treasury" => ["bi-bank", self::FINANCE],
            "expenses" => ["bi-wallet2", self::FINANCE],
            "accounting" => ["bi-journal-text", self::FINANCE],
            "reports" => ["bi-graph-up", self::FINANCE],
            "payroll" => ["bi-cash-stack", self::FINANCE],
            "documents" => ["bi-folder2-open", self::FINANCE],
        ],
        "people" => [
            "hr" => ["bi-person-badge", self::MANAGERS],
            "my-hr" => ["bi-person-vcard", ["cashier"]],
        ],
        "resources" => [
            "resources" => ["bi-box-seam", self::MANAGERS],
        ],
        "admin" => [
            "stores" => ["bi-shop-window", ["admin"]],
            "users" => ["bi-person-gear", self::MANAGERS],
            "catalog" => ["bi-tags", self::MANAGERS],
            "settings" => ["bi-sliders", self::MANAGERS],
            "security" => ["bi-shield-lock", ["admin"]],
        ],
        "ai" => [
            "assistant" => ["bi-stars", self::ALL],
            "insights" => ["bi-lightbulb", self::MANAGERS],
        ],
        "communication" => [
            "internal-chat" => ["bi-chat-dots", self::STAFF],
        ],
        "account" => [
            "account" => ["bi-person-circle", self::ALL],
        ],
    ];

    /**
     * Topics the user may read, grouped by section.
     *
     * @return array<string, array<string, array{key: string, icon: string, title: string}>>
     */
    public static function sectionsFor(User $user): array
    {
        $sections = [];
        foreach (self::SECTIONS as $section => $topics) {
            foreach ($topics as $key => [$icon, $roles]) {
                if (in_array($user->role, $roles, true)) {
                    $sections[$section][$key] = ["key" => $key, "icon" => $icon, "title" => __("app.help_t_" . str_replace("-", "_", $key))];
                }
            }
        }

        return $sections;
    }

    public static function allows(User $user, string $key): bool
    {
        foreach (self::SECTIONS as $topics) {
            if (isset($topics[$key])) {
                return in_array($user->role, $topics[$key][1], true);
            }
        }

        return false;
    }

    /** @return array{key: string, icon: string, title: string}|null */
    public static function topic(string $key): ?array
    {
        foreach (self::SECTIONS as $topics) {
            if (isset($topics[$key])) {
                return ["key" => $key, "icon" => $topics[$key][0], "title" => __("app.help_t_" . str_replace("-", "_", $key))];
            }
        }

        return null;
    }

    /** HTML of a topic, or of the role summary ("role-{role}"), filtered for the user's role. */
    public static function render(string $key, User $user): string
    {
        $markdown = self::source($key);
        if ($markdown === null) {
            return "";
        }

        $markdown = preg_replace_callback(
            '/<!--\s*roles:([a-z,\s]+)-->(.*?)<!--\s*\/roles\s*-->/s',
            fn ($m) => in_array($user->role, array_map("trim", explode(",", $m[1])), true) ? $m[2] : "",
            $markdown,
        );

        return Str::markdown($markdown, ["html_input" => "strip", "allow_unsafe_links" => false], [new TableExtension]);
    }

    public static function path(string $key, string $locale): string
    {
        return resource_path("help/{$locale}/{$key}.md");
    }

    private static function source(string $key): ?string
    {
        if (!preg_match('/^[a-z-]+$/', $key)) {
            return null;
        }
        foreach ([app()->getLocale(), "pt"] as $locale) {
            if (is_file($file = self::path($key, $locale))) {
                return file_get_contents($file);
            }
        }

        return null;
    }
}
