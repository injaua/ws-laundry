<?php

namespace App\Support\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A4 documents (views extending layouts.document): the page itself, or its PDF when the
 * request asks for ?format=pdf. The PDF is the same Blade view rendered by dompdf with
 * resources/css/pdf.css, so screen, print and PDF never drift apart.
 */
final class DocumentResponse
{
    public static function make(string $view, array $data, string $filename): View|Response
    {
        if (request()->query("format") === "pdf") {
            return self::pdf($view, $data, $filename);
        }

        return view($view, $data + ["pdfAvailable" => true]);
    }

    public static function pdf(string $view, array $data, string $filename, string $orientation = "portrait"): Response
    {
        self::raiseMemoryLimit("512M"); // dompdf lays out the whole document in memory (long journals)
        $html = view($view, $data + ["pdf" => true])->render();

        return Pdf::loadHTML($html)
            ->setPaper("a4", $orientation)
            ->setOption(["defaultFont" => "DejaVu Sans", "isRemoteEnabled" => false, "isFontSubsettingEnabled" => true])
            ->download(self::filename($filename, "pdf"));
    }

    /** Safe download name: "Fatura FT-2026/0001" -> "fatura-ft-2026-0001.pdf". */
    public static function filename(string $name, string $extension): string
    {
        return (Str::slug(str_replace(["/", "\\"], "-", $name)) ?: "documento") . "." . $extension;
    }

    private static function raiseMemoryLimit(string $limit): void
    {
        $current = ini_get("memory_limit");
        if ($current !== "-1" && $current !== false && self::bytes($current) < self::bytes($limit)) {
            ini_set("memory_limit", $limit);
        }
    }

    private static function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            "G" => $number * 1024 ** 3,
            "M" => $number * 1024 ** 2,
            "K" => $number * 1024,
            default => $number,
        };
    }

    /** Absolute path of the PDF stylesheet (dompdf reads it from disk, inside its chroot). */
    public static function stylesheet(): string
    {
        return str_replace("\\", "/", resource_path("css/pdf.css"));
    }
}
