<?php

namespace App\Support\Documents;

/**
 * A tabular report built once and delivered as Excel or PDF (ReportDownload).
 *
 * Columns are [label, type] with type "text", "money", "number" or "date". Each row is a
 * list of cell values (money as numbers, never pre-formatted strings) plus an optional
 * style: "section" (a heading spanning the table), "subtotal", "total" or "indent".
 * Summary lines ([label, value]) are shown above the table (opening balance, VAT due…).
 */
final class ReportTable
{
    /** @var list<array{cells: array, style: ?string}> */
    public array $rows = [];

    /** @var list<array{0: string, 1: mixed, 2?: string}> */
    public array $summary = [];

    /**
     * @param  list<array{0: string, 1: string}>  $columns
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly array $columns,
        public readonly string $filename,
    ) {}

    public function row(array $cells, ?string $style = null): self
    {
        $this->rows[] = ["cells" => array_values($cells), "style" => $style];

        return $this;
    }

    public function section(string $label): self
    {
        return $this->row([$label], "section");
    }

    /** A summary line; $type "money" (default) or "text". */
    public function summary(string $label, mixed $value, string $type = "money"): self
    {
        $this->summary[] = [$label, $value, $type];

        return $this;
    }

    public function type(int $column): string
    {
        return $this->columns[$column][1] ?? "text";
    }

    /** Landscape when the table is too wide for a portrait A4 page. */
    public function orientation(): string
    {
        return count($this->columns) > 6 ? "landscape" : "portrait";
    }
}
