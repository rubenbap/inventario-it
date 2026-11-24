<?php

namespace App\Http\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Fmt
{
    public static function date(mixed $value): ?array
    {
        if (! $value) {
            return null;
        }
        $d = Carbon::parse($value);

        return ['date' => $d->format('Y-m-d'), 'formatted' => $d->format('d/m/Y')];
    }

    public static function datetime(mixed $value): ?array
    {
        if (! $value) {
            return null;
        }
        $d = Carbon::parse($value)->timezone(config('app.timezone'));

        return ['datetime' => $d->format('Y-m-d H:i:s'), 'formatted' => $d->format('d/m/Y H:i')];
    }

    public static function rel(?Model $model, string $nameAttr = 'name'): ?array
    {
        return $model ? ['id' => $model->getKey(), 'name' => e($model->getAttribute($nameAttr))] : null;
    }

    public static function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, ',', ' ');
    }

    public static function text(?string $value): ?string
    {
        return $value === null ? null : e($value);
    }
}
