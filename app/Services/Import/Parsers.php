<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

class Parsers
{
    private const BRANDS = [
        'samsung' => 'Samsung', 'oppo' => 'OPPO', 'motorola' => 'Motorola', 'moto' => 'Motorola', 'iphone' => 'Apple',
        'apple' => 'Apple', 'xiaomi' => 'Xiaomi', 'redmi' => 'Xiaomi', 'huawei' => 'Huawei', 'nokia' => 'Nokia',
        'lenovo' => 'Lenovo', 'dell' => 'Dell', 'hp' => 'HP', 'hpe' => 'HPE', 'synology' => 'Synology', 'qnap' => 'QNAP',
    ];

    public static function norm(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $value)));
    }

    public static function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})/', $value, $m)) {
            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return checkdate((int) $m[2], (int) $m[1], (int) $year) ? sprintf('%s-%02d-%02d', $year, $m[2], $m[1]) : null;
        }

        return null;
    }

    public static function luhn(string $imei): bool
    {
        if (! preg_match('/^\d{15}$/', $imei)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $d = (int) $imei[14 - $i];
            if ($i % 2) {
                $d *= 2;
                $d = $d > 9 ? $d - 9 : $d;
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }

    public static function phone(?string $text): ?array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        $isNew = (bool) preg_match('/\bnov[oa]s?\b/iu', $text);
        $date = self::date($text);
        $clean = preg_replace(['/\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}/', '/\bnov[oa]s?\b/iu', '/\s+/'], ['', '', ' '], $text);
        $clean = trim($clean, " -–\t");
        $words = explode(' ', $clean);
        $first = Str::lower($words[0]);
        $brand = self::BRANDS[$first] ?? Str::ucfirst(Str::lower($words[0]));
        $rest = isset(self::BRANDS[$first]) && ! in_array($first, ['iphone', 'moto'], true) ? array_slice($words, 1) : $words;
        $rest = array_map(fn ($w) => preg_match('/\d/', $w) || mb_strlen($w) <= 3 ? Str::upper($w) : Str::ucfirst(Str::lower($w)), $rest);
        $model = implode(' ', $rest) ?: $clean;

        if ($brand === 'Samsung' && preg_match('/^[AMSZ]\d/i', $model)) {
            $model = 'Galaxy '.$model;
        }
        if ($first === 'iphone') {
            $model = 'iPhone '.implode(' ', array_slice($words, 1));
        }

        return ['brand' => $brand, 'model' => trim($model), 'new' => $isNew, 'date' => $date];
    }

    public static function lineNotes(?string $text): array
    {
        $raw = trim((string) $text);
        $out = ['raw' => $raw];
        if ($raw === '') {
            return $out;
        }

        $date = self::date($raw);
        $accessories = [];
        if (preg_match('/\bcapas?\b/iu', $raw)) {
            $accessories[] = 'Capa';
        }
        if (preg_match('/p[aeé]l[ií]culas?/iu', $raw)) {
            $accessories[] = 'Película';
        }
        if (preg_match('/portad[oa]s?\s+(?:da|de|do|pela|pelo)?\s*(.+?)(?:\s*[-–]\s*\d|\s*$)/iu', $raw, $m)) {
            $out['portado'] = trim($m[1], " -–");
            $out['ativacao'] = $date;
        }
        if ($accessories) {
            $out['acessorios'] = implode(', ', $accessories);
            $out['acc_new'] = (bool) preg_match('/\bnov[oa]s?\b/iu', $raw);
            if (! isset($out['portado'])) {
                $out['acc_date'] = $date;
            }
        }
        if (! isset($out['portado']) && ! $accessories) {
            if ($date && preg_replace('/[\d\/.\-\s]/', '', $raw) === '') {
                $out['ativacao'] = $date;
            } else {
                $out['unknown'] = true;
            }
        }

        return $out;
    }
}
