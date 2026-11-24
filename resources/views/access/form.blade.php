@extends('layouts.app')
@section('title', $user->exists ? 'Editar acesso' : 'Novo login')

@section('content')
    <div><a href="{{ route('access.index') }}">← Acessos</a></div>
    <h1>{{ $user->exists ? 'Acesso de '.$user->name : 'Novo login' }}</h1>

    @if($errors->any())
        <div class="alert err" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    @php
        $role = old('role', $user->role);
    @endphp

    <form class="card" method="post" action="{{ $user->exists ? route('access.update', $user->id) : route('access.store') }}" style="display:flex;flex-direction:column;gap:16px;max-width:760px">
        @csrf
        @if($user->exists) @method('PUT') @endif

        <div class="grid">
            <label class="field">Nome<input name="first_name" value="{{ old('first_name', $user->first_name) }}" required></label>
            <label class="field">Apelido<input name="last_name" value="{{ old('last_name', $user->last_name) }}"></label>
            <label class="field">E-mail (login)<input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="off"></label>
            <label class="field">Perfil
                <select name="role" required>
                    @foreach(['admin' => 'Pode mexer: cria, edita, atribui e gere logins', 'leitura' => 'Só consulta: vê tudo, não altera nada'] as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">{{ $user->exists ? 'Nova password (opcional)' : 'Password (opcional)' }}<input type="password" name="password" minlength="10" autocomplete="new-password"></label>
            <label class="field">Confirmar password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password"></label>
        </div>
        @unless($user->exists)
            <div class="muted">Se deixares a password em branco, a pessoa recebe um e-mail com um link para a definir ela própria.</div>
        @endunless

        <div class="muted">Quem tem login vê todas as empresas. O PIN/PUK das linhas só aparece a quem pode mexer.</div>

        @if($user->exists)
            <div class="row" style="justify-content:flex-start">
                <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="can_login" value="0"><input type="checkbox" name="can_login" value="1" @checked(old('can_login', $user->can_login)) style="width:18px;height:18px"> Pode entrar</label>
                <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="activated" value="0"><input type="checkbox" name="activated" value="1" @checked(old('activated', $user->activated)) style="width:18px;height:18px"> Ativo</label>
            </div>
            <div class="muted">Ao tirar o login ou desativar, a pessoa deixa de ter acesso logo no pedido seguinte e os tokens da API são revogados.</div>
        @endif

        <div class="row" style="justify-content:flex-start">
            <button class="btn" type="submit">{{ $user->exists ? 'Guardar' : 'Criar login' }}</button>
            <a class="btn ghost" href="{{ route('access.index') }}">Cancelar</a>
        </div>
    </form>

    @if($user->exists && $user->can_login)
        <form method="post" action="{{ route('access.link', $user->id) }}" class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            @csrf
            <span class="muted">Esqueceu-se da password? Envia-lhe um link para definir uma nova (válido 60 minutos).</span>
            <button class="btn ghost" type="submit">Enviar link de nova password</button>
        </form>
    @endif
@endsection
