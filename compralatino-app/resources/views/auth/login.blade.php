{{--
  Vista de LOGIN mínima (solo demostración local; no forma parte del código
  fuente académico de la Fase III). Diseño responsive mobile-first consistente
  con el resto del sistema y accesible (label for + role de estado de error).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9; --texto: #0f172a; --alerta: #b91c1c; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); display: grid; place-items: center; min-height: 100vh; padding: 1rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(15,23,42,.12); padding: 2rem; width: 100%; max-width: 400px; }
        h1 { color: var(--primario); font-size: 1.3rem; margin-bottom: .3rem; }
        p.sub { color: #64748b; font-size: .9rem; margin-bottom: 1.4rem; }
        label { display: block; font-weight: 600; margin: .9rem 0 .3rem; }
        input[type=email], input[type=password] { width: 100%; padding: .65rem .8rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; min-height: 44px; }
        button { margin-top: 1.3rem; width: 100%; padding: .75rem; background: var(--primario); color: #fff; border: 0; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; min-height: 44px; }
        button:hover { background: var(--primario-suave); }
        .error { background: #fee2e2; color: var(--alerta); padding: .6rem .9rem; border-radius: 8px; margin-top: 1rem; font-size: .9rem; }
        .demo { margin-top: 1.4rem; font-size: .8rem; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: .9rem; }
        .demo code { background: #f1f5f9; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>CompraLatino</h1>
        <p class="sub">Inicia sesión para acceder a la tienda o al panel de BI</p>

        @if ($errors->any())
            <p class="error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>

        <p class="login-link">
            ¿No tienes cuenta? <a href="{{ route('register') }}">Crea una aquí</a>
        </p>

        <p class="demo">
            <strong>Cuentas demo:</strong><br>
            Admin BI: <code>admin@compralatino.test</code> / <code>secret1234</code><br>
            Cliente: <code>cliente1@compralatino.test</code> / <code>password</code>
        </p>
    </main>
</body>
</html>
