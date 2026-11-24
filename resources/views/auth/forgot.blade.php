<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Esqueci-me da password — {{ config('app.name') }}</title>
    @include('auth._style')
</head>
<body>
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <h1>Esqueci-me da password</h1>
    <p class="muted">Escreve o e-mail com que entras. Enviamos um link para definires uma nova password.</p>
    @if(session('status'))<div class="ok" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif
    <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
    <button type="submit">Enviar link</button>
    <a href="{{ route('login') }}">← Voltar ao login</a>
</form>
</body>
</html>
