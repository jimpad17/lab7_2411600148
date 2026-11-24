<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lab 7 - shared CSV/PDF export helpers. Controllers only supply headers and
 * rows, so every report uses exactly the same formatting.
 */
class ExportService
{
    /** @param array<int, string> $headers */
    public function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($value) => is_string($value) ? $value : (string) $value,
                    array_values((array) $row)
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** @param array<int, string> $headers */
    public function pdf(string $filename, string $title, array $headers, iterable $rows)
    {
        $rows = $rows instanceof Collection ? $rows->all() : iterator_to_array($rows);

        return Pdf::loadView('exports.table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
