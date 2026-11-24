<?php

namespace App\Services\Import;

use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class TabularReader
{
    public function fromFile(string $path, string $originalName): array
    {
        if (preg_match('/\.xlsx$/i', $originalName)) {
            $reader = new XlsxReader;
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y') : trim((string) $v), $row->toArray());
                }
                break;
            }
            $reader->close();

            return $this->split($rows);
        }

        $text = (string) file_get_contents($path);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return $this->fromText($text);
    }

    public function fromText(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $text));
        $first = strtok($text, "\n") ?: '';
        $delimiter = collect(["\t", ';', ','])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $this->split($rows);
    }

    private function split(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => $v !== '')) > 0));

        return ['headers' => $rows[0] ?? [], 'rows' => array_slice($rows, 1)];
    }
}
