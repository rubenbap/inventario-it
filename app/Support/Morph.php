<?php

namespace App\Support;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;

final class Morph
{
    public const MAP = [
        'asset' => Asset::class,
        'user' => User::class,
        'location' => Location::class,
        'accessory' => Accessory::class,
        'company' => Company::class,
        'model' => AssetModel::class,
    ];

    public const CHECKOUT_TARGETS = ['user', 'location', 'asset'];

    public static function class(?string $short): ?string
    {
        return self::MAP[$short] ?? null;
    }

    public static function short(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        return array_search($class, self::MAP, true) ?: strtolower(class_basename($class));
    }
}
