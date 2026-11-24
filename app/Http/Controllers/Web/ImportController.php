<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Import;
use App\Services\Import\Exporter;
use App\Services\Import\Importer;
use App\Services\Import\TabularReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly Importer $importer,
        private readonly Exporter $exporter,
        private readonly TabularReader $reader,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('import.index', [
            'modules' => Importer::MODULES,
            'exportModules' => Exporter::MODULES,
            'companies' => Company::query()->whereIn('id', $user->companyIds())->where('active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'activeCompanyId' => session('company_id'),
            'imports' => Import::query()->with(['user:id,first_name,last_name', 'company:id,code'])
                ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('company_id', $user->companyIds()))
                ->latest()->limit(20)->get(),
        ]);
    }

    public function upload(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', Rule::in(array_keys(Importer::MODULES))],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'file' => ['nullable', 'required_without:text', 'file', 'max:10240', 'mimes:csv,txt,tsv,xlsx'],
            'text' => ['nullable', 'required_without:file', 'string', 'max:2000000'],
        ]);
        abort_unless($request->user()->hasCompany((int) $data['company_id']), 403);

        $file = $request->file('file');
        $parsed = $file ? $this->reader->fromFile($file->getRealPath(), $file->getClientOriginalName()) : $this->reader->fromText($data['text']);
        $best = Importer::detectModule($parsed['headers']);
        if ($best && $best !== $data['module']) {
            $data['module'] = $best;
            session()->flash('status', $best === 'snipeit'
                ? 'Ficheiro do Snipe-IT detetado: cada linha vai para a secção certa pela categoria.'
                : 'Pelas colunas, isto parece '.Importer::MODULES[$best]['label'].': mudei para aí.');
        }
        if (! $parsed['rows']) {
            return back()->withInput()->withErrors(['file' => 'Não encontrei linhas de dados por baixo do cabeçalho.']);
        }

        $token = (string) Str::uuid();
        Cache::put("import:{$token}", [
            'user' => $request->user()->id, 'module' => $data['module'], 'company_id' => (int) $data['company_id'],
            'filename' => $file?->getClientOriginalName() ?? 'colado', 'headers' => $parsed['headers'], 'rows' => $parsed['rows'],
        ], now()->addHours(2));

        return view('import.map', [
            'token' => $token,
            'module' => $data['module'],
            'fields' => Importer::MODULES[$data['module']]['fields'],
            'headers' => $parsed['headers'],
            'sample' => $parsed['rows'][0] ?? [],
            'map' => $this->importer->autoMap($data['module'], $parsed['headers']),
            'count' => count($parsed['rows']),
        ]);
    }

    public function preview(Request $request): View
    {
        [$job, $map, $autoCreate] = $this->job($request);
        $result = $this->importer->run($job['module'], $job['rows'], $map, Company::findOrFail($job['company_id']), $autoCreate, $request->user(), false);

        return view('import.preview', [
            'token' => $request->input('token'), 'module' => $job['module'], 'map' => $map, 'autoCreate' => $autoCreate, 'result' => $result,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        [$job, $map, $autoCreate] = $this->job($request);
        $result = $this->importer->run($job['module'], $job['rows'], $map, Company::findOrFail($job['company_id']), $autoCreate, $request->user(), true, $job['filename']);
        Cache::forget('import:'.$request->input('token'));

        return redirect()->route('import.index')->with('status', "Importação concluída: {$result['ok']} linhas importadas".($result['errors'] ? ", {$result['errors']} com erro ficaram de fora." : '.'));
    }

    public function undo(Request $request, int $id): RedirectResponse
    {
        $import = Import::findOrFail($id);
        abort_unless($request->user()->isAdmin() || $import->user_id === $request->user()->id, 403);
        if (! $import->canUndo()) {
            return back()->withErrors(['import' => 'Esta importação já não pode ser anulada (só nas primeiras 24 horas).']);
        }
        $this->importer->undo($import);

        return back()->with('status', 'Importação anulada: tudo voltou ao estado anterior.');
    }

    public function export(Request $request, string $module): StreamedResponse
    {
        abort_unless(isset(Exporter::MODULES[$module]), 404);
        $companyId = session('company_id');

        return $this->exporter->download($module, $request->input('format') === 'xlsx' ? 'xlsx' : 'csv', $companyId ? (int) $companyId : null, $request->user(), $request->boolean('secrets'));
    }

    public function template(string $module): StreamedResponse
    {
        abort_unless(isset(Importer::MODULES[$module]), 404);

        return $this->exporter->template($module);
    }

    private function job(Request $request): array
    {
        $request->validate(['token' => ['required', 'uuid'], 'map' => ['array']]);
        $job = Cache::get('import:'.$request->input('token'));
        abort_if(! $job || $job['user'] !== $request->user()->id, 410, 'A sessão de importação expirou. Volta a carregar o ficheiro.');
        $allowed = array_keys(Importer::MODULES[$job['module']]['fields']);
        $map = array_map(fn ($v) => in_array($v, $allowed, true) ? $v : null, $request->input('map', []));

        return [$job, $map, $request->boolean('auto_create', true)];
    }
}
