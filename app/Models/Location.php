<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['name', 'company_id', 'parent_id', 'address', 'city', 'zip', 'country', 'notes'];

    public function companyScopeAllowsNull(): bool
    {
        return true;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
