<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'tax_id', 'logo', 'active', 'notes'];

    protected $casts = ['active' => 'boolean'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function accessories(): HasMany
    {
        return $this->hasMany(Accessory::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public static function codeFor(string $name): string
    {
        $words = array_values(array_filter(explode(' ', preg_replace('/[^A-Z0-9 ]/', ' ', Str::upper(Str::ascii($name))))));
        $base = substr(count($words) > 1 ? implode('', array_map(fn ($w) => $w[0], $words)) : ($words[0] ?? 'EMP'), 0, 4);
        $base = strlen($base) < 2 ? substr($words[0] ?? 'EMP', 0, 3) : $base;
        $code = $base;
        for ($i = 2; static::withTrashed()->where('code', $code)->exists(); $i++) {
            $code = substr($base, 0, 3).$i;
        }

        return $code;
    }

    public function nextAssetTag(): string
    {
        return DB::transaction(function () {
            $company = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $digits = config('inventario.asset_tag_digits', 5);

            do {
                $company->asset_tag_counter++;
                $tag = $company->code.'-'.str_pad((string) $company->asset_tag_counter, $digits, '0', STR_PAD_LEFT);
            } while (Asset::withoutGlobalScopes()->where('asset_tag', $tag)->exists());

            $company->save();
            $this->asset_tag_counter = $company->asset_tag_counter;

            return $tag;
        });
    }
}
