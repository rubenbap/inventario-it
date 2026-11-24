<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['name', 'company_id', 'notes'];

    public function companyScopeAllowsNull(): bool
    {
        return true;
    }
}
