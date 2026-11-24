<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Accessory extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'category_id', 'manufacturer_id', 'supplier_id', 'location_id', 'model_number',
        'qty', 'min_amt', 'purchase_date', 'purchase_cost', 'order_number', 'notes', 'image',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'qty' => 'integer',
        'min_amt' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(AccessoryCheckout::class);
    }

    public function openCheckouts(): HasMany
    {
        return $this->checkouts()->whereNull('checked_in_at');
    }

    public function numRemaining(): int
    {
        return max(0, $this->qty - $this->openCheckouts()->count());
    }
}
