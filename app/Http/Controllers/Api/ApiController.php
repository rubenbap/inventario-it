<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class ApiController extends Controller
{
    protected function listing(
        Builder $query,
        Request $request,
        callable $transform,
        array $searchable = ['name'],
        array $sortable = ['id', 'name', 'created_at', 'updated_at'],
        string $defaultSort = 'created_at',
    ): JsonResponse {
        $search = trim((string) $request->input('search', ''));

        if ($search !== '' && $searchable) {
            $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $term = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $q) use ($searchable, $op, $term) {
                foreach ($searchable as $column) {
                    if (str_contains($column, '.')) {
                        $relation = substr($column, 0, strrpos($column, '.'));
                        $col = substr($column, strrpos($column, '.') + 1);
                        $q->orWhereHas($relation, fn (Builder $r) => $r->where($col, $op, $term));
                    } else {
                        $q->orWhere($q->getModel()->qualifyColumn($column), $op, $term);
                    }
                }
            });
        }

        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : $defaultSort;
        $order = strtolower((string) $request->input('order')) === 'asc' ? 'asc' : 'desc';
        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min($limit ?: 50, config('inventario.api_max_limit', 500)));
        $offset = max(0, (int) $request->input('offset', 0));

        $total = (clone $query)->count();
        $rows = $query->orderBy($query->getModel()->qualifyColumn($sort), $order)
            ->skip($offset)->take($limit)->get()
            ->map(fn ($m) => $transform($m))->values()->all();

        return ApiResponse::rows($total, $rows);
    }

    protected function resolveCompanyId(?int $companyId, User $user, bool $required = true): ?int
    {
        if ($companyId !== null) {
            if (! $user->hasCompany($companyId)) {
                throw ValidationException::withMessages(['company_id' => ['Não tens acesso a esta empresa.']]);
            }

            return $companyId;
        }

        $ids = $user->companyIds();
        if (count($ids) === 1) {
            return $ids[0];
        }
        if ($required) {
            throw ValidationException::withMessages(['company_id' => ['Indica a empresa (company_id).']]);
        }

        return null;
    }
}
