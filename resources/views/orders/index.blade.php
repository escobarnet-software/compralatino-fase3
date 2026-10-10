{{--
  ARCHIVO: resources/views/orders/index.blade.php
  CAPA: Capa de Presentación — Historial de compras (Clase 12)
  TEORÍA: MVC estricto (recibe $ordenes paginadas); badges ESTADO color+texto
  (WCAG); tabla con scroll horizontal en móvil; skip-link y lang=es.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis órdenes · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --fondo: #f1f5f9; --texto: #0f172a; --texto-suave: #64748b; --exito: #15803d; --alerta: #b91c1c; --advertencia: #b45309; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        .skip-link { position: absolute; left: -9999px; background: var(--primario); color: #fff; padding: .75rem 1rem; }
        .skip-link:focus { left: 0; top: 0; z-index: 100; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        header.app nav a { color: #dbeafe; text-decoration: none; margin-left: 1rem; }
        main { padding: 1.25rem; max-width: 1000px; margin: 0 auto; }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.25rem; }
        .panel h2 { color: var(--primario); margin-bottom: .9rem; }
        .tabla-contenedor { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: .6rem .75rem; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; white-space: nowrap; }
        .estado { display: inline-block; padding: .2rem .7rem; border-radius: 999px; font-weight: 700; font-size: .8rem; }
        .estado.pendiente { background: #fef3c7; color: var(--advertencia); }
        .estado.en_proceso { background: #dbeafe; color: var(--primario); }
        .estado.completada { background: #dcfce7; color: var(--exito); }
        .estado.fallida { background: #fee2e2; color: var(--alerta); }
    </style>
</head>
<body>
    <a class="skip-link" href="#contenido">Saltar al contenido principal</a>
    <header class="app">
        <h1>CompraLatino · Mis órdenes</h1>
        <nav aria-label="Navegación de la tienda">
            <a href="{{ route('store.index') }}">Catálogo</a>
            <a href="{{ route('orders.index') }}" aria-current="page">Mis órdenes</a>
        </nav>
    </header>
    <main id="contenido">
        @if (session('status'))
            <p class="panel" role="status">{{ session('status') }}</p>
        @endif
        <section class="panel" aria-labelledby="titulo-ordenes">
            <h2 id="titulo-ordenes">Historial de compras</h2>
            <div class="tabla-contenedor">
                <table>
                    <caption>Estado actualizado en segundo plano por la cola de sincronización.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Orden</th>
                            <th scope="col">Producto</th>
                            <th scope="col">Total</th>
                            <th scope="col">Plataforma</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordenes as $orden)
                            <tr>
                                <td><a href="{{ route('orders.show', $orden) }}">#{{ $orden->id }}</a></td>
                                <td>{{ $orden->product->nombre ?? '—' }}</td>
                                <td>{{ number_format((float) $orden->total, 2) }} {{ $orden->moneda }}</td>
                                <td>{{ ucfirst($orden->plataforma) }}</td>
                                <td><span class="estado {{ $orden->estado }}">{{ ucfirst(str_replace('_', ' ', $orden->estado)) }}</span></td>
                                <td>{{ $orden->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Aún no tienes compras. Explora el catálogo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <nav aria-label="Paginación del historial" style="margin-top:1rem;">
                {{ $ordenes->links() }}
            </nav>
        </section>
    </main>
</body>
</html>
