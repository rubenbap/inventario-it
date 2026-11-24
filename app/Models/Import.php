<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    protected $fillable = ['user_id', 'company_id', 'module', 'filename', 'ok', 'errors', 'created', 'updated', 'undone_at'];

    protected $casts = ['created' => 'array', 'updated' => 'array', 'undone_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function canUndo(): bool
    {
        return ! $this->undone_at && $this->created_at->gt(now()->subDay());
    }
}
