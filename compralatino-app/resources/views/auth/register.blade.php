{{--
  Vista de REGISTRO (alta de cuentas multiplataforma, Fase III).
  Campos = reglas de StoreRegisterRequest (cohesión validación↔presentación).
  Accesible: label for + errores con role=alert junto al campo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9; --texto: #0f172a; --alerta: #b91c1c; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); display: grid; place-items: center; min-height: 100vh; padding: 1rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(15,23,42,.12); padding: 2rem; width: 100%; max-width: 440px; }
        h1 { color: var(--primario); font-size: 1.3rem; margin-bottom: .3rem; }
        p.sub { color: #64748b; font-size: .9rem; margin-bottom: 1rem; }
        label { display: block; font-weight: 600; margin: .9rem 0 .3rem; }
        input, select { width: 100%; padding: .65rem .8rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; min-height: 44px; }
        button { margin-top: 1.3rem; width: 100%; padding: .75rem; background: var(--primario); color: #fff; border: 0; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; min-height: 44px; }
        button:hover { background: var(--primario-suave); }
        .error-campo { color: var(--alerta); font-size: .85rem; margin-top: .25rem; }
        .login-link { margin-top: 1rem; font-size: .9rem; text-align: center; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Crear cuenta</h1>
        <p class="sub">Únete a CompraLatino y elige tu plataforma preferida</p>
        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <label for="name">Nombre completo</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
            @error('name')<p class="error-campo" role="alert">{{ $message }}</p>@enderror

            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<p class="error-campo" role="alert">{{ $message }}</p>@enderror

            <label for="password">Contraseña (mínimo 8 caracteres)</label>
            <input type="password" id="password" name="password" required>
            @error('password')<p class="error-campo" role="alert">{{ $message }}</p>@enderror

            <label for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>

            <label for="plataforma_preferida">Plataforma preferida</label>
            <select id="plataforma_preferida" name="plataforma_preferida" required>
                <option value="local" @selected(old('plataforma_preferida', 'local') === 'local')>Tienda local</option>
                <option value="yauctions" @selected(old('plataforma_preferida') === 'yauctions')>YAuctions (Japón)</option>
            </select>
            @error('plataforma_preferida')<p class="error-campo" role="alert">{{ $message }}</p>@enderror

            <button type="submit">Crear mi cuenta</button>
        </form>
        <p class="login-link">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a> · <a href="{{ route('store.index') }}">Volver a la tienda</a></p>
    </main>
</body>
</html>
