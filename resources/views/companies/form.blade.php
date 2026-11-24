@extends('layouts.app')
@section('title', $company->exists ? 'Editar empresa' : 'Nova empresa')

@section('content')
    <div><a href="{{ route('companies.index') }}">← Empresas</a></div>
    <h1>{{ $company->exists ? $company->name : 'Nova empresa' }}</h1>

    @if($errors->any() && ! $errors->has('company'))
        <div class="alert err" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <form class="card" method="post" action="{{ $company->exists ? route('companies.update', $company->id) : route('companies.store') }}" style="display:flex;flex-direction:column;gap:16px;max-width:760px">
        @csrf
        @if($company->exists) @method('PUT') @endif
        <div class="grid">
            <label class="field">Nome<input name="name" value="{{ old('name', $company->name) }}" required maxlength="191"></label>
            <label class="field">Código (prefixo das tags)<input name="code" value="{{ old('code', $company->code) }}" required pattern="[A-Za-z0-9]{2,5}" maxlength="5" style="text-transform:uppercase" placeholder="ex.: OMT"></label>
            <label class="field">NIF<input name="tax_id" value="{{ old('tax_id', $company->tax_id) }}" maxlength="20" inputmode="numeric"></label>
            @unless($company->exists)
                <label class="field">Primeira localização (opcional)<input name="location" value="{{ old('location') }}" placeholder="ex.: Sede"></label>
            @endunless
        </div>
        <label class="field">Notas<input name="notes" value="{{ old('notes', $company->notes) }}"></label>
        @if($company->exists)
            <label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $company->active)) style="width:18px;height:18px"> Empresa ativa</label>
            <div class="muted">Mudar o código só afeta as tags novas; as existentes mantêm-se.</div>
        @else
            <div class="muted">Os admins passam a ter acesso logo. Para dar acesso a técnicos ou leitura, edita o login deles em Acessos.</div>
        @endif
        <div class="row" style="justify-content:flex-start">
            <button class="btn" type="submit">{{ $company->exists ? 'Guardar' : 'Criar empresa' }}</button>
            <a class="btn ghost" href="{{ route('companies.index') }}">Cancelar</a>
        </div>
    </form>

    @if($company->exists)
        <form method="post" action="{{ route('companies.destroy', $company->id) }}" class="card" style="max-width:760px">
            @csrf @method('DELETE')
            <div class="row"><span class="muted">Só é possível apagar uma empresa sem equipamentos, linhas, acessórios nem pessoas.</span><button class="btn ghost" type="submit" style="color:var(--danger)">Apagar empresa</button></div>
        </form>
    @endif
@endsection
