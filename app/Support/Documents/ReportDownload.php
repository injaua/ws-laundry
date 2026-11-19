<?php

namespace App\Support\Documents;

use App\Exports\ReportTableExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report pages export themselves: the same URL with ?format=xlsx or ?format=pdf (keeping
 * the page's filters) downloads the report instead of showing it.
 */
final class ReportDownload
{
    public const FORMATS = ["xlsx", "pdf"];

    /** The export format the request asks for, or null to show the page. */
    public static function requested(Request $request): ?string
    {
        $format = $request->query("format");

        return in_array($format, self::FORMATS, true) ? $format : null;
    }

    public static function make(ReportTable $table, string $format): Response
    {
        if ($format === "pdf") {
            return DocumentResponse::pdf("reports.table", ["table" => $table], $table->filename, $table->orientation());
        }

        return Excel::download(new ReportTableExport($table), DocumentResponse::filename($table->filename, "xlsx"));
    }
}
