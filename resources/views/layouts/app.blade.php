<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') — {{ config('app.name') }}</title>
    <style>
        :root { --bg:#F4F5F7; --surface:#FFF; --border:#E5E7EB; --divider:#F3F4F6; --text:#111827; --muted:#4B5563; --accent:#2563EB; --danger:#B91C1C; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--text); background:var(--bg); }
        a { color:#1D4ED8; text-decoration:none; } a:hover { text-decoration:underline; }
        .shell { display:flex; flex-wrap:wrap; min-height:100vh; }
        nav.side { flex:1 1 232px; max-width:100%; background:#111827; padding:24px 16px; display:flex; flex-direction:column; gap:4px; }
        nav.side .brand { color:#fff; font-weight:700; padding:4px 12px 24px; }
        nav.side a, nav.side button { display:block; width:100%; text-align:left; padding:10px 12px; border-radius:8px; color:#D1D5DB; font:inherit; font-size:14px; background:none; border:0; cursor:pointer; }
        nav.side a.on { background:#1F2937; color:#fff; font-weight:600; }
        nav.side .soon { color:#6B7280; cursor:default; }
        nav.side hr { border:0; height:1px; background:#374151; margin:12px; }
        main { flex:999 1 560px; min-width:0; padding:28px 32px 40px; display:flex; flex-direction:column; gap:20px; }
        h1 { margin:0; font-size:26px; } h2 { margin:0; font-size:16px; }
        .muted { color:var(--muted); font-size:14px; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
        .kpi .n { font-size:32px; font-weight:700; } .kpi .l { font-size:13px; color:var(--muted); font-weight:500; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th { text-align:left; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; padding:8px 12px 8px 0; }
        td { padding:10px 12px 10px 0; border-top:1px solid var(--divider); vertical-align:top; }
        .chip { font-size:12px; font-weight:600; padding:3px 8px; border-radius:999px; background:#F3F4F6; color:#374151; white-space:nowrap; }
        .btn { height:44px; padding:0 16px; border-radius:8px; border:0; background:var(--accent); color:#fff; font:inherit; font-weight:600; cursor:pointer; }
        .btn.ghost { background:#fff; color:var(--text); border:1px solid #D1D5DB; }
        input, select { height:44px; padding:0 12px; border:1px solid #D1D5DB; border-radius:8px; font:inherit; font-size:14px; background:#fff; color:var(--text); }
        .alert { padding:12px 16px; border-radius:10px; font-size:14px; }
        .alert.ok { background:#DCFCE7; color:#14532D; } .alert.err { background:#FEE2E2; color:#7F1D1D; }
        .field { display:flex; flex-direction:column; gap:6px; font-size:13px; font-weight:600; color:var(--muted); }
        .field input, .field select { color:var(--text); font-weight:400; }
        .row { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; }
        code { font-family: ui-monospace, Menlo, monospace; font-size:13px; }
        main { padding:0; gap:0; }
        .top { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:12px 28px; background:var(--surface); border-bottom:1px solid var(--border); }
        .page { padding:24px 28px 40px; display:flex; flex-direction:column; gap:20px; }
        .dd { position:relative; }
        .dd > summary { list-style:none; display:flex; align-items:center; gap:10px; min-height:46px; padding:5px 12px 5px 6px; border:1px solid var(--border); border-radius:12px; background:var(--surface); cursor:pointer; }
        .dd > summary::-webkit-details-marker { display:none; }
        .dd[open] > summary { border-color:var(--accent); box-shadow:0 0 0 3px #EFF6FF; }
        .dd .txt { display:flex; flex-direction:column; line-height:1.25; }
        .dd .txt small { font-size:11px; color:var(--muted); }
        .badge { display:inline-grid; place-items:center; min-width:36px; height:32px; padding:0 6px; border-radius:9px; background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; font-size:11px; font-weight:700; }
        .avatar { width:32px; height:32px; border-radius:50%; display:grid; place-items:center; background:#7C3AED; color:#fff; font-size:13px; font-weight:700; }
        .menu { position:absolute; top:calc(100% + 8px); left:0; min-width:300px; background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:0 10px 30px rgba(17,24,39,.18); padding:6px; z-index:30; }
        .menu.right { left:auto; right:0; }
        .menu form { margin:0; }
        .menu .item { display:flex; align-items:center; gap:10px; width:100%; padding:9px 10px; border:0; border-radius:8px; background:none; font:inherit; color:var(--text); text-align:left; cursor:pointer; }
        .menu .item:hover { background:#F9FAFB; text-decoration:none; }
        .menu .item.on { background:#EFF6FF; }
        .menu .sep { height:1px; background:var(--divider); margin:6px 4px; }
        .menu .head { padding:10px; display:flex; gap:12px; align-items:center; }
        nav.side { background:var(--surface); border-right:1px solid var(--border); flex:0 0 240px; position:sticky; top:0; height:100vh; overflow-y:auto; align-self:flex-start; padding:18px 12px; gap:1px; }
        nav.side .brand { color:var(--text); font-size:20px; letter-spacing:-.035em; padding:4px 10px 20px; }
        nav.side .brand span { color:var(--muted); font-weight:500; }
        nav.side a, nav.side span.soon { color:#374151; font-weight:500; padding:8px 10px; border-radius:7px; }
        nav.side a:hover { background:#F9FAFB; text-decoration:none; color:var(--text); }
        nav.side a.on { background:#F3F4F6; color:var(--text); font-weight:600; }
        nav.side .soon { color:#9CA3AF; }
        nav.side a, nav.side span.soon { display:flex; align-items:center; }
        nav.side .side-bottom { margin-top:auto; padding-top:12px; border-top:1px solid #E5E7EB; display:flex; flex-direction:column; gap:1px; }
        nav.side .count { margin-left:auto; font-size:12px; color:#6B7280; font-variant-numeric:tabular-nums; }
        nav.side hr { background:var(--divider); margin:8px 10px; }
        .dot { display:inline-block; width:8px; height:8px; border-radius:50%; }
        .co-row { display:grid; grid-template-columns:minmax(180px,260px) 1fr 44px; gap:14px; align-items:center; width:100%; padding:10px 12px; border:0; border-radius:10px; background:none; cursor:pointer; text-align:left; font:inherit; color:var(--text); }
        .co-row:hover { background:#F9FAFB; }
        .co-bar { height:12px; border-radius:6px; background:#F3F4F6; overflow:hidden; display:block; }
        .co-bar > span { display:flex; gap:2px; height:100%; }
        .co-bar i { display:block; height:100%; }
        @media (max-width:700px) { nav.side { position:static; height:auto; flex:1 1 100%; border-right:0; } .co-row { grid-template-columns:1fr 44px; } .co-bar { grid-column:1/-1; } }
    </style>
</head>
<body>
<div class="shell">
    <nav class="side" aria-label="Menu principal">
        <div class="brand">inventário<span>.it</span></div>
        <a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')])>Painel</a>
        @foreach(['assets' => 'Assets', 'printers' => 'Impressoras', 'servers' => 'Servidores e NAS', 'lines' => 'Linhas móveis', 'accessories' => 'Acessórios', 'users' => 'Utilizadores'] as $key => $label)
            <span class="soon" title="Em breve">{{ $label }}<span class="count">{{ $navCounts[$key] }}</span></span>
        @endforeach
        @if(auth()->user()->isAdmin())
            <a href="{{ route('companies.index') }}" @class(['on' => request()->routeIs('companies.*')])>Empresas<span class="count">{{ $navCounts['companies'] }}</span></a>
            <a href="{{ route('access.index') }}" @class(['on' => request()->routeIs('access.*')])>Acessos</a>
        @endif
        <div class="side-bottom">
            <a href="{{ route('import.index') }}" @class(['on' => request()->routeIs('import.*')])>Importar / Exportar</a>
        </div>
    </nav>
    <main>
        <header class="top">
            <details class="dd">
                <summary aria-label="Escolher empresa">
                    <span class="badge">{{ $activeCompany?->code ?? '∗' }}</span>
                    <span class="txt"><small>Empresa</small><strong>{{ $activeCompany?->name ?? 'Todas as empresas' }}</strong></span>
                    <span aria-hidden="true">▾</span>
                </summary>
                <div class="menu" role="menu">
                    <form method="post" action="{{ route('active-company') }}">@csrf
                        <button class="item {{ $activeCompany ? '' : 'on' }}" name="company_id" value="">
                            <span class="badge">∗</span><span>Todas as empresas</span>
                        </button>
                        <div class="sep"></div>
                        @foreach($topCompanies as $c)
                            <button class="item {{ $activeCompany?->id === $c->id ? 'on' : '' }}" name="company_id" value="{{ $c->id }}">
                                <span class="badge">{{ $c->code }}</span><span>{{ $c->name }}</span>
                            </button>
                        @endforeach
                    </form>
                    @if(auth()->user()->isAdmin())
                        <div class="sep"></div>
                        <a class="item" href="{{ route('companies.index') }}">Gerir empresas</a>
                    @endif
                </div>
            </details>

            <details class="dd">
                <summary aria-label="Conta">
                    <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1).mb_substr((string) auth()->user()->last_name, 0, 1)) }}</span>
                    <span class="txt"><strong>{{ auth()->user()->name }}</strong><small>{{ \App\Models\User::ROLE_LABELS[auth()->user()->role] ?? '' }}</small></span>
                    <span aria-hidden="true">▾</span>
                </summary>
                <div class="menu right" role="menu">
                    <div class="head"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1)) }}</span><span class="txt"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span></div>
                    <div class="sep"></div>
                    @if(auth()->user()->canWrite())<a class="item" href="{{ route('tokens') }}">Tokens da API</a>@endif
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="item" type="submit">Terminar sessão</button></form>
                </div>
            </details>
        </header>
        <div class="page">
            @if(session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
            @if($errors->has('company'))<div class="alert err" role="alert">{{ $errors->first('company') }}</div>@endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
