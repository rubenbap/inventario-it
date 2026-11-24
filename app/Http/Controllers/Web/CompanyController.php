<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $kinds = Asset::query()->toBase()
            ->join('models', 'models.id', '=', 'assets.model_id')
            ->join('categories', 'categories.id', '=', 'models.category_id')
            ->whereNull('assets.deleted_at')
            ->selectRaw('assets.company_id, categories.name as category, count(*) as total')
            ->groupBy('assets.company_id', 'categories.name')
            ->get()
            ->groupBy('company_id');

        return view('companies.index', [
            'companies' => Company::query()
                ->withCount(['assets' => fn ($q) => $q->withoutGlobalScopes(), 'members', 'accessories' => fn ($q) => $q->withoutGlobalScopes(), 'users'])
                ->orderBy('name')->get(),
            'kinds' => $kinds,
        ]);
    }

    public function create(): View
    {
        return view('companies.form', ['company' => new Company(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $company = DB::transaction(function () use ($data, $request) {
            $company = Company::create($data);
            if ($request->filled('location')) {
                Location::create(['name' => $request->string('location')->trim(), 'company_id' => $company->id]);
            }
            ActivityLogger::log('create', $company, null, "{$company->name} ({$company->code})");

            return $company;
        });

        return redirect()->route('companies.index')
            ->with('status', "{$company->name} criada. As tags vão ser {$company->code}-00001, {$company->code}-00002…");
    }

    public function edit(int $id): View
    {
        return view('companies.form', ['company' => Company::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $company->fill([...$this->validated($request, $company), 'active' => $request->boolean('active')])->save();

        if ($changes = ActivityLogger::diff($company)) {
            ActivityLogger::log('update', $company, null, null, $changes);
        }

        return redirect()->route('companies.index')->with('status', 'Empresa atualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $used = Asset::withoutGlobalScopes()->where('company_id', $id)->exists()
            || Accessory::withoutGlobalScopes()->where('company_id', $id)->exists()
            || User::query()->where('company_id', $id)->exists();

        if ($used) {
            return back()->withErrors(['company' => 'Só se pode apagar uma empresa sem equipamentos, linhas, acessórios nem pessoas. Desativa-a em vez disso.']);
        }

        DB::transaction(function () use ($company) {
            Location::withoutGlobalScopes()->where('company_id', $company->id)->delete();
            $company->users()->detach();
            $company->delete();
            ActivityLogger::log('delete', $company, null, $company->name);
        });

        return redirect()->route('companies.index')->with('status', "{$company->name} apagada.");
    }

    private function validated(Request $request, ?Company $company): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('companies', 'name')->ignore($company?->id)],
            'code' => ['required', 'regex:/^[A-Z0-9]{2,5}$/', Rule::unique('companies', 'code')->ignore($company?->id)],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ], [
            'code.regex' => 'O código tem de ter 2 a 5 letras ou números.',
            'code.unique' => 'Esse código já está a ser usado.',
            'name.unique' => 'Já existe uma empresa com esse nome.',
        ]);
    }
}
