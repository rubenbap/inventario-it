@extends('layouts.app')
@section('title', 'Empresas')

@section('content')
    <div class="row">
        <div>
            <h1>Empresas</h1>
            <div class="muted">Cada equipamento, linha, acessório e pessoa pertence a uma empresa.</div>
        </div>
        <a class="btn" href="{{ route('companies.create') }}">+ Nova empresa</a>
    </div>

    <section class="card">
        <div style="overflow-x:auto">
            <table style="min-width:900px">
                <thead><tr><th>Empresa</th><th>Código</th><th>NIF</th><th>Assets</th><th>Impressoras</th><th>Servidores/NAS</th><th>Linhas</th><th>Acessórios</th><th>Pessoas</th><th>Logins</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($companies as $c)
                    @php
                        $byCategory = collect($kinds->get($c->id, []))->pluck('total', 'category');
                        $printers = (int) $byCategory->get('Impressora', 0);
                        $servers = (int) $byCategory->get('Servidor', 0) + (int) $byCategory->get('NAS', 0);
                        $lines = (int) $byCategory->get('Linha móvel', 0);
                    @endphp
                    <tr>
                        <td><strong>{{ $c->name }}</strong></td>
                        <td><span class="chip">{{ $c->code }}</span></td>
                        <td class="muted">{{ $c->tax_id ?: '—' }}</td>
                        <td>{{ $c->assets_count - $printers - $servers - $lines }}</td>
                        <td>{{ $printers }}</td>
                        <td>{{ $servers }}</td>
                        <td>{{ $lines }}</td>
                        <td>{{ $c->accessories_count }}</td>
                        <td>{{ $c->members_count }}</td>
                        <td title="Técnicos e leitura com acesso; os admins têm sempre">{{ $c->users_count }}</td>
                        <td>{{ $c->active ? 'Ativa' : 'Desativada' }}</td>
                        <td style="text-align:right"><a class="btn ghost" href="{{ route('companies.edit', $c->id) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted">Ainda não há empresas. Cria a primeira.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="muted">As asset tags de cada empresa usam o código como prefixo (ex.: OMT-00001). Uma empresa desativada deixa de aparecer nos formulários de registo, mas os dados ficam.</p>
    </section>
@endsection
