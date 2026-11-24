<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova password — {{ config('app.name') }}</title>
    @include('auth._style')
</head>
<body>
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <h1>Nova password</h1>
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif
    <input type="hidden" name="token" value="{{ $token }}">
    <label>E-mail<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required></label>
    <label>Nova password<input type="password" name="password" minlength="10" autocomplete="new-password" required autofocus></label>
    <label>Confirmar password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
    <p class="muted">Mínimo 10 caracteres. Ao mudar, os tokens da API desta pessoa deixam de funcionar.</p>
    <button type="submit">Guardar password</button>
    <a href="{{ route('login') }}">← Voltar ao login</a>
</form>
</body>
</html>
