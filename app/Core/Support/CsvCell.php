<?php

namespace App\Core\Support;

/**
 * Makes a value safe to put in a CSV cell that a spreadsheet will open.
 *
 * Excel, LibreOffice and Google Sheets run a cell starting with = + - @ (or a
 * tab or carriage return before one) as a formula, so tenant-typed text such as
 * a task title or a note could run one on whoever opens the export. Such text
 * gets a leading apostrophe, which spreadsheets show as plain text.
 * Numbers, booleans and null are left alone, so -5 stays a number.
 */
class CsvCell
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::FORMULA_STARTS, true) ? "'".$value : $value;
    }

    /** @param  array<int, mixed>  $row */
    public static function row(array $row): array
    {
        return array_map(self::safe(...), $row);
    }
}
