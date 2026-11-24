<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManufacturersController extends ResourceController
{
    protected array $withCount = ['models'];
    protected array $blockingRelations = ['models'];
    protected string $label = 'Marca';

    protected function modelClass(): string
    {
        return Manufacturer::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191', Rule::unique('manufacturers', 'name')->ignore($model?->getKey())],
            'url' => ['nullable', 'url', 'max:191'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'support_phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::manufacturer($model);
    }
}
