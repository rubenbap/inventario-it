<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\Transformers;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldsController extends ResourceController
{
    protected array $searchable = ['name', 'db_column'];
    protected string $label = 'Campo';

    protected function modelClass(): string
    {
        return CustomField::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191'],
            'element' => [$s, Rule::in(CustomField::ELEMENTS)],
            'format' => ['sometimes', 'string', 'max:191', function ($attr, $value, $fail) {
                if (! str_starts_with($value, 'regex:') && ! array_key_exists(strtoupper($value), CustomField::FORMATS)) {
                    $fail('Formato inválido.');
                }
            }],
            'field_values' => ['nullable', 'string'],
            'field_encrypted' => ['sometimes', 'boolean'],
            'show_in_listview' => ['sometimes', 'boolean'],
            'help_text' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function associate(Request $request, int $id): JsonResponse
    {
        $field = CustomField::findOrFail($id);
        $data = $request->validate([
            'fieldset_id' => ['required', 'integer', 'exists:custom_fieldsets,id'],
            'required' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $field->fieldsets()->syncWithoutDetaching([
            $data['fieldset_id'] => ['required' => $data['required'] ?? false, 'order' => $data['order'] ?? 0],
        ]);

        return ApiResponse::success(Transformers::field($field), 'Campo associado ao fieldset.');
    }

    public function disassociate(Request $request, int $id): JsonResponse
    {
        $field = CustomField::findOrFail($id);
        $data = $request->validate(['fieldset_id' => ['required', 'integer', 'exists:custom_fieldsets,id']]);
        $field->fieldsets()->detach($data['fieldset_id']);

        return ApiResponse::success(Transformers::field($field), 'Campo retirado do fieldset.');
    }

    protected function transform(Model $model): array
    {
        return Transformers::field($model);
    }
}
