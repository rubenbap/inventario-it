<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Morph;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Asset extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'asset_tag', 'serial', 'model_id', 'status_id', 'purchase_date', 'purchase_cost',
        'supplier_id', 'order_number', 'warranty_months', 'location_id', 'rtd_location_id', 'expected_checkin',
        'notes', 'image', 'custom_values',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expected_checkin' => 'date',
        'warranty_expires' => 'date',
        'last_checkout' => 'datetime',
        'last_checkin' => 'datetime',
        'purchase_cost' => 'decimal:2',
        'custom_values' => 'array',
        'warranty_months' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $asset) {
            $asset->warranty_expires = $asset->purchase_date && $asset->warranty_months
                ? $asset->purchase_date->copy()->addMonths($asset->warranty_months)
                : null;
        });
    }

    public const NO_BRAND = 'Sem marca';

    public function scopeIncomplete(Builder $q): void
    {
        $q->where(fn ($w) => $w
            ->where(fn ($s) => $s->whereNull($this->qualifyColumn('serial'))->orWhere($this->qualifyColumn('serial'), ''))
                ->whereNotIn('model_id', AssetModel::query()->select('models.id')->join('categories', 'categories.id', '=', 'models.category_id')->where('categories.kind', 'line'))
            ->orWhereIn('model_id', AssetModel::query()->select('models.id')->join('manufacturers', 'manufacturers.id', '=', 'models.manufacturer_id')->where('manufacturers.name', self::NO_BRAND))
            ->orWhereIn('model_id', AssetModel::query()->select('models.id')->join('categories', 'categories.id', '=', 'models.category_id')->whereRaw('lower(models.name) = lower(categories.name)')));
    }

    public function missingFields(): array
    {
        $this->loadMissing('model.manufacturer', 'model.category');
        $kind = $this->model?->category?->kind;
        if ($kind === 'line') {
            return [];
        }

        return array_values(array_filter([
            ! $this->model?->manufacturer || $this->model->manufacturer->name === self::NO_BRAND ? 'manufacturer' : null,
            ! $this->model || Str::lower($this->model->name) === Str::lower((string) $this->model->category?->name) ? 'model' : null,
            blank($this->serial) ? 'serial' : null,
        ]));
    }

    public function scopeNotArchived(Builder $q): void
    {
        $q->whereNotIn($this->qualifyColumn('status_id'), StatusLabel::idsOfType('archived'));
    }

    public function scopeOfStatusType(Builder $q, string ...$types): void
    {
        $q->whereIn($this->qualifyColumn('status_id'), StatusLabel::idsOfType(...$types));
    }

    public function scopeReady(Builder $q): void
    {
        $q->whereNull($this->qualifyColumn('assigned_id'))->ofStatusType('deployable');
    }

    public function scopeInCategory(Builder $q, int $categoryId): void
    {
        $q->whereIn('model_id', AssetModel::query()->select('id')->where('category_id', $categoryId));
    }

    public function scopeOfManufacturer(Builder $q, int $manufacturerId): void
    {
        $q->whereIn('model_id', AssetModel::query()->select('id')->where('manufacturer_id', $manufacturerId));
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id')->withTrashed();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusLabel::class, 'status_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rtdLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'rtd_location_id');
    }

    public function assigned(): MorphTo
    {
        return $this->morphTo('assigned')->withoutGlobalScopes();
    }

    public function assignedAssets(): MorphMany
    {
        return $this->morphMany(self::class, 'assigned');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'item');
    }

    public function isAssigned(): bool
    {
        return $this->assigned_id !== null;
    }

    public function isDeployable(): bool
    {
        return (bool) $this->status?->deployable;
    }

    public function statusMeta(): string
    {
        return $this->isAssigned() ? 'deployed' : ($this->status?->statusType() ?? 'undeployable');
    }

    public function warrantyExpires(): ?Carbon
    {
        return $this->warranty_expires;
    }

    public static function assignedTypeFor(string $checkoutToType): string
    {
        return Morph::class($checkoutToType) ?? User::class;
    }
}
