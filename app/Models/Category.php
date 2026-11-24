<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    public const TYPES = ['asset', 'accessory'];

    public const KINDS = ['device', 'phone', 'printer', 'server', 'nas', 'line', 'accessory'];

    public const KIND_FIELDSET = ['printer' => 'Impressora', 'server' => 'Servidor / NAS', 'nas' => 'Servidor / NAS', 'line' => 'Linha móvel'];

    protected $fillable = ['name', 'category_type', 'kind', 'notes'];

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class);
    }
}
