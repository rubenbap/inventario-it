<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DepartmentsController extends ResourceController
{
    protected array $with = ['company'];
    protected bool $companyOptional = true;
    protected string $label = 'Departamento';

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => [$model ? 'sometimes' : 'required', 'string', 'max:191'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function transform(Model $model): array
    {
        return Transformers::department($model);
    }
}
