<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomField extends Model
{
    public const FORMATS = [
        'ANY' => null,
        'NUMERIC' => 'numeric',
        'ALPHA' => 'alpha',
        'EMAIL' => 'email',
        'DATE' => 'date',
        'URL' => 'url',
        'IP' => 'ip',
        'IPV4' => 'ipv4',
        'MAC' => 'mac_address',
        'BOOLEAN' => 'boolean',
    ];

    public const ELEMENTS = ['text', 'textarea', 'listbox', 'checkbox', 'radio'];

    protected $fillable = ['name', 'format', 'element', 'field_values', 'field_encrypted', 'show_in_listview', 'help_text'];

    protected $casts = ['field_encrypted' => 'boolean', 'show_in_listview' => 'boolean'];

    protected static function booted(): void
    {
        static::created(function (self $field) {
            if (! $field->db_column) {
                $field->db_column = '_snipeit_'.Str::slug(Str::ascii($field->name), '_').'_'.$field->id;
                $field->saveQuietly();
            }
        });
    }

    public function fieldsets(): BelongsToMany
    {
        return $this->belongsToMany(CustomFieldset::class)->withPivot(['order', 'required']);
    }

    public function options(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->field_values))));
    }

    public function rules(bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable'];

        if ($this->element === 'checkbox') {
            return $rules;
        }

        if (str_starts_with($this->format, 'regex:')) {
            $rules[] = $this->format;
        } elseif ($rule = self::FORMATS[strtoupper($this->format)] ?? null) {
            $rules[] = $rule;
        } else {
            $rules[] = 'string';
            $rules[] = 'max:65535';
        }

        if (in_array($this->element, ['listbox', 'radio'], true) && $this->options()) {
            $rules[] = Rule::in($this->options());
        }

        return $rules;
    }
}
