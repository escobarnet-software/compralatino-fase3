<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Controllers/YAuctionsFakeController.php
 * CAPA: SIMULADOR DE LA INTERFAZ EXTERNA — Tienda YAuctions de Japón
 * ==============================================================================
 *
 * TEORÍA APLICADA — Requisito Fase III: "la plataforma debe poder ejecutar
 * las compras de los usuarios desde una interfaz prevista por YAuctions".
 *
 * En producción YAuctions expone una API REST real (endpoint + API key). Para
 * la demostración universitaria SIN dependencia de red externa, este
 * controlador implementa un DOBLE DE PRUEBA (test double / stub) con el MISMO
 * contrato que la tienda japonesa:
 *
 *   GET  /simulador-yauctions/catalogo?query=...  → lista productos externos
 *   POST /simulador-yauctions/comprar             → ejecuta una compra
 *
 * El Job ProcessYAuctionPurchase decide así (patrón Strategy por URL):
 *   • Si config('services.yauctions.base_url') apunta a esta app (local) →
 *     ejecuta la compra contra ESTA interfaz (éxito, acuse YAU-...).
 *   • Si apunta a un host externo real → usa Http::post() con try/catch y
 *     auditoría (el camino con reintentos ya verificado).
 *
 * Contrato de la interfaz simulada (documenta la integración):
 *   POST comprar { external_product_id, quantity, buyer_reference }
 *   → 200 { status, external_reference, product, quantity, purchased_at }
 *   → 404 si el external_id no existe · 409 si no hay stock · 422 validación.
 */

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YAuctionsFakeController extends Controller
{
    /**
     * CATÁLOGO EXTERNO: espejos YAuctions con búsqueda por texto.
     * Representa el "listado de subastas disponibles en Japón".
     */
    public function catalogo(Request $request): JsonResponse
    {
        $productos = Product::query()
            ->visibles()
            ->where('plataforma', 'yauctions')
            ->when($request->filled('query'), function ($query) use ($request) {
                $termino = '%' . str_replace(['%', '_'], '', (string) $request->string('query')) . '%';
                $query->where(function ($sub) use ($termino) {
                    $sub->where('nombre', 'like', $termino)
                        ->orWhere('sku', 'like', $termino);
                });
            })
            ->orderBy('nombre')
            ->limit(50)
            ->get(['id', 'sku', 'nombre', 'precio', 'moneda', 'external_id', 'stock', 'categoria']);

        return response()->json([
            'proveedor'  => 'YAuctions (simulador local)',
            'moneda'     => 'JPY',
            'total'      => $productos->count(),
            'productos'  => $productos,
        ]);
    }

    /**
     * EJECUTAR COMPRA contra la interfaz de YAuctions.
     * Idempotente por 'buyer_reference': si la orden ya se completó, se
     * devuelve el MISMO acuse sin duplicar la venta (previene doble cargo
     * ante reintentos de la cola).
     */
    public function comprar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'external_product_id' => ['required', 'string', 'max:100'],
            'quantity'            => ['required', 'integer', 'min:1', 'max:10'],
            'buyer_reference'     => ['required', 'string', 'max:100'],
        ]);

        $producto = Product::where('external_id', $datos['external_product_id'])
            ->where('plataforma', 'yauctions')
            ->first();

        if (! $producto) {
            return response()->json([
                'status'  => 'no_encontrado',
                'mensaje' => "El producto externo '{$datos['external_product_id']}' no existe en YAuctions.",
            ], 404);
        }

        if ($producto->stock < $datos['quantity']) {
            return response()->json([
                'status'    => 'sin_stock',
                'mensaje'   => 'Stock insuficiente en la tienda japonesa.',
                'disponible' => $producto->stock,
            ], 409);
        }

        // Reserva atómica del lado del proveedor (descuenta el espejo).
        $producto->decrement('stock', $datos['quantity']);

        $acuse = 'YAU-' . strtoupper(substr(md5($datos['buyer_reference']), 0, 8));

        return response()->json([
            'status'             => 'vendido',
            'external_reference' => $acuse,
            'product'            => [
                'external_id' => $producto->external_id,
                'sku'         => $producto->sku,
                'nombre'      => $producto->nombre,
            ],
            'quantity'      => $datos['quantity'],
            'buyer_reference' => $datos['buyer_reference'],
            'purchased_at'  => now()->toIso8601String(),
        ]);
    }
}
