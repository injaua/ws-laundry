<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    use BelongsToStore;

    public const SOURCES = ["manual", "payment", "expense", "credit_sale", "cash_session"];

    protected $fillable = ["entry_number", "entry_date", "description", "source", "source_id", "posted_by"];

    protected function casts(): array
    {
        return [
            "entry_date" => "date",
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, "posted_by");
    }

    public function isManual(): bool
    {
        return $this->source === "manual";
    }

    public static function number(int $id): string
    {
        return sprintf("LNC-%06d", $id);
    }

    /**
     * Create or replace the auto-generated balanced entry for a source record
     * (payment / expense) with arbitrary lines. Silently does nothing when the
     * lines are missing, unbalanced, or total zero.
     */
    public static function postLines(array $lines, string $date, string $description, string $source, int $sourceId): ?self
    {
        $lines = array_values(array_filter(
            $lines,
            fn ($line) => !empty($line["account_id"])
                && ((float) $line["debit"] > 0 || (float) $line["credit"] > 0)
        ));

        $debit = Money::sum(array_column($lines, "debit"));
        $credit = Money::sum(array_column($lines, "credit"));

        if (count($lines) < 2 || $debit <= 0 || !Money::equals($debit, $credit)) {
            return null;
        }

        return DB::transaction(function () use ($lines, $date, $description, $source, $sourceId) {
            $entry = static::firstOrNew(["source" => $source, "source_id" => $sourceId]);
            $entry->fill([
                "entry_date" => $date,
                "description" => $description,
                "posted_by" => auth()->id(),
            ]);
            if (!$entry->entry_number) {
                $entry->entry_number = "TMP-" . Str::upper(Str::random(12));
            }
            $entry->save();
            $entry->update(["entry_number" => static::number($entry->id)]);

            $entry->lines()->delete();
            foreach ($lines as $line) {
                $entry->lines()->create([
                    "account_id" => $line["account_id"],
                    "debit" => Money::round($line["debit"]),
                    "credit" => Money::round($line["credit"]),
                ]);
            }

            return $entry;
        });
    }

    /**
     * Create or replace the auto-generated balanced pair for a source record
     * (payment / expense). Silently does nothing when accounts or amount are missing.
     */
    public static function postPair(?int $debitAccountId, ?int $creditAccountId, float $amount, string $date, string $description, string $source, int $sourceId): ?self
    {
        if (!$debitAccountId || !$creditAccountId || $amount <= 0) {
            return null;
        }

        return static::postLines([
            ["account_id" => $debitAccountId, "debit" => $amount, "credit" => 0],
            ["account_id" => $creditAccountId, "debit" => 0, "credit" => $amount],
        ], $date, $description, $source, $sourceId);
    }

    public static function forget(string $source, int $sourceId): void
    {
        static::where("source", $source)->where("source_id", $sourceId)->get()
            ->each(fn (self $entry) => $entry->delete());
    }
}
