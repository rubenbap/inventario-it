@extends('layouts.app')
@section('title', 'Painel')

@section('content')
    <div class="row">
        <div>
            <h1>Painel</h1>
            <div class="muted">Resumo do inventário{{ $company ? ' · '.$company->name : '' }}</div>
        </div>
    </div>

    <section class="grid" aria-label="Totais">
        <div class="card kpi"><div class="l">Assets ativos</div><div class="n">{{ $totals['active'] }}</div></div>
        <div class="card kpi"><div class="l">Atribuídos</div><div class="n">{{ $totals['assigned'] }}</div></div>
        <div class="card kpi"><div class="l">Prontos a atribuir</div><div class="n">{{ $totals['ready'] }}</div></div>
        <div class="card kpi"><div class="l">Em reparação / avariados</div><div class="n">{{ $totals['broken'] }}</div></div>
    </section>

    @if($incomplete)
        <div class="card" role="status" style="border-color:#D97706;color:#B45309">⚠ <strong>{{ $incomplete }} {{ $incomplete === 1 ? 'equipamento' : 'equipamentos' }} por completar</strong>: falta a marca, o modelo ou o n.º de série.</div>
    @endif

    <section class="card" aria-label="Por empresa" style="display:flex;flex-direction:column;gap:12px">
        <div class="row">
            <h2>Por empresa</h2>
            <div class="muted" style="display:flex;gap:14px;font-size:13px">
                <span><span class="dot" style="background:#2563EB"></span> Assets</span>
                <span><span class="dot" style="background:#8B5CF6"></span> Impressoras</span>
                <span><span class="dot" style="background:#14B8A6"></span> Servidores/NAS</span>
                <span><span class="dot" style="background:#F97316"></span> Linhas</span>
            </div>
        </div>
        @foreach($byCompany as $r)
            <form method="post" action="{{ route('active-company') }}">@csrf
                <button class="co-row" name="company_id" value="{{ $company?->id === $r['company']->id ? '' : $r['company']->id }}"
                        title="Assets: {{ $r['assets'] }} · Impressoras: {{ $r['printers'] }} · Servidores/NAS: {{ $r['servers'] }} · Linhas: {{ $r['lines'] }}"
                        @style(['background:#EFF6FF' => $company?->id === $r['company']->id])>
                    <span style="display:flex;gap:10px;align-items:center;font-weight:600"><span class="badge">{{ $r['company']->code }}</span>{{ $r['company']->name }}</span>
                    <span class="co-bar"><span style="width:{{ $r['total'] ? max(3, $r['total'] / $maxTotal * 100) : 0 }}%">
                        @foreach(['assets' => '#2563EB', 'printers' => '#8B5CF6', 'servers' => '#14B8A6', 'lines' => '#F97316'] as $key => $color)
                            @if($r[$key])<i style="flex:{{ $r[$key] }} 1 0;background:{{ $color }}"></i>@endif
                        @endforeach
                    </span></span>
                    <strong style="text-align:right">{{ $r['total'] }}</strong>
                </button>
            </form>
        @endforeach
    </section>

    <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(300px, 1fr))">
        <section class="card">
            <h2>Devoluções em atraso</h2>
            <table>
                @forelse($overdue as $a)
                    <tr>
                        <td><strong>{{ $a->asset_tag }}</strong> · {{ $a->model?->name }}<br><span class="muted">{{ $a->assigned?->name }}</span></td>
                        <td style="color:var(--danger);white-space:nowrap">{{ (int) $a->expected_checkin->diffInDays(today(), true) }} dias</td>
                    </tr>
                @empty
                    <tr><td class="muted">Nada em atraso.</td></tr>
                @endforelse
            </table>
        </section>

        <section class="card">
            <h2>Stock baixo de acessórios</h2>
            <table>
                @forelse($lowStock as $acc)
                    <tr><td><strong>{{ $acc->name }}</strong></td><td class="muted" style="white-space:nowrap">{{ $acc->qty - $acc->open_checkouts_count }} disp. / mín. {{ $acc->min_amt }}</td></tr>
                @empty
                    <tr><td class="muted">Stock em ordem.</td></tr>
                @endforelse
            </table>
        </section>

        <section class="card">
            <h2>Garantias a terminar (30 dias)</h2>
            <table>
                @forelse($warranty as $a)
                    <tr><td><strong>{{ $a->asset_tag }}</strong> · {{ $a->model?->name }}</td><td class="muted" style="white-space:nowrap">{{ $a->warranty_expires->format('d/m/Y') }}</td></tr>
                @empty
                    <tr><td class="muted">Nenhuma garantia a terminar.</td></tr>
                @endforelse
            </table>
        </section>
    </div>

    <section class="card">
        <h2>Atividade recente</h2>
        <div style="overflow-x:auto">
            <table style="min-width:640px">
                <thead><tr><th>Quando</th><th>Ação</th><th>Item</th><th>Destino</th><th>Por</th></tr></thead>
                <tbody>
                @forelse($activity as $log)
                    <tr>
                        <td class="muted" style="white-space:nowrap">{{ $log->created_at->timezone(config('app.timezone'))->format('d/m H:i') }}</td>
                        <td><span class="chip">{{ $log->action_type }}</span></td>
                        <td>{{ $log->item?->asset_tag ?? $log->item?->name }}</td>
                        <td>{{ $log->target?->name ?? $log->target?->asset_tag ?? '—' }}</td>
                        <td class="muted">{{ $log->admin?->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Ainda sem atividade.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
