<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;

/**
 * A read-only question the AI may ask the system. The model only sees the tools the user's
 * role allows (ToolRegistry), and every run checks the ability again; queries run in the
 * user's store (global store scope) and through the same services as the pages.
 */
abstract class AiTool
{
    /** Rows a list returns at most. */
    protected const LIMIT = 25;

    abstract public function name(): string;

    abstract public function description(): string;

    /** Ability (App\Support\Permissions) required, or null for every user. */
    abstract public function ability(): ?string;

    /** @return array<string, mixed> JSON schema properties */
    protected function properties(): array
    {
        return [];
    }

    /** @return string[] */
    protected function required(): array
    {
        return [];
    }

    /** @return array<string, mixed> JSON result */
    abstract public function handle(User $user, array $input, ToolContext $context): array;

    public function allows(User $user): bool
    {
        return $this->ability() === null || Permissions::allows($user, $this->ability());
    }

    /** @return array{name: string, description: string, parameters: array<string, mixed>} */
    public function spec(): array
    {
        $parameters = ["type" => "object", "properties" => (object) $this->properties()];
        if ($this->required()) {
            $parameters["required"] = $this->required();
        }

        return ["name" => $this->name(), "description" => $this->description(), "parameters" => $parameters];
    }

    /** @return array<string, mixed> the from / to properties of a period */
    protected static function periodProperties(): array
    {
        return [
            "from" => ["type" => "string", "description" => "Start date YYYY-MM-DD (default: first day of this month)"],
            "to" => ["type" => "string", "description" => "End date YYYY-MM-DD (default: today)"],
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} a valid period of at most 366 days */
    protected static function period(array $input, ?CarbonImmutable $defaultFrom = null): array
    {
        $parse = function ($value): ?CarbonImmutable {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }
            [$y, $m, $d] = array_map("intval", explode("-", $value));

            return checkdate($m, $d, $y) ? CarbonImmutable::create($y, $m, $d) : null;
        };

        $to = $parse($input["to"] ?? null) ?? CarbonImmutable::today();
        $from = $parse($input["from"] ?? null) ?? $defaultFrom ?? $to->startOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    protected static function text(array $input, string $key, int $max = 100): string
    {
        return is_string($input[$key] ?? null) ? mb_substr(trim($input[$key]), 0, $max) : "";
    }

    protected static function limit(array $input): int
    {
        $limit = is_numeric($input["limit"] ?? null) ? (int) $input["limit"] : 10;

        return max(1, min(self::LIMIT, $limit));
    }

    protected static function limitProperty(): array
    {
        return ["limit" => ["type" => "integer", "description" => "Maximum rows (1-" . self::LIMIT . ", default 10)"]];
    }

    protected static function date(?\DateTimeInterface $date): ?string
    {
        return $date?->format("Y-m-d");
    }
}
