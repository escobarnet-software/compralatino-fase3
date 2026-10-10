<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Controllers/BiDashboardController.php
 * CAPA: Capa de Presentación — Controladores MVC / Panel de BI (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Business Intelligence en arquitectura por capas:
 *
 * El panel de Inteligencia de Negocios es una VENTANA DE LECTURA (read-only)
 * sobre los datos transaccionales. El controlador actúa como ORQUESTADOR de
 * consultas analíticas:
 *
 *  • MEDIDAS (KPIs): ingresos, ticket promedio, tasa de conversión, órdenes
 *    fallidas (salud del servicio de sincronización).
 *  • DIMENSIONES: tiempo (ventas por día), plataforma (local vs YAuctions),
 *    producto (top ventas), comportamiento (efectividad del recomendador).
 *  • SEPARACIÓN MVC: las consultas usan scopes/relaciones de Eloquent (Capa de
 *    Persistencia) y el cálculo de efectividad proviene del RecommendationService
 *    (Capa de Negocio). El controlador SOLO orquesta y entrega datos a la Vista.
 *  • AUTORIZACIÓN: protegido con middleware 'auth' + 'can:administrador';
 *    la toma de decisiones solo es visible para el área administrativa.
 */

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\View\View;

class BiDashboardController extends Controller
{
    /**
     * PANEL PRINCIPAL DE BI: consolida los KPIs del negocio.
     *
     * Calcula las "medidas" agregadas y las inyecta en la vista Blade
     * 'dashboard-bi'. La vista NO consulta la BD directamente
     * (unidireccionalidad de datos en MVC: Model → Controller → View).
     */
    public function index(RecommendationService $recomendador): View
    {
        // ---- VENTANA TEMPORAL: últimos 30 días (parámetro típico de BI) ----
        $desde = now()->subDays(30)->startOfDay();

        // ---- MEDIDA 1: ingresos y volumen de ventas efectivas ----
        // NOTA SQL: se califica 'orders.created_at' con el nombre de tabla porque
        // la consulta de topProductos hace JOIN con 'products' (ambas tablas tienen
        // 'created_at' y SQLite/MySQL exigen desambiguar la columna).
        $resumenVentas = Order::completadas()
            ->where('orders.created_at', '>=', $desde)
            ->selectRaw('COUNT(*) as ordenes, COALESCE(SUM(total), 0) as ingresos')
            ->first();

        // ---- MEDIDA 2: ticket promedio (indicador de decisión comercial) ----
        $ticketPromedio = $resumenVentas->ordenes > 0
            ? round($resumenVentas->ingresos / $resumenVentas->ordenes, 2)
            : 0;

        // ---- MEDIDA 3: distribución de órdenes por estado ----
        // Permite al administrador detectar cuellos de botella en la cola
        // (ej. muchas órdenes 'fallida' ⇒ problemas con la API de YAuctions).
        $ordenesPorEstado = Order::where('orders.created_at', '>=', $desde)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // ---- DIMENSIÓN: ventas por plataforma (comparativa multiplataforma) ----
        $ventasPorPlataforma = Order::completadas()
            ->where('orders.created_at', '>=', $desde)
            ->selectRaw('plataforma, COUNT(*) as ordenes, SUM(total) as ingresos')
            ->groupBy('plataforma')
            ->get();

        // ---- SERIE TEMPORAL: ingresos diarios (alimenta el gráfico) ----
        $ventasPorDia = Order::completadas()
            ->where('orders.created_at', '>=', $desde)
            ->selectRaw("DATE(orders.created_at) as dia, SUM(orders.total) as ingresos, COUNT(*) as ordenes")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // ---- DIMENSIÓN PRODUCTO: top 5 más vendidos ----
        $topProductos = Order::completadas()
            ->where('orders.created_at', '>=', $desde)
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->selectRaw('products.nombre, products.categoria, SUM(orders.cantidad) as unidades, SUM(orders.total) as ingresos')
            ->groupBy('products.nombre', 'products.categoria')
            ->orderByDesc('ingresos')
            ->limit(5)
            ->get();

        // ---- IA / KPI del MOTOR DE RECOMENDACIONES (Fase III) ----
        // Calculado por la Capa de Negocio; el controlador solo lo transporta.
        $efectividadRecomendaciones    = $recomendador->reporteDeEfectividad();
        $tasaConversionRecomendaciones = $efectividadRecomendaciones['tasa_conversion_pct'];

        // ---- SALUD DE LA INTEGRACIÓN: auditorías de fallo de sincronización ----
        $fallasSincronizacion = Order::where('estado', Order::ESTADO_FALLIDA)
            ->where('orders.created_at', '>=', $desde)
            ->count();

        // ---- INDICADORES DE BASE (usuarios, catálogo activo) ----
        $totalUsuarios    = User::count();
        $productosActivos = Product::visibles()->count();

        return view('dashboard-bi', compact(
            'resumenVentas',
            'ticketPromedio',
            'ordenesPorEstado',
            'ventasPorPlataforma',
            'ventasPorDia',
            'topProductos',
            'efectividadRecomendaciones',
            'tasaConversionRecomendaciones',
            'fallasSincronizacion',
            'totalUsuarios',
            'productosActivos'
        ));
    }

    /**
     * BITÁCORA DE AUDITORÍA: lista las órdenes fallidas con su JSON de
     * auditoría, permitiendo al administrador TOMAR DECISIONES (reintentar,
     * contactar al proveedor japonés, reembolsar). Es el "Data Warehouse
     * ligero" del monolito: consultas puntuales sobre la BD transaccional.
     */
    public function auditoria(): View
    {
        $ordenesFallidas = Order::where('estado', Order::ESTADO_FALLIDA)
            ->with(['user:id,name,email', 'product:id,nombre,sku'])
            ->latest()
            ->paginate(20);

        return view('bi.auditoria', compact('ordenesFallidas'));
    }
}
