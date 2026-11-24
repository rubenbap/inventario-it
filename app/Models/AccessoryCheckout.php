<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryCheckout extends Model
{
    protected $fillable = ['accessory_id', 'assigned_to', 'note', 'created_by', 'checked_in_at'];

    protected $casts = ['checked_in_at' => 'datetime'];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }
}
