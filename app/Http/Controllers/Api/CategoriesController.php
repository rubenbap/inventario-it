<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriesController extends ResourceController
{
    protected array $blockingRelations = ['models'];
    protected string $label = 'Categoria';

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        if ($request->filled('category_type')) {
            $query->where('category_type', $request->input('category_type'));
        }
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191'],
            'category_type' => [$s, Rule::in(Category::TYPES)],
            'kind' => ['sometimes', Rule::in(Category::KINDS)],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::category($model);
    }
}
