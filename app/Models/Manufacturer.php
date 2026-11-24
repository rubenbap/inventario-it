<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manufacturer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'url', 'support_email', 'support_phone'];

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class);
    }
}
