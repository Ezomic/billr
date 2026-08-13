<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|null>>  $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Excel reads a bare UTF-8 CSV as the local codepage, which mangles
            // client names and currency symbols. The BOM makes it behave.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function filename(string $prefix): string
    {
        return $prefix.'-'.Date::now()->format('Y-m-d').'.csv';
    }

    public function money(?int $cents): string
    {
        return number_format(($cents ?? 0) / 100, 2, '.', '');
    }
}
