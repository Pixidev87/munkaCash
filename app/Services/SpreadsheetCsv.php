<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetCsv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<int|string|null>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($header, $rows): void {
                $output = fopen('php://output', 'wb');

                if ($output === false) {
                    throw new \RuntimeException('Unable to open the CSV response stream.');
                }

                try {
                    fwrite($output, "\xEF\xBB\xBF");
                    fputcsv($output, $header, ';', '"', '', "\r\n");

                    foreach ($rows as $row) {
                        $values = array_map(static fn (int|string|null $value): int|string|null => is_string($value)
                            ? self::spreadsheetText($value)
                            : $value, $row);
                        fputcsv($output, $values, ';', '"', '', "\r\n");
                    }
                } finally {
                    fclose($output);
                }
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private static function spreadsheetText(?string $value): ?string
    {
        if ($value !== null && preg_match('/\A(?:\p{Cc}|[\p{Z}\p{Cf}\s]*[=+@\-＝＋＠－])/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
