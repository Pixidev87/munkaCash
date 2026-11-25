<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type InvoiceData array{customer_id: int, invoice_number: string, due_date: string, gross_amount: int}
 * @phpstan-type CsvIssue array{code: string, column: string|null, message: string}
 * @phpstan-type CsvRow array{line: int, data: InvoiceData|null, errors: list<CsvIssue>}
 * @phpstan-type StoredCsvRow array{line: int, data: InvoiceData|null, errors: list<CsvIssue|string>}
 */
class InvoiceCsvParser
{
    public const string HEADER = 'customer_id;invoice_number;due_date;currency;gross_amount';

    public const int MAX_BYTES_KIB = 128;

    public const int MAX_ROWS = 250;

    private const string FIELD_PATTERN = '(?:[^;"\r\n]*|"(?:[^"]|"")*")';

    /** @return list<CsvRow> */
    public function parse(string $contents): array
    {
        $contents = str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;

        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['csv' => 'UTF-8 kódolású CSV-fájlt tölts fel.']);
        }

        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        if (array_shift($lines) !== self::HEADER) {
            throw ValidationException::withMessages(['csv' => 'A fejléc eltér a sablontól. Használd a letölthető mintát.']);
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (count($rows) === self::MAX_ROWS) {
                throw ValidationException::withMessages(['csv' => 'Egy fájl legfeljebb '.self::MAX_ROWS.' számlát tartalmazhat.']);
            }

            $rows[] = $this->parseRow($line, $index + 2);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['csv' => 'A fájlban nincs számlasor.']);
        }

        return $rows;
    }

    /** @return CsvRow */
    private function parseRow(string $line, int $lineNumber): array
    {
        $field = self::FIELD_PATTERN;
        if (preg_match('/\A'.$field.'(?:;'.$field.'){4}\z/', $line) !== 1) {
            return [
                'line' => $lineNumber,
                'data' => null,
                'errors' => [[
                    'code' => 'row_structure',
                    'column' => null,
                    'message' => 'Pontosan öt, pontosvesszővel elválasztott mező szükséges.',
                ]],
            ];
        }

        [$customer, $number, $dueDate, $currency, $gross] = array_map(
            static fn (?string $value): string => trim($value ?? ''),
            str_getcsv($line, ';', '"', ''),
        );

        $validator = Validator::make([
            'customer_id' => $customer,
            'invoice_number' => $number,
            'due_date' => $dueDate,
            'currency' => $currency,
            'gross_amount' => $gross,
        ], [
            'customer_id' => ['required', 'regex:/\A[1-9][0-9]{0,17}\z/'],
            'invoice_number' => ['required', 'max:100'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'currency' => ['required', 'in:HUF'],
            'gross_amount' => ['required', 'regex:/\A[0-9]{1,12}\z/', 'integer', 'min:1', 'max:'.Invoice::MAX_GROSS_AMOUNT],
        ], [
            'customer_id.*' => 'add meg egy meglévő ügyfél azonosítóját.',
            'invoice_number.*' => 'add meg a számlaszámot, legfeljebb 100 karakterben.',
            'due_date.*' => 'érvényes dátumot adj meg ÉÉÉÉ-HH-NN formátumban.',
            'currency.*' => 'kizárólag HUF használható.',
            'gross_amount.*' => 'pozitív egész forintösszeget adj meg, tagolás nélkül.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $issues = [];
            foreach ($errors->keys() as $column) {
                $issues[] = [
                    'code' => 'invalid_value',
                    'column' => $column,
                    'message' => $errors->first($column),
                ];
            }

            return ['line' => $lineNumber, 'data' => null, 'errors' => $issues];
        }

        return [
            'line' => $lineNumber,
            'data' => [
                'customer_id' => (int) $customer,
                'invoice_number' => $number,
                'due_date' => $dueDate,
                'gross_amount' => (int) $gross,
            ],
            'errors' => [],
        ];
    }
}
