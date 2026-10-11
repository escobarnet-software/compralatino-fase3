<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Controllers/StoreController.php
 * CAPA: Capa de Presentación — Controladores MVC (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Patrón MVC (Model-View-Controller):
 *
 * Este controlador es el COORDINADOR HTTP del módulo de compras. Su única
 * responsabilidad es mediar entre la petición del usuario (Vista/Request) y el
 * modelo de dominio (Model/Servicio), cumpliendo la SEPARACIÓN ESTRICTA:
 *
 *  • NO contiene reglas de negocio complejas → delega en RecommendationService
 *    y en el Job (Capa de Negocio). El controlador no "sabe" cómo se recomienda.
 *  • NO escribe SQL → usa Eloquent vía los modelos (Capa de Persistencia).
 *  • NO renderiza HTML directamente → devuelve VISTAS Blade o redirects.
 *
 * FLUJO ASÍNCRONO (característica de CompraLatino, Fase II/III):
 *   1. El usuario envía la compra.
 *   2. El controlador persiste la orden como 'pendiente' y DESPACHA el Job.
 *   3. Responde INMEDIATAMENTE (UX instantánea); la comunicación con
 *      YAuctions ocurrirá en segundo plano vía la cola de Laravel.
 *
 * PUNTOS DE EXTENSIÓN: al inyectar dependencias (RecommendationService) se
 * respeta el PRINCIPIO DE INVERSIÓN DE DEPENDENCIAS (DIP), facilitando
 * sustituir el motor de recomendaciones por otro sin tocar este archivo.
 */

namespace App\Http\Controllers;

use App\Exceptions\PaymentDeclinedException;
use App\Jobs\ProcessYAuctionPurchase;
use App\Models\Order;
use App\Models\Product;
use App\Models\UserHistory;
use App\Services\PaymentService;
use App\Services\RecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StoreController extends Controller
{
    /**
     * Inyección de dependencias por constructor: la Capa de Presentación
     * depende de una ABSTRACCIÓN de negocio (el servicio), no de su detalle.
     */
    public function __construct(
        private readonly RecommendationService $recomendador
    ) {
    }

    /**
     * CATÁLOGO PÚBLICO: lista productos visibles con paginación.
     * La paginación es un patrón de rendimiento: evita cargas masivas (N+1
     * mitigado con eager loading 'with()').
     *
     * BÚSQUEDA (requisito Fase III "buscar productos"): el formulario GET del
     * catálogo envía 'q' (texto), 'categoria' y 'plataforma'. Al ser GET, la
     * búsqueda es COMPARTIBLE por URL y respeta REST (lectura idempotente).
     * Los filtros se aplican con bindings parametrizados (anti-injection).
     */
    public function index(Request $request): View
    {
        $productos = Product::query()
            ->visibles()
            ->when($request->filled('q'), function ($query) use ($request) {
                // Búsqueda por texto en nombre, descripción y SKU (LIKE escapado).
                $termino = '%' . str_replace(['%', '_'], '', (string) $request->string('q')) . '%';
                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', $termino)
                        ->orWhere('descripcion', 'like', $termino)
                        ->orWhere('sku', 'like', $termino);
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $query->where('categoria', $request->string('categoria'));
            })
            ->when(in_array($request->string('plataforma')->toString(), ['local', 'yauctions'], true), function ($query) use ($request) {
                // Filtro multiplataforma: ver solo inventario local o de YAuctions.
                $query->where('plataforma', $request->string('plataforma')->toString());
            })
            ->withCount('orders')               // métrica de popularidad (agregado)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Listas para los <select> de filtros (dimensiones del catálogo).
        $categorias = Product::visibles()
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        // Motor de recomendaciones personalizado para el usuario autenticado.
        $recomendaciones = auth()->check()
            ? $this->recomendador->recomendarPara($request->user())
            : collect();

        // Trazabilidad: cada recomendación entregada queda en la bitácora BI.
        if (auth()->check() && $recomendaciones->isNotEmpty()) {
            $this->recomendador->registrarRecomendaciones($request->user(), $recomendaciones);
        }

        return view('store.index', compact('productos', 'recomendaciones', 'categorias'));
    }

    /**
     * PÁGINA "PARA TI" (requisito Fase III "ofrecer productos basados en el
     * historial"): muestra SOLO las recomendaciones del motor con la
     * explicación del puntaje (transparencia algorítmica: el usuario entiende
     * POR QUÉ se le sugiere cada producto).
     */
    public function recommendations(Request $request): View
    {
        $recomendaciones = $this->recomendador->recomendarPara($request->user(), 12);

        if ($recomendaciones->isNotEmpty()) {
            $this->recomendador->registrarRecomendaciones($request->user(), $recomendaciones);
        }

        return view('store.recommendations', ['recomendaciones' => $recomendaciones]);
    }

    /**
     * FICHA DE PRODUCTO: registra la vista en la bitácora (alimenta el motor
     * de recomendaciones) y muestra el detalle. Patrón REST: GET es seguro y
     * sin efectos secundarios de negocio, pero sí genera ANALYTICS (evento).
     */
    public function show(Product $product, Request $request): View
    {
        // Route Model Binding: Eloquent resolvió el producto y verificó su
        // existencia → si no existe, Laravel responde 404 (fail-safe).
        // Detección simple de dispositivo para el contexto del evento BI.
        $esMovil = str_contains($request->userAgent() ?? '', 'Mobile');

        UserHistory::registrar(
            $request->user()->id,
            $product->id,
            UserHistory::EVENTO_VISTA,
            ['dispositivo' => $esMovil ? 'movil' : 'escritorio']
        );

        return view('store.show', ['producto' => $product]);
    }

    /**
     * HISTORIAL DE COMPRAS PROPIAS: listado paginado de las órdenes del
     * usuario autenticado. El scope orders() impide ver órdenes ajenas (IDOR).
     */
    public function myOrders(Request $request): View
    {
        $ordenes = $request->user()
            ->orders()
            ->with('product:id,nombre,sku,plataforma')
            ->latest()
            ->paginate(10);

        return view('orders.index', ['ordenes' => $ordenes]);
    }

    /**
     * SEGUIMIENTO DE ORDEN: el usuario consulta el estado de su compra.
     * Ejemplo de Route Model Binding con autorización implícita: se filtra
     * por el usuario autenticado para impedir ver órdenes ajenas (IDOR).
     */
    public function showOrder(int $ordenId, Request $request): View
    {
        $orden = $request->user()
            ->orders()
            ->with('product:id,nombre,sku,plataforma')
            ->findOrFail($ordenId);

        return view('orders.show', ['orden' => $orden]);
    }

    /**
     * CHECKOUT (Paso 1 — mostrar formulario de tarjeta).
     *
     * El usuario YA eligió producto/cantidad/plataforma en la ficha; aquí ve el
     * RESUMEN del pedido e ingresa su tarjeta. Patrón PRG previo: los datos del
     * pedido viajan como query (?product_id=&cantidad=&plataforma=) validados
     * con las mismas reglas de dominio (fail-fast si se manipula la URL).
     */
    public function checkout(Request $request): View|RedirectResponse
    {
        $pedido = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'cantidad'   => ['required', 'integer', 'min:1', 'max:10'],
            'plataforma' => ['required', 'in:local,yauctions'],
        ]);

        $producto = Product::whereKey($pedido['product_id'])->firstOrFail();

        if (! $producto->hayStockDisponible($pedido['cantidad'])) {
            return redirect()->route('store.show', $producto)->withErrors([
                'cantidad' => 'No hay stock suficiente para la cantidad solicitada.',
            ]);
        }

        $total = (float) $producto->precio * $pedido['cantidad'];

        return view('store.checkout', [
            'producto'   => $producto,
            'cantidad'   => (int) $pedido['cantidad'],
            'plataforma' => $pedido['plataforma'],
            'total'      => $total,
        ]);
    }

    /**
     * PROCESAR COMPRA CON TARJETA (Paso 2 — cobrar y registrar).
     *
     * Secuencia por capas (el ORDEN importa):
     *   1. PRESENTACIÓN → StoreCheckoutRequest ya validó forma del pedido+tarjeta.
     *   2. NEGOCIO      → PaymentService::cobrar() intenta el cargo PRIMERO.
     *      Si la tarjeta falla (PaymentDeclinedException), NO se crea orden,
     *      NO se descuenta stock y NO se despacha el Job (fail-fast financiero:
     *      nunca se ejecuta una compra impaga).
     *   3. PERSISTENCIA → DB::transaction crea la orden YA pagada (con marca,
     *      últimos 4 y referencia del cargo) + descuenta stock (atomicidad ACID).
     *   4. ASÍNCRONO    → el Job sincroniza con YAuctions en segundo plano.
     *
     * SEGURIDAD: el PAN y el CVV solo existen en $datos (memoria del request);
     * jamás se asignan al modelo ni se registran (PaymentService los descarta).
     */
    public function store(\App\Http\Requests\StoreCheckoutRequest $request, PaymentService $pagos): RedirectResponse
    {
        // validated(): SOLO campos aprobados (anti mass-assignment).
        $datos = $request->validated();

        $producto = Product::whereKey($datos['product_id'])->firstOrFail();

        // Regla de negocio en el borde: stock suficiente.
        if (! $producto->hayStockDisponible($datos['cantidad'])) {
            return back()->withErrors([
                'cantidad' => 'No hay stock suficiente para la cantidad solicitada.',
            ])->withInput($request->except(['numero', 'cvv']));
        }

        $total = (float) $producto->precio * $datos['cantidad'];

        // PASO A — COBRO PRIMERO (fuera de la transacción: el cargo externo no
        // puede deshacerse con ROLLBACK; si falla, nada se persiste).
        try {
            $cargo = $pagos->cobrar(
                [
                    'numero'      => $datos['numero'],
                    'titular'     => $datos['titular'],
                    'vencimiento' => $datos['vencimiento'],
                    'cvv'         => $datos['cvv'],
                ],
                $total,
                $producto->moneda,
                // Idempotencia didáctica: la referencia del cargo se ancla a un
                // identificador único del intento (evita doble cobro al reintentar).
                (int) (Order::max('id') ?? 0) + 1
            );
        } catch (PaymentDeclinedException $rechazo) {
            // Regla de negocio: tarjeta rechazada → volver al checkout con el
            // mensaje, SIN crear orden. Se reenvían los campos NO sensibles.
            return back()
                ->withErrors(['numero' => $rechazo->getMessage()])
                ->withInput($request->except(['numero', 'cvv']));
        }

        // PASO B — ORDEN PAGADA (atomicidad ACID: orden + stock en una unidad).
        $orden = DB::transaction(function () use ($datos, $producto, $request, $total, $cargo) {
            $orden = Order::create([
                'user_id'         => $request->user()->id,
                'product_id'      => $producto->id,
                'cantidad'        => $datos['cantidad'],
                'precio_unitario' => $producto->precio,     // precio congelado al comprar
                'moneda'          => $producto->moneda,
                'total'           => $total,
                'estado'          => Order::ESTADO_PENDIENTE,
                'plataforma'      => $datos['plataforma'],
                // Datos NO sensibles del cobro (marca + últimos 4 + referencia).
                'marca_tarjeta'   => $cargo['marca'],
                'tarjeta_ultimos4' => $cargo['ultimos4'],
                'referencia_pago' => $cargo['referencia'],
                'pagado_en'       => now(),
            ]);

            // Descuento optimista de inventario (reducción condicional).
            Product::whereKey($producto->id)
                ->where('stock', '>=', $datos['cantidad'])
                ->decrement('stock', $datos['cantidad']);

            return $orden;
        });

        // TRAZABILIDAD: evento de compra en la bitácora (fuente del BI y del
        // perfil de recomendaciones).
        UserHistory::registrar(
            $request->user()->id,
            $producto->id,
            UserHistory::EVENTO_COMPRA,
            ['orden_id' => $orden->id, 'total' => (float) $orden->total]
        );

        // DESACOPLAMIENTO TEMPORAL: el Job viaja a la cola; la respuesta HTTP
        // no espera la llamada a la API japonesa (arquitectura de Fase II).
        ProcessYAuctionPurchase::dispatch($orden);

        // PRG (Post/Redirect/Get): evita reenvíos duplicados al recargar.
        return redirect()
            ->route('orders.show', $orden)
            ->with('status', "¡Compra registrada! La orden #{$orden->id} se está sincronizando con YAuctions.");
    }
}
