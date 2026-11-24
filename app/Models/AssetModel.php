<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetModel extends Model
{
    use SoftDeletes;

    protected $table = 'models';

    protected $fillable = ['name', 'model_number', 'manufacturer_id', 'category_id', 'fieldset_id', 'image', 'notes'];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function fieldset(): BelongsTo
    {
        return $this->belongsTo(CustomFieldset::class, 'fieldset_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'model_id');
    }
}
