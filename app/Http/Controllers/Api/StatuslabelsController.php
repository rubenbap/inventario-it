<?php

namespace App\Http\Controllers\Api;

use App\Http\Transformers\Transformers;
use App\Models\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatuslabelsController extends ResourceController
{
    protected array $withCount = ['assets'];
    protected array $blockingRelations = ['assets'];
    protected string $label = 'Estado';

    protected function modelClass(): string
    {
        return StatusLabel::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $s = $model ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191', Rule::unique('status_labels', 'name')->ignore($model?->getKey())],
            'type' => [$s, Rule::in(['deployable', 'pending', 'undeployable', 'archived'])],
            'color' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function fill(Model $model, array $data, Request $request): void
    {
        if (isset($data['type'])) {
            $data = [...$data, ...StatusLabel::flagsForType($data['type'])];
            unset($data['type']);
        }
        $model->fill($data);
    }

    protected function transform(Model $model): array
    {
        return Transformers::statuslabel($model);
    }
}
