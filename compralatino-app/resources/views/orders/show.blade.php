{{--
  ==============================================================================
  ARCHIVO: resources/views/orders/show.blade.php
  CAPA: Capa de Presentación — Vista de seguimiento de orden (Clase 12)
  ==============================================================================
  TEORÍA APLICADA — Visibilidad del procesamiento asíncrono:

  Esta vista es la PRUEBA VISIBLE de la arquitectura de colas de CompraLatino:
  cuando la orden está 'pendiente' o 'en_proceso', la página se AUTO-RECARGA
  (meta refresh) y muestra el estado ACTUAL que el Job ProcessYAuctionPurchase
  va actualizando en la BD en segundo plano. El usuario nunca espera a la API
  de YAuctions; solo observa la evolución del estado.

  Elementos clave:
  1. LÍNEA DE TIEMPO semántica: las fases del ciclo de vida se presentan como
     pasos (teoría de retroalimentación de Norman: el sistema siempre comunica
     QUÉ está pasando).
  2. ESTADO con COLOR + TEXTO (no solo color → accesibilidad WCAG 1.4.1).
  3. ESCAPADO XSS en todos los datos dinámicos ({{ }}).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden #{{ $orden->id }} · CompraLatino</title>

    {{--
      AUTO-REFRESCO: solo mientras la orden está en tránsito por la cola.
      Demuestra la desincronización temporal entre la respuesta HTTP
      instantánea y el trabajo diferido del Job en la cola.
    --}}
    @if (in_array($orden->estado, ['pendiente', 'en_proceso']))
        <meta http-equiv="refresh" content="4">
    @endif

    <style>
        :root { --primario: #1e3a8a; --fondo: #f1f5f9; --texto: #0f172a; --texto-suave: #64748b; --exito: #15803d; --alerta: #b91c1c; --advertencia: #b45309; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
        header.app a { color: #dbeafe; text-decoration: none; }
        main { padding: 1.25rem; max-width: 800px; margin: 0 auto; }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.4rem; margin-bottom: 1rem; }

        /* Badges de estado: COLOR + ETIQUETA DE TEXTO (accesibilidad). */
        .estado { display: inline-block; padding: .3rem .9rem; border-radius: 999px; font-weight: 700; font-size: .9rem; }
        .estado.pendiente  { background: #fef3c7; color: var(--advertencia); }
        .estado.en_proceso { background: #dbeafe; color: var(--primario); }
        .estado.completada { background: #dcfce7; color: var(--exito); }
        .estado.fallida    { background: #fee2e2; color: var(--alerta); }

        /* Línea de tiempo vertical (responsiva por defecto). */
        .timeline { list-style: none; display: grid; gap: 1rem; margin-top: 1rem; border-left: 3px solid #cbd5e1; padding-left: 1.2rem; }
        .timeline li { position: relative; }
        .timeline li::before { content: ''; position: absolute; left: -1.62rem; top: .4rem; width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; }
        .timeline li.activo::before { background: var(--primario); }
        .timeline li.ok::before    { background: var(--exito); }
        .timeline li.error::before { background: var(--alerta); }

        dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1rem; margin-top: .8rem; }
        dt { font-weight: 600; color: var(--texto-suave); }
    </style>
</head>
<body>
    <header class="app">
        <h1>CompraLatino · Seguimiento de orden</h1>
        <nav aria-label="Navegación de la tienda">
            <a href="{{ route('store.index') }}" style="margin-right:1rem;">← Seguir comprando</a>
            <a href="{{ route('orders.index') }}">Mis órdenes</a>
        </nav>
    </header>

    <main>
        @if (session('status'))
            <p class="panel" role="status">{{ session('status') }}</p>
        @endif

        <section class="panel" aria-labelledby="titulo-orden">
            <h2 id="titulo-orden">Orden #{{ $orden->id }}</h2>

            {{-- ESTADO ACTUAL: refleja lo que el Job escribe en la BD. --}}
            <p style="margin-top:.6rem;">
                Estado:
                <span class="estado {{ $orden->estado }}" role="status">
                    {{ ucfirst(str_replace('_', ' ', $orden->estado)) }}
                </span>
            </p>

            {{--
              LÍNEA DE TIEMPO DEL CICLO DE VIDA: comunica al usuario en qué
              fase se encuentra el proceso asíncrono (retroalimentación).
            --}}
            <ol class="timeline">
                <li class="ok">
                    <strong>Orden recibida</strong><br>
                    <small>{{ $orden->created_at->format('d/m/Y H:i') }}</small>
                </li>
                <li class="{{ in_array($orden->estado, ['en_proceso', 'completada']) ? 'ok' : ($orden->estado === 'pendiente' ? 'activo' : '') }}">
                    <strong>Comunicación con YAuctions</strong><br>
                    <small>
                        @if ($orden->estado === 'en_proceso')
                            En cola — un worker está contactando la API externa…
                        @elseif ($orden->estado === 'completada')
                            Acuse recibido: {{ $orden->external_reference }}
                        @elseif ($orden->estado === 'fallida')
                            Falló la comunicación (revisar con soporte)
                        @else
                            Esperando worker de la cola
                        @endif
                    </small>
                </li>
                <li class="{{ $orden->estado === 'completada' ? 'ok' : ($orden->estado === 'fallida' ? 'error' : '') }}">
                    <strong>Confirmación final</strong>
                    @if ($orden->estado === 'completada' && $orden->sincronizado_en)
                        <br><small>Sincronizada el {{ $orden->sincronizado_en->format('d/m/Y H:i') }}</small>
                    @endif
                </li>
            </ol>

            {{-- DETALLE ECONÓMICO de la compra. --}}
            <dl>
                <dt>Producto</dt>
                <dd>{{ $orden->product->nombre ?? '—' }}</dd>
                <dt>Cantidad</dt>
                <dd>{{ $orden->cantidad }}</dd>
                <dt>Precio unitario</dt>
                <dd>{{ number_format((float) $orden->precio_unitario, 2) }} {{ $orden->moneda }}</dd>
                <dt>Total</dt>
                <dd><strong>{{ number_format((float) $orden->total, 2) }} {{ $orden->moneda }}</strong></dd>
                <dt>Plataforma</dt>
                <dd>{{ ucfirst($orden->plataforma) }}</dd>
            </dl>
        </section>

        {{--
          NOTA ACADÉMICA: la auditoría JSON (datos_sincronizacion) NO se muestra
          aquí por seguridad ($hidden en el modelo); es información interna
          visible solo para administradores en el panel de auditoría de BI.
        --}}
    </main>
</body>
</html>
