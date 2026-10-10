{{--
  ARCHIVO: resources/views/bi/auditoria.blade.php
  CAPA: Capa de Presentación — Auditoría de fallos (Clase 12)
  TEORÍA: recibe $ordenesFallidas del BiDashboardController (MVC); expone el
  JSON que el Job escribió en su catch (trazabilidad). Reutiliza los design
  tokens del panel BI. Tabla con scroll en móvil, scope en th, skip-link.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de fallos · CompraLatino BI</title>
    <style>
        :root {
            --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9;
            --tarjeta: #fff; --texto: #0f172a; --texto-suave: #64748b;
            --alerta: #b91c1c; --radio: 12px; --sombra: 0 1px 3px rgba(15,23,42,.12);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        .skip-link { position: absolute; left: -9999px; background: var(--primario); color: #fff; padding: .75rem 1rem; }
        .skip-link:focus { left: 0; top: 0; z-index: 100; }
        .app-header { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; }
        .app-header h1 { font-size: 1.15rem; }
        .app-layout { display: block; }
        .app-nav { background: var(--tarjeta); padding: 1rem; border-bottom: 1px solid #e2e8f0; }
        .app-nav ul { list-style: none; display: flex; flex-direction: column; gap: .5rem; }
        .app-nav a { display: block; padding: .65rem .9rem; border-radius: 8px; color: var(--primario); text-decoration: none; font-weight: 500; min-height: 44px; }
        .app-nav a:hover, .app-nav a[aria-current="page"] { background: #e0e7ff; }
        .app-main { padding: 1.25rem; }
        .kpi-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); margin-bottom: 1.5rem; }
        .kpi-card { background: var(--tarjeta); border-radius: var(--radio); box-shadow: var(--sombra); padding: 1.25rem; border-left: 5px solid var(--primario-suave); }
        .kpi-card.alerta { border-left-color: var(--alerta); }
        .kpi-card .etiqueta { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--texto-suave); font-weight: 600; }
        .kpi-card .valor { font-size: 1.75rem; font-weight: 700; margin-top: .25rem; }
        .panel { background: var(--tarjeta); border-radius: var(--radio); box-shadow: var(--sombra); padding: 1.25rem; margin-bottom: 1.5rem; }
        .panel h2 { font-size: 1.05rem; margin-bottom: .9rem; color: var(--primario); }
        .tabla-contenedor { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        caption { text-align: left; color: var(--texto-suave); padding-bottom: .5rem; }
        th, td { text-align: left; padding: .6rem .75rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f8fafc; font-weight: 600; white-space: nowrap; }
        .estado { display: inline-block; padding: .2rem .7rem; border-radius: 999px; font-weight: 700; font-size: .8rem; }
        .estado.fallida { background: #fee2e2; color: var(--alerta); }
        .estado.en_proceso { background: #dbeafe; color: var(--primario); }
        details summary { cursor: pointer; color: var(--primario); font-weight: 600; min-height: 44px; }
        pre { background: #0f172a; color: #e2e8f0; padding: .8rem; border-radius: 8px; overflow-x: auto; font-size: .75rem; margin-top: .5rem; }
        @media (min-width: 640px) {
            .app-layout { display: grid; grid-template-columns: 220px 1fr; }
            .app-nav { border-right: 1px solid #e2e8f0; border-bottom: none; }
            .app-main { padding: 1.75rem; }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#contenido">Saltar al contenido principal</a>
    <header class="app-header">
        <h1>CompraLatino · Auditoría de sincronización</h1>
        <p>Hola, {{ auth()->user()->name ?? 'Administrador' }}</p>
    </header>
    <div class="app-layout">
        <nav class="app-nav" aria-label="Navegación administrativa">
            <ul>
                <li><a href="{{ route('bi.dashboard') }}">Panel BI</a></li>
                <li><a href="{{ route('bi.auditoria') }}" aria-current="page">Auditoría de fallos</a></li>
                <li><a href="{{ route('store.index') }}">Volver a la tienda</a></li>
            </ul>
        </nav>
        <main class="app-main" id="contenido">
            <section class="kpi-grid" aria-label="Resumen de fallos">
                <article class="kpi-card alerta">
                    <p class="etiqueta">Órdenes con fallo (total)</p>
                    <p class="valor">{{ $ordenesFallidas->total() }}</p>
                </article>
                <article class="kpi-card">
                    <p class="etiqueta">Reintentables (en proceso)</p>
                    <p class="valor">{{ $ordenesFallidas->where('estado', 'en_proceso')->count() }}</p>
                </article>
                <article class="kpi-card">
                    <p class="etiqueta">Agotadas (fallidas)</p>
                    <p class="valor">{{ $ordenesFallidas->where('estado', 'fallida')->count() }}</p>
                </article>
            </section>
            <section class="panel" aria-labelledby="titulo-auditoria">
                <h2 id="titulo-auditoria">Bitácora de sincronización con YAuctions</h2>
                <div class="tabla-contenedor">
                    <table>
                        <caption>Rastro JSON escrito por el Job en su bloque catch.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Orden</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Producto</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Intentos</th>
                                <th scope="col">Último evento</th>
                                <th scope="col">Detalle técnico</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ordenesFallidas as $orden)
                                @php
                                    $eventos = $orden->datos_sincronizacion ?? [];
                                    $ultimo  = is_array($eventos) ? end($eventos) : null;
                                @endphp
                                <tr>
                                    <td><strong>#{{ $orden->id }}</strong><br><small>{{ $orden->created_at?->format('d/m/Y H:i') }}</small></td>
                                    <td>{{ $orden->user->name ?? '—' }}<br><small>{{ $orden->user->email ?? '' }}</small></td>
                                    <td>{{ $orden->product->nombre ?? '—' }}</td>
                                    <td><span class="estado {{ $orden->estado }}">{{ ucfirst(str_replace('_', ' ', $orden->estado)) }}</span></td>
                                    <td>{{ $orden->intentos_sincronizacion }}</td>
                                    <td>
                                        @if (is_array($ultimo))
                                            <strong>{{ $ultimo['evento'] ?? '—' }}</strong><br>
                                            <small>{{ $ultimo['fecha'] ?? '' }} · {{ $ultimo['mensaje'] ?? '' }}</small>
                                        @else
                                            <small>Sin eventos registrados</small>
                                        @endif
                                    </td>
                                    <td>
                                        <details>
                                            <summary>Ver JSON</summary>
                                            <pre>{{ json_encode($eventos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7">Sin fallos de sincronización. La cola está al día.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Paginación de auditoría" style="margin-top:1rem;">
                    {{ $ordenesFallidas->links() }}
                </nav>
            </section>
        </main>
    </div>
</body>
</html>
