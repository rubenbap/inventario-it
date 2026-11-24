<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ResourceController extends ApiController
{
    protected array $with = [];

    protected array $withCount = [];

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'created_at', 'updated_at'];

    protected array $blockingRelations = [];

    protected string $label = 'Registo';

    protected bool $companyOptional = false;

    abstract protected function modelClass(): string;

    abstract protected function rules(Request $request, ?Model $model): array;

    abstract protected function transform(Model $model): array;

    protected function query(Request $request): Builder
    {
        return ($this->modelClass())::query()->with($this->with)->withCount($this->withCount);
    }

    protected function filter(Builder $query, Request $request): void
    {
    }

    protected function fill(Model $model, array $data, Request $request): void
    {
        if ($this->companyOptional && (array_key_exists('company_id', $data) || ! $model->exists)) {
            $user = $request->user();
            $data['company_id'] = $this->resolveCompanyId($data['company_id'] ?? null, $user, ! $user->isAdmin());
        }
        $model->fill($data);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);
        $this->filter($query, $request);

        return $this->listing($query, $request, fn ($m) => $this->transform($m), $this->searchable, $this->sortable, 'name');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->transform($this->query($request)->findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request, null));
        $class = $this->modelClass();
        $model = new $class;
        $this->fill($model, $data, $request);
        $model->save();
        ActivityLogger::log('create', $model);

        return ApiResponse::success($this->transform($this->query($request)->findOrFail($model->getKey())), $this->label.' criado com sucesso.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $model = $this->query($request)->findOrFail($id);
        $data = $request->validate($this->rules($request, $model));
        $this->fill($model, $data, $request);
        $model->save();

        if ($changes = ActivityLogger::diff($model)) {
            ActivityLogger::log('update', $model, null, null, $changes);
        }

        return ApiResponse::success($this->transform($this->query($request)->findOrFail($id)), $this->label.' atualizado com sucesso.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $model = $this->query($request)->findOrFail($id);

        foreach ($this->blockingRelations as $relation) {
            if ($model->{$relation}()->exists()) {
                return ApiResponse::error('Não é possível apagar: '.mb_strtolower($this->label).' em uso ('.$relation.').');
            }
        }

        $model->delete();
        ActivityLogger::log('delete', $model);

        return ApiResponse::success(null, $this->label.' apagado com sucesso.');
    }
}
