<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompaniesController extends ResourceController
{
    protected array $withCount = ['assets'];
    protected array $searchable = ['name', 'code', 'tax_id'];
    protected array $blockingRelations = ['assets'];
    protected string $label = 'Empresa';

    protected function modelClass(): string
    {
        return Company::class;
    }

    protected function query(Request $request): Builder
    {
        $query = parent::query($request);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $query->whereIn('id', $user->companyIds());
        }

        return $query;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191', Rule::unique('companies', 'name')->ignore($model?->getKey())],
            'code' => [$s, 'string', 'regex:/^[A-Z0-9]{2,5}$/', Rule::unique('companies', 'code')->ignore($model?->getKey())],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::company($model);
    }
}
