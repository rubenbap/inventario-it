<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — {{ config('app.name') }}</title>
    @include('auth._style')
</head>
<body>
<form method="post" action="/login">
    @csrf
    <h1>{{ config('app.name') }}</h1>
    @if(session('status'))<div class="ok" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif
    <label>E-mail ou utilizador<input type="text" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <label class="check"><input type="checkbox" name="remember" value="1"> Manter sessão iniciada</label>
    <button type="submit">Entrar</button>
    <a href="{{ route('password.request') }}">Esqueci-me da password</a>
</form>
</body>
</html>
