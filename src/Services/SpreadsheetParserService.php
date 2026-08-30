<?php

namespace Platform\Core\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parst Tabellenkalkulations-Dateien (xlsx/xls) zu strukturierten Rows.
 *
 * Schließt die Lücke in GetContextFileContentTool, das für xlsx bisher nur
 * "Binärdatei, kein Text" zurückgeben konnte (vgl. #849).
 */
class SpreadsheetParserService
{
    /**
     * Max. Zeilen pro Sheet im Tool-Ergebnis (Payload-Schutz für LLM-Kontext).
     */
    private const MAX_ROWS_PER_SHEET = 500;

    /**
     * @return array{sheets: array<int, array{name: string, rows: array<int, array<int, mixed>>, row_count: int, truncated: bool}>}
     */
    public function parse(string $binaryContent, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'xlsx');
        $tmpPath = tempnam(sys_get_temp_dir(), 'spreadsheet_') . '.' . $extension;

        try {
            file_put_contents($tmpPath, $binaryContent);

            $spreadsheet = IOFactory::load($tmpPath);
            $sheets = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $allRows = $sheet->toArray(null, true, true, false);
                $rowCount = count($allRows);
                $rows = array_slice($allRows, 0, self::MAX_ROWS_PER_SHEET);

                $sheets[] = [
                    'name' => $sheet->getTitle(),
                    'rows' => $rows,
                    'row_count' => $rowCount,
                    'truncated' => $rowCount > self::MAX_ROWS_PER_SHEET,
                ];
            }

            return ['sheets' => $sheets];
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Prüft, ob der MIME-Typ von diesem Service geparst werden kann.
     */
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
            'application/vnd.ms-excel', // xls
        ], true);
    }
}
