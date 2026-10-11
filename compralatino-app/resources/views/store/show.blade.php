{{--
  ==============================================================================
  ARCHIVO: resources/views/store/show.blade.php
  CAPA: Capa de Presentación — Vista de detalle (Clase 12)
  ==============================================================================
  TEORÍA APLICADA — Vista de ficha de producto:

  1. MVC: recibe el modelo $producto resuelto por Route Model Binding; no
     accede a la BD (el controlador ya hizo el trabajo de la Capa de
     Persistencia y registró el evento 'vista' en la bitácora).

  2. FORMULARIO DE COMPRA COMPLETO: a diferencia de la compra rápida del
     catálogo, aquí el usuario elige CANTIDAD y PLATAFORMA. Ambos campos son
     validados por StoreOrderRequest ANTES de tocar la Capa de Negocio.

  3. ACCESIBILIDAD: cada campo tiene <label for> explícito y los errores de
     validación se muestran con role="alert" junto al campo afectado.

  4. RESPONSIVE: dos columnas (info + compra) en escritorio, una sola en móvil.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $producto->nombre }} · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9; --texto: #0f172a; --texto-suave: #64748b; --alerta: #b91c1c; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        header.app a { color: #dbeafe; text-decoration: none; font-weight: 500; }
        main { padding: 1.25rem; max-width: 1000px; margin: 0 auto; }

        /* LAYOUT RESPONSIVE: 1 columna en móvil; 2 columnas ≥768px. */
        .ficha { display: grid; gap: 1.25rem; }
        @media (min-width: 768px) { .ficha { grid-template-columns: 2fr 1fr; } }

        .panel { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.25rem; }
        .precio { font-size: 1.6rem; font-weight: 700; color: var(--primario); }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #e0e7ff; color: var(--primario); }

        label { display: block; font-weight: 600; margin: .8rem 0 .25rem; }
        input, select { width: 100%; padding: .6rem .8rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; min-height: 44px; }
        button { margin-top: 1.1rem; width: 100%; padding: .75rem; background: var(--primario); color: #fff; border: 0; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; min-height: 44px; }
        button:hover { background: var(--primario-suave); }
        .error-campo { color: var(--alerta); font-size: .85rem; margin-top: .25rem; }
    </style>
</head>
<body>
    <header class="app">
        <h1>CompraLatino · Detalle de producto</h1>
        <nav aria-label="Navegación de la tienda">
            <a href="{{ route('store.index') }}" style="margin-right:1rem;">← Volver al catálogo</a>
            <a href="{{ route('orders.index') }}">Mis órdenes</a>
        </nav>
    </header>

    <main>
        <div class="ficha">
            {{-- INFORMACIÓN DEL PRODUCTO (modelo entregado por el controlador). --}}
            <section class="panel" aria-labelledby="nombre-producto">
                <span class="badge">{{ ucfirst($producto->plataforma) }}</span>
                <h2 id="nombre-producto" style="margin:.5rem 0;">{{ $producto->nombre }}</h2>
                <p class="precio">{{ $producto->precioFormateado() }}</p>
                <p style="margin-top:.8rem;color:var(--texto-suave);">{{ $producto->descripcion }}</p>
                <ul style="margin-top:1rem;list-style:none;display:grid;gap:.35rem;font-size:.92rem;">
                    <li><strong>SKU:</strong> {{ $producto->sku }}</li>
                    <li><strong>Categoría:</strong> {{ $producto->categoria }}</li>
                    <li><strong>Stock disponible:</strong> {{ $producto->stock }} unidades</li>
                    @if ($producto->external_id)
                        <li><strong>ID en YAuctions:</strong> {{ $producto->external_id }}</li>
                    @endif
                </ul>
            </section>

            {{--
              FORMULARIO DE COMPRA: en vez de crear la orden directamente, lleva
              al usuario al CHECKOUT con tarjeta (requisito Fase III "pagar con
              tarjeta"). Usa GET para que la elección de cantidad y plataforma
              viaje en la URL; el cobro con tarjeta se ingresa en el checkout.
            --}}
            <section class="panel" aria-labelledby="titulo-compra">
                <h2 id="titulo-compra">Realizar compra</h2>

                <form method="GET" action="{{ route('store.checkout') }}">
                    <input type="hidden" name="product_id" value="{{ $producto->id }}">

                    {{-- Cantidad: viaja al checkout (validada ahí como min:1 max:10). --}}
                    <label for="cantidad">Cantidad</label>
                    <input type="number" id="cantidad" name="cantidad"
                           value="{{ old('cantidad', 1) }}" min="1" max="10" required>

                    {{-- Plataforma multiplataforma (dominio cerrado: local|yauctions). --}}
                    <label for="plataforma">Plataforma de compra</label>
                    <select id="plataforma" name="plataforma" required>
                        <option value="yauctions" @selected(old('plataforma', $producto->plataforma) === 'yauctions')>
                            YAuctions (Japón) — sincronización asíncrona
                        </option>
                        <option value="local" @selected(old('plataforma', $producto->plataforma) === 'local')>
                            Tienda local
                        </option>
                    </select>

                    <button type="submit">Continuar al pago</button>
                    <p style="margin-top:.75rem;font-size:.82rem;color:var(--texto-suave);">
                        Continuarás a la pantalla de pago con tarjeta; al confirmar,
                        la orden se procesa EN SEGUNDO PLANO vía cola.
                    </p>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
