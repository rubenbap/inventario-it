@extends('layouts.app')
@section('title', 'Colunas')

@section('content')
    <div><a href="{{ route('import.index') }}">← Importar / Exportar</a></div>
    <h1>Colunas · {{ \App\Services\Import\Importer::MODULES[$module]['label'] }}</h1>
    <div class="muted">{{ $count }} linhas. As colunas foram reconhecidas automaticamente; corrige o que estiver errado.</div>

    <form class="card" method="post" action="{{ route('import.preview') }}" style="display:flex;flex-direction:column;gap:14px">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div style="overflow-x:auto"><table>
            <thead><tr><th>Coluna no ficheiro</th><th>Exemplo</th><th>Vai para</th></tr></thead>
            <tbody>
            @foreach($headers as $i => $header)
                <tr>
                    <td><strong>{{ $header ?: '(sem título)' }}</strong></td>
                    <td class="muted">{{ $sample[$i] ?? '' }}</td>
                    <td>
                        <select name="map[{{ $i }}]" aria-label="Destino de {{ $header }}">
                            <option value="">Ignorar</option>
                            @foreach($fields as $key => [$label])<option value="{{ $key }}" @selected($map[$i] === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="auto_create" value="1" checked style="width:18px;height:18px"> Criar automaticamente marcas, modelos, pessoas e localizações que não existam</label>
        <div><button class="btn" type="submit">Pré-visualizar</button></div>
    </form>
@endsection
