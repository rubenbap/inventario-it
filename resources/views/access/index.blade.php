@extends('layouts.app')
@section('title', 'Acessos')

@section('content')
    <div class="row">
        <div>
            <h1>Acessos</h1>
            <div class="muted">Logins de quem gere o inventário. Várias pessoas podem estar ligadas ao mesmo tempo, cada uma com a sua sessão.</div>
        </div>
        <a class="btn" href="{{ route('access.create') }}">+ Novo login</a>
    </div>

    <div class="row" style="justify-content:flex-start">
        @foreach(['' => 'Todos', 'admin' => 'Pode mexer', 'leitura' => 'Só consulta'] as $key => $label)
            <a class="btn {{ ($role ?? '') === $key ? '' : 'ghost' }}" href="{{ route('access.index', $key ? ['role' => $key] : []) }}">{{ $label }}</a>
        @endforeach
    </div>

    <section class="card">
        <div style="overflow-x:auto">
            <table style="min-width:720px">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Acesso</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td><strong>{{ $u->name }}</strong>@if($u->is(auth()->user())) <span class="chip">tu</span>@endif</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="chip">{{ \App\Models\User::ROLE_LABELS[$u->role] ?? '—' }}</span></td>
                        <td>{{ $u->activated ? 'Ativo' : 'Desativado' }}</td>
                        <td style="text-align:right"><a class="btn ghost" href="{{ route('access.edit', $u->id) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum login.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
