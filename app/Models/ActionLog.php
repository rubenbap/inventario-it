<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionLog extends Model
{
    protected $fillable = [
        'created_by', 'action_type', 'item_type', 'item_id', 'target_type', 'target_id', 'company_id', 'note', 'log_meta',
    ];

    protected $casts = ['log_meta' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function item(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function target(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
