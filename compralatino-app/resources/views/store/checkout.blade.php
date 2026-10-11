{{--
  ARCHIVO: resources/views/store/checkout.blade.php
  CAPA: Capa de Presentación — Checkout con tarjeta (Clase 12)

  1. DOBLE COLUMNA: resumen del pedido (qué pago) + tarjeta (cómo pago).
  2. SEGURIDAD: @csrf, autocomplete="cc-*", inputmode numérico; el número y
     el CVV NUNCA se re-muestran tras un error (old() solo en no sensibles).
  3. PRUEBA: 4242... aprueba; 4000...0002 rechaza por fondos.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago con tarjeta · CompraLatino</title>
    <style>
        :root { --primario: #1e3a8a; --fondo: #f1f5f9; --texto: #0f172a; --suave: #64748b; --alerta: #b91c1c; --exito: #15803d; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--fondo); color: var(--texto); line-height: 1.6; }
        header.app { background: var(--primario); color: #fff; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        header.app a { color: #dbeafe; text-decoration: none; }
        main { padding: 1.25rem; max-width: 1000px; margin: 0 auto; }
        .checkout { display: grid; gap: 1.25rem; }
        @media (min-width: 768px) { .checkout { grid-template-columns: 1fr 1.4fr; } }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,.12); padding: 1.25rem; }
        .precio { font-size: 1.6rem; font-weight: 700; color: var(--primario); }
        label { display: block; font-weight: 600; margin: .8rem 0 .25rem; }
        input { width: 100%; padding: .6rem .8rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; min-height: 44px; }
        .fila { display: grid; gap: .8rem; grid-template-columns: 1fr 1fr; }
        button.pagar { margin-top: 1.1rem; width: 100%; padding: .8rem; background: var(--exito); color: #fff; border: 0; border-radius: 8px; font: inherit; font-weight: 700; font-size: 1.05rem; cursor: pointer; min-height: 48px; }
        .error-campo { color: var(--alerta); font-size: .85rem; margin-top: .25rem; }
        .demo { margin-top: 1rem; font-size: .82rem; color: var(--suave); background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: .7rem .9rem; }
        .demo code { background: #e2e8f0; padding: .1rem .35rem; border-radius: 4px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1rem; margin-top: .8rem; font-size: .93rem; }
        dt { font-weight: 600; color: var(--suave); }
    </style>
</head>
<body>
    <header class="app">
        <h1>CompraLatino · Pago seguro</h1>
        <a href="{{ route('store.show', $producto) }}">← Volver al producto</a>
    </header>
    <main>
        <div class="checkout">
            <section class="panel" aria-labelledby="titulo-resumen">
                <h2 id="titulo-resumen">Resumen de tu compra</h2>
                <p style="margin-top:.5rem;font-weight:600;">{{ $producto->nombre }}</p>
                <p style="color:var(--suave);font-size:.9rem;">{{ ucfirst($plataforma) }} · {{ $producto->categoria }}</p>
                <dl>
                    <dt>Cantidad</dt><dd>{{ $cantidad }}</dd>
                    <dt>Precio unitario</dt><dd>{{ $producto->precioFormateado() }}</dd>
                    <dt>Total a pagar</dt><dd><span class="precio">{{ number_format($total, 2) }} {{ $producto->moneda }}</span></dd>
                </dl>
            </section>
            <section class="panel" aria-labelledby="titulo-pago">
                <h2 id="titulo-pago">Datos de tu tarjeta</h2>
                <form method="POST" action="{{ route('orders.store') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $producto->id }}">
                    <input type="hidden" name="cantidad" value="{{ $cantidad }}">
                    <input type="hidden" name="plataforma" value="{{ $plataforma }}">
                    <label for="titular">Titular de la tarjeta</label>
                    <input type="text" id="titular" name="titular" value="{{ old('titular') }}" autocomplete="cc-name" placeholder="Como aparece en el plastico" required>
                    @error('titular')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                    <label for="numero">Numero de tarjeta</label>
                    <input type="text" id="numero" name="numero" value="" autocomplete="cc-number" inputmode="numeric" placeholder="4242 4242 4242 4242" maxlength="23" required>
                    @error('numero')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                    <div class="fila">
                        <div>
                            <label for="vencimiento">Vencimiento (MM/AA)</label>
                            <input type="text" id="vencimiento" name="vencimiento" value="{{ old('vencimiento') }}" autocomplete="cc-exp" inputmode="numeric" placeholder="12/28" maxlength="5" required>
                            @error('vencimiento')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="cvv">CVV</label>
                            <input type="password" id="cvv" name="cvv" value="" autocomplete="cc-csc" inputmode="numeric" placeholder="123" maxlength="4" required>
                            @error('cvv')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @error('product_id')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                    @error('cantidad')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                    @error('plataforma')<p class="error-campo" role="alert">{{ $message }}</p>@enderror
                    <button type="submit" class="pagar">Pagar {{ number_format($total, 2) }} {{ $producto->moneda }}</button>
                    <p style="margin-top:.75rem;font-size:.8rem;color:var(--suave);">Pago simulado academico: no se almacena tu numero ni tu CVV.</p>
                    <div class="demo" role="note">
                        <strong>Tarjetas de prueba:</strong><br>
                        Aprueba: <code>4242 4242 4242 4242</code> (cualquier futuro + CVV)<br>
                        Rechaza (sin fondos): <code>4000 0000 0000 0002</code>
                    </div>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
