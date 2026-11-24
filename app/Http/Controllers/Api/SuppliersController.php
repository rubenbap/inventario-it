<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuppliersController extends ResourceController
{
    protected array $searchable = ['name', 'contact', 'email'];
    protected string $label = 'Fornecedor';

    protected function modelClass(): string
    {
        return Supplier::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191', Rule::unique('suppliers', 'name')->ignore($model?->getKey())],
            'contact' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'url' => ['nullable', 'url', 'max:191'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::supplier($model);
    }
}
