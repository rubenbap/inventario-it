<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldset extends Model
{
    protected $fillable = ['name'];

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class)
            ->withPivot(['order', 'required'])
            ->orderByPivot('order');
    }

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'fieldset_id');
    }
}
