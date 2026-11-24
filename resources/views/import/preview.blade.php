@extends('layouts.app')
@section('title', 'Pré-visualização')

@section('content')
    @php
        $labels = ['new' => ['Novo', '#16A34A'], 'update' => ['Atualiza', '#2563EB'], 'warn' => ['Com aviso', '#EA580C'], 'error' => ['Erro', '#B91C1C']];
        $counts = collect($result['rows'])->countBy('status');
    @endphp
    <div><a href="{{ route('import.index') }}">← Importar / Exportar</a></div>
    <div class="row">
        <h1>Pré-visualização · {{ \App\Services\Import\Importer::MODULES[$module]['label'] }}</h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach($labels as $key => [$label, $color])<span class="chip"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $color }}"></span> {{ $label }}: {{ $counts[$key] ?? 0 }}</span>@endforeach
        </div>
    </div>
    <div class="muted">Nada foi gravado ainda. Revê as linhas e confirma.</div>

    <section class="card">
        <div style="overflow-x:auto"><table style="min-width:820px">
            <thead><tr><th>Linha</th><th>Resultado</th><th>Dados</th><th>Mensagens</th></tr></thead>
            <tbody>
            @foreach($result['rows'] as $row)
                <tr @style(['background:#FEF2F2' => $row['status'] === 'error', 'background:#FFF7ED' => $row['status'] === 'warn'])>
                    <td class="muted">{{ $row['line'] }}</td>
                    <td><span class="chip">{{ $labels[$row['status']][0] }}</span></td>
                    <td>{{ $row['summary'] }}</td>
                    <td style="font-size:13px">
                        @forelse($row['messages'] as [$type, $message])
                            <div style="color:{{ ['err' => '#B91C1C', 'warn' => '#C2410C', 'info' => '#4B5563'][$type] }}">{{ $message }}</div>
                        @empty<span class="muted">—</span>@endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <form method="post" action="{{ route('import.run') }}" class="row" style="justify-content:flex-start">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="auto_create" value="{{ $autoCreate ? 1 : 0 }}">
        @foreach($map as $i => $field)<input type="hidden" name="map[{{ $i }}]" value="{{ $field }}">@endforeach
        <button class="btn" type="submit" @disabled(! $result['ok'])>Importar {{ $result['ok'] }} linhas</button>
        @if($result['errors'])<span class="muted">{{ $result['errors'] }} linhas com erro ficam de fora.</span>@endif
    </form>
@endsection
