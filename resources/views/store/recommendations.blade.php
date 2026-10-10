{{--
  ==============================================================================
  ARCHIVO: resources/views/store/recommendations.blade.php
  CAPA: Capa de Presentación — Recomendaciones basadas en historial (Clase 12)
  ==============================================================================
  TEORÍA APLICADA — Requisito Fase III "ofrecer productos basados en el
  historial de compras":

  1. TRANSPARENCIA ALGORÍTMICA: cada tarjeta muestra el PUNTAJE y el PORQUÉ
     (categorías afines + popularidad), para que la sugerencia no sea una
     "caja negra".
  2. MVC: la vista solo renderiza la colección ya ordenada por
     RecommendationService; no calcula nada.
  3. TRAZABILIDAD BI: el controlador ya registró estos eventos
     'recomendacion' en la bitácora (miden conversión en el panel BI).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recomendados para ti · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9; --texto: #0f172a; --texto-suave: #64748b; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; }
        header.app a { color: #dbeafe; text-decoration: none; font-weight: 500; }
        main { padding: 1.25rem; max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.1rem; display: flex; flex-direction: column; gap: .5rem; }
        .precio { font-size: 1.25rem; font-weight: 700; color: var(--primario); }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #e0e7ff; color: var(--primario); }
        .porque { font-size: .82rem; color: var(--texto-suave); background: #f8fafc; border-radius: 8px; padding: .5rem .7rem; }
        form.compra { display: flex; gap: .5rem; margin-top: auto; }
        button { font: inherit; padding: .55rem .8rem; border-radius: 8px; min-height: 44px; background: var(--primario); color: #fff; border: 0; cursor: pointer; }
        button:hover { background: var(--primario-suave); }
        .nota { background: #e0e7ff; border-radius: 8px; padding: .9rem 1.1rem; margin-bottom: 1rem; font-size: .9rem; }
    </style>
</head>
<body>
    <header class="app">
        <h1>✨ Recomendados para ti</h1>
        <nav aria-label="Navegación principal">
            <a href="{{ route('store.index') }}">← Catálogo</a>
            <a href="{{ route('orders.index') }}" style="margin-left:1rem;">Mis órdenes</a>
        </nav>
    </header>

    <main>
        <p class="nota" role="note">
            Estas sugerencias las genera el <strong>motor de recomendaciones</strong> a partir de
            tu historial de vistas, carritos y compras (filtrado por contenido con
            ponderación compra &gt; carrito &gt; vista). El puntaje explica la afinidad.
        </p>

        @if ($recomendaciones->isEmpty())
            <p role="status">Aún no tenemos suficientes señales de tu historial. Explora el
                <a href="{{ route('store.index') }}">catálogo</a> y vuelve aquí.</p>
        @else
            <div class="grid">
                @foreach ($recomendaciones as $producto)
                    <article class="card">
                        <span class="badge">Afinidad {{ $producto->puntaje_recomendacion ?? '—' }}</span>
                        <h2 style="font-size:1rem;">
                            <a href="{{ route('store.show', $producto) }}" style="color:inherit;text-decoration:none;">
                                {{ $producto->nombre }}
                            </a>
                        </h2>
                        <p class="precio">{{ $producto->precioFormateado() }}</p>
                        {{-- Explicabilidad: categoría afín + popularidad del producto. --}}
                        <p class="porque">
                            Sugerido por tu interés en <strong>{{ $producto->categoria }}</strong>
                            · {{ $producto->orders_count ?? 0 }} compra(s) de otros clientes.
                        </p>
                        <form class="compra" method="POST" action="{{ route('orders.store') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $producto->id }}">
                            <input type="hidden" name="cantidad" value="1">
                            <input type="hidden" name="plataforma" value="{{ $producto->plataforma }}">
                            <button type="submit">Comprar</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
