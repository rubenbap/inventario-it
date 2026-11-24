<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\Transformers;
use App\Models\CustomFieldset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldsetsController extends ResourceController
{
    protected array $with = ['fields', 'models'];
    protected array $blockingRelations = ['models'];
    protected string $label = 'Fieldset';

    protected function modelClass(): string
    {
        return CustomFieldset::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => [$model ? 'sometimes' : 'required', 'string', 'max:191',
                Rule::unique('custom_fieldsets', 'name')->ignore($model?->getKey())],
        ];
    }

    public function fields(Request $request, int $id): JsonResponse
    {
        $set = CustomFieldset::with('fields')->findOrFail($id);

        return ApiResponse::rows($set->fields->count(), $set->fields->map(fn ($f) => Transformers::field($f))->all());
    }

    protected function transform(Model $model): array
    {
        return Transformers::fieldset($model);
    }
}
