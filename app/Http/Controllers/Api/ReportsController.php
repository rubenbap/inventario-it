<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\Transformers;
use App\Models\Accessory;
use App\Models\ActionLog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use App\Support\Morph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends ApiController
{
    public function activity(Request $request): JsonResponse
    {
        $query = ActionLog::query()->with(['admin:id,first_name,last_name', 'item', 'target']);
        $viewer = $request->user();

        if (! $viewer->isAdmin()) {
            $query->whereIn('company_id', $viewer->companyIds());
        }
        if ($type = Morph::class($request->input('item_type'))) {
            $query->where('item_type', $type);
        }
        if ($type = Morph::class($request->input('target_type'))) {
            $query->where('target_type', $type);
        }
        foreach (['item_id', 'target_id', 'company_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->input('action_type'));
        }

        $limit = max(1, min((int) $request->input('limit', 50) ?: 50, config('inventario.api_max_limit', 500)));
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')
            ->skip(max(0, (int) $request->input('offset', 0)))->take($limit)->get();

        return ApiResponse::rows($total, $rows->map(fn ($l) => Transformers::activity($l))->all());
    }

    public function summary(Request $request): JsonResponse
    {
        $companies = Company::query()->orderBy('name')->when($request->filled('company_id'), fn ($q) => $q->whereKey((int) $request->input('company_id')))->get(['id', 'name', 'code']);
        $ids = $companies->pluck('id');
        $kinds = Asset::query()->notArchived()->whereIn('assets.company_id', $ids)
            ->join('models', 'models.id', '=', 'assets.model_id')->join('categories', 'categories.id', '=', 'models.category_id')
            ->selectRaw('assets.company_id, categories.kind, count(*) as n, count(assets.assigned_id) as assigned')
            ->groupBy('assets.company_id', 'categories.kind')->get()->groupBy('company_id');
        $incomplete = Asset::query()->notArchived()->incomplete()->whereIn('company_id', $ids)->selectRaw('company_id, count(*) as n')->groupBy('company_id')->pluck('n', 'company_id');
        $accessories = Accessory::query()->whereIn('company_id', $ids)->selectRaw('company_id, count(*) as n')->groupBy('company_id')->pluck('n', 'company_id');
        $users = User::query()->whereIn('company_id', $ids)->selectRaw('company_id, count(*) as n')->groupBy('company_id')->pluck('n', 'company_id');

        $rows = $companies->map(function (Company $c) use ($kinds, $incomplete, $accessories, $users) {
            $k = collect($kinds->get($c->id, []))->keyBy('kind');
            $sum = fn (array $list, string $col = 'n') => (int) collect($list)->sum(fn ($kind) => $k->get($kind)?->{$col} ?? 0);

            return [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
                'assets' => $sum(['device', 'phone']),
                'assets_assigned' => $sum(['device', 'phone'], 'assigned'),
                'printers' => $sum(['printer']),
                'servers' => $sum(['server', 'nas']),
                'lines' => $sum(['line']),
                'accessories' => (int) ($accessories[$c->id] ?? 0),
                'users' => (int) ($users[$c->id] ?? 0),
                'incomplete' => (int) ($incomplete[$c->id] ?? 0),
            ];
        })->values();

        $keys = ['assets', 'assets_assigned', 'printers', 'servers', 'lines', 'accessories', 'users', 'incomplete'];

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'totals' => collect($keys)->mapWithKeys(fn ($key) => [$key => $rows->sum($key)])->all(),
            'companies' => $rows->all(),
        ]);
    }
}
