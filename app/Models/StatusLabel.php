<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusLabel extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'deployable', 'pending', 'archived', 'color', 'notes'];

    protected $casts = ['deployable' => 'boolean', 'pending' => 'boolean', 'archived' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => self::$typeMap = null);
        static::deleted(fn () => self::$typeMap = null);
    }

    private static ?array $typeMap = null;

    public static function idsOfType(string ...$types): array
    {
        self::$typeMap ??= static::query()->get(['id', 'deployable', 'pending', 'archived'])
            ->groupBy(fn (self $s) => $s->statusType())
            ->map(fn ($group) => $group->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->all();

        return array_merge(...array_map(fn ($t) => self::$typeMap[$t] ?? [], $types)) ?: [0];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'status_id');
    }

    public function statusType(): string
    {
        return match (true) {
            $this->deployable => 'deployable',
            $this->pending => 'pending',
            $this->archived => 'archived',
            default => 'undeployable',
        };
    }

    public static function flagsForType(string $type): array
    {
        return [
            'deployable' => $type === 'deployable',
            'pending' => $type === 'pending',
            'archived' => $type === 'archived',
        ];
    }
}
