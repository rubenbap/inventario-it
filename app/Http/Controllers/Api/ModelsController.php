<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\AssetModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModelsController extends ResourceController
{
    protected array $with = ['manufacturer', 'category', 'fieldset'];
    protected array $withCount = ['assets'];
    protected array $searchable = ['name', 'model_number', 'manufacturer.name', 'category.name'];
    protected array $blockingRelations = ['assets'];
    protected string $label = 'Modelo';

    protected function modelClass(): string
    {
        return AssetModel::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        foreach (['manufacturer_id', 'category_id', 'fieldset_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';
        $manufacturerId = $request->input('manufacturer_id', $model?->manufacturer_id);

        return [
            'name' => [$s, 'string', 'max:191',
                Rule::unique('models', 'name')->where('manufacturer_id', $manufacturerId)->ignore($model?->getKey())],
            'model_number' => ['nullable', 'string', 'max:191'],
            'manufacturer_id' => [$s, 'integer', 'exists:manufacturers,id'],
            'category_id' => [$s, 'integer', Rule::exists('categories', 'id')->where('category_type', 'asset')],
            'fieldset_id' => ['nullable', 'integer', 'exists:custom_fieldsets,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::model($model);
    }
}
