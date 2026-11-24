@extends('layouts.app')
@section('title', 'Tokens da API')

@section('content')
    <div>
        <h1>Tokens da API</h1>
        <div class="muted">Um token é a chave que um programa (script, Power BI, ferramenta do Snipe-IT) usa para falar com a API, no cabeçalho <code>Authorization: Bearer &lt;token&gt;</code>. Dá a cada programa o seu token, só com o acesso de que precisa.</div>
    </div>

    @if($newToken)
        <div class="alert ok" role="status">
            <strong>Token criado. Copia-o agora: não volta a aparecer.</strong><br>
            <code style="word-break:break-all">{{ $newToken }}</code>
        </div>
    @endif

    <form class="card" method="post" action="{{ route('tokens') }}" style="display:flex;flex-direction:column;gap:12px">
        @csrf
        <strong>Gerar token</strong>
        <div class="grid">
            <label class="field">Nome<input name="name" value="{{ old('name') }}" placeholder="ex.: Power BI, script do Intune" required></label>
            <label class="field">Validade
                <select name="expires">
                    @foreach(\App\Http\Controllers\Web\TokenController::EXPIRY as $key => $label)
                        <option value="{{ $key }}" @selected(old('expires', '365') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <fieldset style="border:1px solid var(--border);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px">
            <legend class="muted" style="padding:0 6px">Acesso</legend>
            <label style="display:flex;gap:8px;align-items:flex-start"><input type="radio" name="access" value="read" @checked(old('access', 'read') === 'read') style="width:18px;height:18px;margin-top:2px"><span><strong>Só leitura</strong><br><span class="muted">Consulta equipamentos, pessoas, linhas, histórico e o resumo por empresa. Não altera nada e não vê PIN/PUK. Para relatórios e Power BI.</span></span></label>
            <label style="display:flex;gap:8px;align-items:flex-start"><input type="radio" name="access" value="write" @checked(old('access') === 'write') style="width:18px;height:18px;margin-top:2px"><span><strong>Leitura e escrita</strong><br><span class="muted">Pode também criar, editar, apagar, fazer check-in/out e ver PIN/PUK. Só para scripts que precisam mesmo de alterar dados.</span></span></label>
        </fieldset>
        @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
        <div><button class="btn" type="submit">Gerar token</button></div>
    </form>

    <section class="card">
        <table>
            <thead><tr><th>Nome</th><th>Acesso</th><th>Validade</th><th>Último uso</th><th></th></tr></thead>
            <tbody>
            @forelse($tokens as $t)
                @php($expired = $t->expires_at && $t->expires_at->isPast())
                <tr>
                    <td><strong>{{ $t->name }}</strong><div class="muted">criado {{ $t->created_at->format('d/m/Y') }}</div></td>
                    <td><span class="chip">{{ $t->can('write') ? 'Leitura e escrita' : 'Só leitura' }}</span></td>
                    <td class="muted">{{ $expired ? 'Expirou a '.$t->expires_at->format('d/m/Y') : ($t->expires_at ? 'até '.$t->expires_at->format('d/m/Y') : 'sem fim') }}</td>
                    <td class="muted">{{ $t->last_used_at?->diffForHumans() ?? 'nunca' }}</td>
                    <td style="text-align:right">
                        <form method="post" action="/tokens/{{ $t->id }}">@csrf @method('DELETE')<button class="btn ghost" type="submit">{{ $expired ? 'Apagar' : 'Revogar' }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Ainda não tens tokens.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
