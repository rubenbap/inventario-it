@extends('layouts.app')
@section('title', 'Importar / Exportar')

@section('content')
    <div>
        <h1>Importar / Exportar</h1>
        <div class="muted">CSV ou Excel, por empresa. Também podes colar diretamente as linhas copiadas do Excel.</div>
    </div>

    @if($errors->any())<div class="alert err" role="alert">{{ $errors->first() }}</div>@endif

    @if(auth()->user()->canWrite())
        <form class="card" method="post" action="{{ route('import.upload') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px">
            @csrf
            <h2>Importar</h2>
            <div class="grid">
                <label class="field">O que vais importar
                    <select name="module" required>
                        @foreach($modules as $key => $m)<option value="{{ $key }}" @selected(old('module', 'lines') === $key)>{{ $m['label'] }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Empresa por defeito
                    <select name="company_id" required>
                        @foreach($companies as $c)<option value="{{ $c->id }}" @selected((int) old('company_id', $activeCompanyId) === $c->id)>{{ $c->name }} ({{ $c->code }})</option>@endforeach
                    </select>
                </label>
                <label class="field">Ficheiro (.xlsx, .csv)<input type="file" name="file" accept=".xlsx,.csv,.txt,.tsv"></label>
            </div>
            <label class="field">…ou cola aqui as linhas copiadas do Excel, com o cabeçalho
                <textarea name="text" rows="7" style="font-family:ui-monospace,Menlo,monospace;padding:10px;border:1px solid var(--input);border-radius:8px">{{ old('text') }}</textarea>
            </label>
            <details class="card" style="background:#F9FAFB;box-shadow:none">
                <summary style="cursor:pointer;font-weight:600">Layout do ficheiro do Snipe-IT</summary>
                <ol class="muted" style="margin:12px 0;padding-left:18px">
                    <li>No Snipe-IT, abre <strong>Assets → Todos</strong>, escolhe as colunas e carrega em <strong>Exportar → CSV</strong> (ou usa <strong>Relatórios → Relatório personalizado</strong> com as colunas abaixo).</li>
                    <li>Escolhe <strong>Snipe-IT (assets)</strong> e carrega o ficheiro tal como sai. Os nomes das colunas em inglês são reconhecidos sozinhos.</li>
                    <li>Cada linha vai para a secção certa pela <strong>Category</strong>. Utilizadores e acessórios exportados do Snipe-IT importam-se nas opções Utilizadores e Acessórios.</li>
                </ol>
                <div style="overflow-x:auto"><table style="min-width:760px">
                    <thead><tr><th>Coluna no Snipe-IT</th><th>Vai para</th><th>Obrigatória</th><th>Exemplo</th><th>Notas</th></tr></thead>
                    <tbody>
                    @foreach(\App\Services\Import\Importer::SNIPE_LAYOUT as [$col, $to, $req, $example, $note])
                        <tr><td><code>{{ $col }}</code></td><td>{{ $to }}</td><td>{{ $req }}</td><td><code>{{ $example }}</code></td><td class="muted">{{ $note }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <p><a href="{{ route('import.template', 'snipeit') }}">Descarregar cabeçalho Snipe-IT</a></p>
            </details>
            <div class="muted">As linhas móveis aceitam o Excel atual tal como está: o PIN/PUK é separado, o telemóvel é criado pelo IMEI e as notas ("Capa + Película - NOVO 13/01/2025", "Portado da …") são lidas.
                Modelos de colunas: @foreach($modules as $key => $m)<a href="{{ route('import.template', $key) }}">{{ $m['label'] }}</a>@if(! $loop->last), @endif @endforeach.</div>
            <div><button class="btn" type="submit">Continuar</button></div>
        </form>
    @endif

    <section class="card" style="display:flex;flex-direction:column;gap:12px">
        <h2>Exportar</h2>
        <div class="muted">Exporta {{ session('company_id') ? 'só a empresa escolhida na barra de cima' : 'todas as tuas empresas' }}.</div>
        <div style="overflow-x:auto"><table>
            <tbody>
            @foreach($exportModules as $key => $label)
                <tr>
                    <td><strong>{{ $label }}</strong></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a class="btn ghost" href="{{ route('export', [$key, 'format' => 'xlsx']) }}">Excel</a>
                        <a class="btn ghost" href="{{ route('export', [$key, 'format' => 'csv']) }}">CSV</a>
                        @if($key === 'lines' && auth()->user()->isAdmin())
                            <a class="btn ghost" href="{{ route('export', [$key, 'format' => 'xlsx', 'secrets' => 1]) }}" title="Fica registado no histórico">Excel com PIN/PUK</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2 style="margin-bottom:10px">Importações feitas</h2>
        <div style="overflow-x:auto"><table style="min-width:640px">
            <thead><tr><th>Quando</th><th>O quê</th><th>Ficheiro</th><th>Por</th><th>Resultado</th><th></th></tr></thead>
            <tbody>
            @forelse($imports as $i)
                <tr>
                    <td class="muted">{{ $i->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                    <td>{{ $modules[$i->module]['label'] ?? $i->module }}</td>
                    <td class="muted">{{ $i->filename }}</td>
                    <td>{{ $i->user?->name }}</td>
                    <td>{{ $i->ok }} importadas{{ $i->errors ? ' · '.$i->errors.' com erro' : '' }}</td>
                    <td style="text-align:right">
                        @if($i->undone_at)<span class="chip">Anulada</span>
                        @elseif($i->canUndo() && auth()->user()->canWrite())
                            <form method="post" action="{{ route('import.undo', $i->id) }}">@csrf<button class="btn ghost" type="submit">Anular</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Ainda não há importações.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
