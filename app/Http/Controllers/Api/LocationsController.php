<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\Transformers;
use App\Models\Asset;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationsController extends ResourceController
{
    protected array $with = ['company', 'parent'];
    protected array $searchable = ['name', 'address', 'city'];
    protected bool $companyOptional = true;
    protected string $label = 'Localização';

    protected function modelClass(): string
    {
        return Location::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        if ($request->filled('company_id')) {
            $query->where(fn ($q) => $q->where('company_id', $request->integer('company_id'))->orWhereNull('company_id'));
        }
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
            'address' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $used = Asset::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('location_id', $id)
                ->orWhere('rtd_location_id', $id)
                ->orWhere(fn ($a) => $a->where('assigned_type', Location::class)->where('assigned_id', $id)))
            ->exists();

        if ($used) {
            return ApiResponse::error('Não é possível apagar: há assets nesta localização.');
        }

        return parent::destroy($request, $id);
    }

    protected function transform(Model $model): array
    {
        return Transformers::location($model);
    }
}
