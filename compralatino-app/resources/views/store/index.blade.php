{{--
  ==============================================================================
  ARCHIVO: resources/views/store/index.blade.php
  CAPA: Capa de Presentación — Vistas de la Tienda (Clase 12)
  ==============================================================================
  TEORÍA APLICADA — Catálogo público + recomendaciones (Fase III):

  1. MVC: la vista SOLO renderiza los datos preparados por StoreController
     ($productos, $recomendaciones). No consulta la BD (unidireccionalidad).

  2. SEGURIDAD: todo dato se imprime con {{ }} (escapado HTML anti-XSS) y el
     formulario de compra incluye @csrf (token CSRF anti-forgery).

  3. INTERACCIÓN ASÍNCRONA VISIBLE: al comprar, el usuario es redirigido a la
     página de su orden donde verá el estado actualizado por el Job en cola.

  4. RESPONSIVE: mobile-first con CSS Grid auto-fill (tarjetas 1 → 2 → 3 col).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda · CompraLatino</title>
    <style>
        /* Tokens de diseño compartidos con el panel BI (consistencia visual). */
        :root {
            --primario: #1e3a8a; --primario-suave: #3b82f6; --fondo: #f1f5f9;
            --tarjeta: #fff; --texto: #0f172a; --texto-suave: #64748b;
            --exito: #15803d; --alerta: #b91c1c; --radio: 12px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; }
        header.app h1 { font-size: 1.2rem; }
        header.app nav a { color: #dbeafe; margin-left: 1rem; text-decoration: none; font-weight: 500; }
        main { padding: 1.25rem; max-width: 1200px; margin: 0 auto; }
        h2 { color: var(--primario); margin: 1.25rem 0 .9rem; font-size: 1.1rem; }

        /* Rejilla responsive de tarjetas (mobile-first). */
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
        .card { background: var(--tarjeta); border-radius: var(--radio); box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.1rem; display: flex; flex-direction: column; gap: .5rem; }
        .card h3 { font-size: 1rem; }
        .precio { font-size: 1.25rem; font-weight: 700; color: var(--primario); }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
        .badge.yauctions { background: #fef3c7; color: #92400e; }   /* tienda externa JA */
        .badge.local { background: #dcfce7; color: #166534; }       /* inventario propio */
        .badge.reco { background: #e0e7ff; color: var(--primario); }

        /* Formulario de compra rápido integrado en la tarjeta. */
        form.compra { display: flex; gap: .5rem; margin-top: auto; }
        select, input, button, .btn { font: inherit; padding: .55rem .8rem; border-radius: 8px; min-height: 44px; }
        select { border: 1px solid #cbd5e1; flex: 1; }
        button, .btn { background: var(--primario); color: #fff; border: 0; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        button:hover, .btn:hover { background: var(--primario-suave); }

        .alerta-error { background: #fee2e2; color: var(--alerta); padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .exito-flash { background: #dcfce7; color: var(--exito); padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .paginacion { margin-top: 1.5rem; display: flex; gap: .5rem; flex-wrap: wrap; }

        /* Tablet/escritorio: más columnas automáticamente por auto-fill. */
        @media (min-width: 768px) { main { padding: 2rem; } }
    </style>
</head>
<body>
    <header class="app">
        <h1>CompraLatino · Catálogo multiplataforma</h1>
        <nav aria-label="Navegación principal">
            <span>{{ auth()->user()->name }}</span>
            <a href="{{ route('store.recommendations') }}">✨ Para ti</a>
            <a href="{{ route('orders.index') }}">Mis órdenes</a>
            {{-- Panel BI visible solo para administradores (autorización por vista). --}}
            @if (auth()->user()->esAdministrador())
                <a href="{{ route('bi.dashboard') }}">Panel BI</a>
            @endif
            {{-- Cierre de sesión vía POST con CSRF (anti-forgery; nunca por GET). --}}
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:none;border:0;color:#dbeafe;cursor:pointer;font:inherit;margin-left:1rem;min-height:44px;">Salir</button>
            </form>
        </nav>
    </header>

    <main>
        {{--
          BUSCADOR (requisito Fase III "buscar productos"): formulario GET con
          texto libre + filtros de categoría y plataforma. Al ser GET, la
          búsqueda es COMPARTIBLE por URL y no muta estado (REST idempotente).
          role="search" identifica la región para lectores de pantalla.
        --}}
        <section aria-label="Buscar productos">
            <form method="GET" action="{{ route('store.index') }}" role="search"
                  style="display:flex;flex-wrap:wrap;gap:.5rem;background:#fff;padding:.9rem;border-radius:12px;box-shadow:0 1px 3px rgba(15,23,42,.12);margin-bottom:1rem;">
                <label for="q" style="position:absolute;left:-9999px;">Buscar</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}"
                       placeholder="Buscar por nombre, descripción o SKU…"
                       style="flex:2 1 220px;border:1px solid #cbd5e1;min-height:44px;">
                <label for="f-categoria" style="position:absolute;left:-9999px;">Categoría</label>
                <select id="f-categoria" name="categoria" style="flex:1 1 150px;border:1px solid #cbd5e1;min-height:44px;">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected(request('categoria') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <label for="f-plataforma" style="position:absolute;left:-9999px;">Plataforma</label>
                <select id="f-plataforma" name="plataforma" style="flex:1 1 150px;border:1px solid #cbd5e1;min-height:44px;">
                    <option value="">Local + YAuctions</option>
                    <option value="local" @selected(request('plataforma') === 'local')>Solo local</option>
                    <option value="yauctions" @selected(request('plataforma') === 'yauctions')>Solo YAuctions</option>
                </select>
                <button type="submit">🔍 Buscar</button>
                @if (request('q') || request('categoria') || request('plataforma'))
                    <a href="{{ route('store.index') }}" class="btn" style="background:#64748b;">Limpiar</a>
                @endif
            </form>
            @if (request('q'))
                <p role="status" style="color:var(--texto-suave);font-size:.9rem;margin-bottom:.5rem;">
                    {{ $productos->total() }} resultado(s) para “{{ request('q') }}”
                </p>
            @endif
        </section>

        {{-- Retroalimentación de la compra procesada (flash message del PRG). --}}
        @if (session('status'))
            <p class="exito-flash" role="status">{{ session('status') }}</p>
        @endif

        {{--
          NOTA: el catálogo YA no procesa compras directas (todo pasa por el
          checkout con tarjeta). Por eso eliminamos el bloque antiguo
          "Revisa tu compra" que mostraba errores de validación aquí: los
          errores de pago ahora se muestran SOLO en el checkout, junto al campo
          que el usuario debe corregir (UX + capa de presentación coherente).
        --}}

        {{--
          SECCIÓN DE RECOMENDACIONES (Fase III): generadas por la Capa de
          Negocio (RecommendationService). Cada tarjeta muestra el puntaje de
          afinidad calculado — el usuario ve POR QUÉ se le sugiere el producto.
        --}}
        @if ($recomendaciones->isNotEmpty())
            <section aria-labelledby="titulo-reco">
                <h2 id="titulo-reco">✨ Recomendados para ti</h2>
                <div class="grid">
                    @foreach ($recomendaciones as $producto)
                        <article class="card">
                            <span class="badge reco">Puntaje {{ $producto->puntaje_recomendacion ?? '—' }}</span>
                            <h3>{{ $producto->nombre }}</h3>
                            <span class="badge {{ $producto->plataforma }}">{{ ucfirst($producto->plataforma) }}</span>
                            <p class="precio">{{ $producto->precioFormateado() }}</p>
                            <a class="btn" href="{{ route('store.checkout', ['product_id' => $producto->id, 'cantidad' => 1, 'plataforma' => $producto->plataforma]) }}">Comprar</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{--
          CATÁLOGO GENERAL: paginado por el controlador (rendimiento). La
          paginación de Blade genera los enlaces "Anterior/Siguiente".
        --}}
        <section aria-labelledby="titulo-catalogo">
            <h2 id="titulo-catalogo">Catálogo completo</h2>
            <div class="grid">
                @forelse ($productos as $producto)
                    <article class="card">
                        <span class="badge {{ $producto->plataforma }}">{{ ucfirst($producto->plataforma) }}</span>
                        <h3>
                            {{-- Ficha de producto: registra evento 'vista' en la bitácora. --}}
                            <a href="{{ route('store.show', $producto) }}" style="color:inherit;text-decoration:none;">
                                {{ $producto->nombre }}
                            </a>
                        </h3>
                        <p style="color:var(--texto-suave);font-size:.9rem;">{{ $producto->descripcion }}</p>
                        <p class="precio">{{ $producto->precioFormateado() }}</p>
                        <p style="font-size:.85rem;color:var(--texto-suave);">
                            Stock: {{ $producto->stock }} · {{ $producto->orders_count }} venta(s)
                        </p>

                        {{-- Compra con tarjeta: 1 unidad; el pago se ingresa en el checkout. --}}
                        <a class="btn" style="margin-top:auto;" href="{{ route('store.checkout', ['product_id' => $producto->id, 'cantidad' => 1, 'plataforma' => $producto->plataforma]) }}">Comprar</a>
                    </article>
                @empty
                    <p>No hay productos en el catálogo. Ejecuta el seeder de demostración.</p>
                @endforelse
            </div>

            {{-- Paginación (enlaces generados por el paginator de Laravel). --}}
            <nav class="paginacion" aria-label="Paginación del catálogo">
                {{ $productos->links() }}
            </nav>
        </section>
    </main>

    <footer class="app">
        <p>CompraLatino — Fase III · Compras multiplataforma con procesamiento asíncrono</p>
    </footer>
</body>
</html>
