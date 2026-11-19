<?php

namespace App\Exports;

use App\Support\Documents\ReportTable;
use App\Support\SystemSettings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One worksheet for a ReportTable: company and report title on top, the summary lines,
 * then the table with real numbers (formatted #,##0.00, so totals can be re-added in Excel).
 */
class ReportTableExport implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    private const MONEY_FORMAT = "#,##0.00";

    private int $headerRow = 0;

    /** @var array<int, string> row number => style */
    private array $styledRows = [];

    /** @var list<int> summary rows holding amounts */
    private array $summaryMoneyRows = [];

    public function __construct(private readonly ReportTable $table) {}

    public function title(): string
    {
        return mb_substr(str_replace(["\\", "/", "?", "*", "[", "]", ":"], "-", $this->table->title), 0, 31);
    }

    public function array(): array
    {
        $out = [
            [SystemSettings::legalName()],
            [$this->table->title],
            [$this->table->subtitle],
            [""], // blank separator: an empty row would be dropped (strict null comparison keeps "")
        ];

        foreach ($this->table->summary as [$label, $value, $type]) {
            $out[] = [$label, $type === "money" ? (float) $value : $value];
            if ($type === "money") {
                $this->summaryMoneyRows[] = count($out);
            }
        }
        if ($this->table->summary !== []) {
            $out[] = [""];
        }

        $out[] = array_column($this->table->columns, 0);
        $this->headerRow = count($out);

        foreach ($this->table->rows as $row) {
            $cells = [];
            foreach ($row["cells"] as $i => $value) {
                $cells[] = in_array($this->table->type($i), ["money", "number"], true) && $value !== null && $value !== ""
                    ? (float) $value
                    : $value;
            }
            $out[] = $cells;
            if ($row["style"] && $row["style"] !== "indent") {
                $this->styledRows[count($out)] = $row["style"];
            }
        }

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        $last = Coordinate::stringFromColumnIndex(max(1, count($this->table->columns)));
        $lastRow = max($this->headerRow, $sheet->getHighestRow());

        foreach ($this->table->columns as $i => [, $type]) {
            if (in_array($type, ["money", "number"], true)) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle("{$col}" . ($this->headerRow + 1) . ":{$col}{$lastRow}")
                    ->getNumberFormat()->setFormatCode($type === "money" ? self::MONEY_FORMAT : "#,##0.##");
                $sheet->getStyle("{$col}{$this->headerRow}")->getAlignment()->setHorizontal("right");
            }
        }
        foreach ($this->summaryMoneyRows as $r) {
            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        }
        $sheet->freezePane("A" . ($this->headerRow + 1));

        $styles = [
            1 => ["font" => ["bold" => true, "size" => 14]],
            2 => ["font" => ["bold" => true, "size" => 12]],
            3 => ["font" => ["italic" => true, "color" => ["rgb" => "6B7280"]]],
            "A{$this->headerRow}:{$last}{$this->headerRow}" => [
                "font" => ["bold" => true],
                "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["rgb" => "E8EAF6"]],
            ],
        ];
        foreach ($this->styledRows as $r => $style) {
            $styles["A{$r}:{$last}{$r}"] = match ($style) {
                "section" => ["font" => ["bold" => true, "color" => ["rgb" => "1A237E"]], "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["rgb" => "EEF1F8"]]],
                "total" => ["font" => ["bold" => true], "borders" => ["top" => ["borderStyle" => "medium"]]],
                default => ["font" => ["bold" => true], "borders" => ["top" => ["borderStyle" => "thin"]]],
            };
        }

        return $styles;
    }
}
