<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class CustomFieldService
{
    public function fieldsFor(?AssetModel $model): Collection
    {
        return $model?->fieldset?->fields ?? collect();
    }

    public function rulesFor(?AssetModel $model, bool $partial = false): array
    {
        $rules = [];

        foreach ($this->fieldsFor($model) as $field) {
            $required = (bool) $field->pivot->required && ! $partial;
            $rules[$field->db_column] = $field->rules($required);
        }

        return $rules;
    }

    public function merge(?AssetModel $model, array $input, ?array $current = null): ?array
    {
        $values = $current ?? [];

        foreach ($this->fieldsFor($model) as $field) {
            if (! array_key_exists($field->db_column, $input)) {
                continue;
            }

            $value = $input[$field->db_column];

            if ($value === null || $value === '') {
                unset($values[$field->db_column]);
                continue;
            }

            if ($field->element === 'checkbox' && is_array($value)) {
                $value = implode(', ', $value);
            }

            $values[$field->db_column] = $field->field_encrypted
                ? Crypt::encryptString((string) $value)
                : (string) $value;
        }

        return $values ?: null;
    }

    public function present(Asset $asset, ?User $viewer, bool $reveal = false): array
    {
        $out = [];
        $values = $asset->custom_values ?? [];

        foreach ($this->fieldsFor($asset->model) as $field) {
            $raw = $values[$field->db_column] ?? null;
            $value = $raw;

            if ($raw !== null && $field->field_encrypted) {
                $value = $reveal && $viewer?->canViewEncrypted() ? $this->decrypt($raw) : '********';
            }

            $out[$field->name] = [
                'field' => $field->db_column,
                'value' => $value,
                'field_format' => $field->format,
                'element' => $field->element,
            ];
        }

        return $out;
    }

    public function secrets(Asset $asset): array
    {
        $values = $asset->custom_values ?? [];

        return $this->fieldsFor($asset->model)
            ->filter(fn (CustomField $f) => $f->field_encrypted)
            ->mapWithKeys(fn (CustomField $f) => [$f->name => isset($values[$f->db_column]) ? $this->decrypt($values[$f->db_column]) : null])
            ->all();
    }

    public function hasEncryptedValues(Asset $asset): bool
    {
        $values = $asset->custom_values ?? [];

        return $this->fieldsFor($asset->model)
            ->contains(fn (CustomField $f) => $f->field_encrypted && isset($values[$f->db_column]));
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
