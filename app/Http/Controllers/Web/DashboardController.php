<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\ActionLog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\StatusLabel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $companyId = $request->session()->get('company_id');
        if ($companyId && ! $user->hasCompany($companyId)) {
            $companyId = null;
        }

        $assets = fn () => Asset::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->notArchived();

        $deployable = implode(',', StatusLabel::idsOfType('deployable'));
        $totals = (array) $assets()->toBase()->selectRaw(
            'count(*) as active, count(assigned_id) as assigned, '
            ."coalesce(sum(case when assigned_id is null and status_id in ({$deployable}) then 1 else 0 end), 0) as ready, "
            ."coalesce(sum(case when status_id not in ({$deployable}) then 1 else 0 end), 0) as broken"
        )->first();

        $byKind = Asset::query()->notArchived()->toBase()
            ->join('models', 'models.id', '=', 'assets.model_id')
            ->join('categories', 'categories.id', '=', 'models.category_id')
            ->selectRaw('assets.company_id, categories.kind, count(*) as total')
            ->groupBy('assets.company_id', 'categories.kind')
            ->get()->groupBy('company_id');
        $groups = ['assets' => ['device', 'phone'], 'printers' => ['printer'], 'servers' => ['server', 'nas'], 'lines' => ['line']];
        $byCompany = Company::query()->whereIn('id', $user->companyIds())->orderBy('name')->get(['id', 'name', 'code'])
            ->map(function (Company $c) use ($byKind, $groups) {
                $kinds = collect($byKind->get($c->id, []))->pluck('total', 'kind');
                $row = ['company' => $c];
                foreach ($groups as $key => $list) {
                    $row[$key] = (int) collect($list)->sum(fn ($k) => $kinds->get($k, 0));
                }
                $row['total'] = $row['assets'] + $row['printers'] + $row['servers'] + $row['lines'];

                return $row;
            })->sortByDesc('total')->values();

        return view('dashboard', [
            'byCompany' => $byCompany,
            'maxTotal' => max(1, (int) $byCompany->max('total')),
            'company' => $companyId ? Company::find($companyId) : null,
            'totals' => array_map('intval', $totals),
            'incomplete' => $assets()->incomplete()->count(),
            'overdue' => $assets()->with(['model:id,name', 'assigned'])
                ->whereNotNull('assigned_id')->whereDate('expected_checkin', '<', today())
                ->orderBy('expected_checkin')->limit(5)->get(),
            'warranty' => $assets()->with('model:id,name')
                ->whereBetween('warranty_expires', [today(), today()->addDays(30)])
                ->orderBy('warranty_expires')->limit(5)->get(),
            'lowStock' => Accessory::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->withCount('openCheckouts')
                ->whereNotNull('min_amt')
                ->whereRaw('qty - (select count(*) from accessory_checkouts where accessory_checkouts.accessory_id = accessories.id and accessory_checkouts.checked_in_at is null) < min_amt')
                ->orderBy('name')->limit(5)->get(),
            'activity' => ActionLog::query()->with(['admin:id,first_name,last_name', 'item', 'target'])
                ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('company_id', $user->companyIds()))
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->latest()->limit(8)->get(),
        ]);
    }
}
