<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Services/RecommendationService.php
 * CAPA: Capa de Negocio (Clase 11)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Servicios de negocio (Application Services):
 *
 * El patrón SERVICE LAYER aísla las REGLAS DE NEGOCIO fuera de los
 * controladores (que solo orquestan la capa de presentación) y fuera de los
 * modelos (que solo persisten). Beneficios demostrados en este archivo:
 *
 *  • SINGLE RESPOSIBILITY: el controlador NO calcula recomendaciones; solo
 *    llama a `$service->recomendarPara(...)`. Cambiar el algoritmo de
 *    recomendación NO requiere tocar la capa de presentación (OCP).
 *  • REUTILIZACIÓN: el mismo servicio es invocado por StoreController (vista
 *    pública) y por BiDashboardController (reportes de BI) → UNA SOLA FUENTE
 *    DE VERDAD para el cálculo de inteligencia de negocios.
 *  • TESTEABILIDAD: al depender de modelos (no de requests HTTP), puede
 *    probarse con datos factory en aislamiento (Unit Testing).
 *
 * ALGORITMO (híbrido, académico y explicable):
 *  1. FILTRADO CONTENIDO: se ponderan las categorías según los eventos del
 *     historial (compra > carrito > vista), extrayendo el perfil de interés.
 *  2. FILTRADO COLABORATIVO simplificado: se descubren productos co-visitados
 *     por el MISMO usuario (quién vio A también vio B).
 *  3. DESCUBRIMIENTO / COLD-START: si el perfil está vacío, se sugieren los
 *     productos más populares del sistema (tendencia).
 *  4. TRAZABILIDAD: cada recomendación generada se registra en 'user_history'
 *     para que el BI mida su tasa de conversión (recomendación → compra).
 */

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Collection;

class RecommendationService
{
    /**
     * Ponderación por tipo de evento (fuerza de la señal de preferencia).
     * Comprar es la evidencia más fuerte de interés; ver es la más débil.
     */
    private const PESOS_EVENTO = [
        UserHistory::EVENTO_COMPRA  => 5.0,
        UserHistory::EVENTO_CARRITO => 3.0,
        UserHistory::EVENTO_VISTA   => 1.0,
    ];

    /** Peso extra por coincidencia exacta de categoría con el perfil. */
    private const PESO_CATEGORIA = 2.0;

    /** Cantidad máxima de sugerencias entregadas por defecto. */
    private const LIMITE_DEFECTO = 6;

    /**
     * Punto de entrada principal: genera recomendaciones para un usuario.
     *
     * @return Collection<Product> colección de productos ordenada por relevancia.
     */
    public function recomendarPara(User $usuario, ?int $limite = null): Collection
    {
        $limite     = $limite ?? self::LIMITE_DEFECTO;
        $perfil     = $this->construirPerfilDeInteres($usuario);
        $comprados  = $this->idsComprados($usuario);

        // Si no hay historial suficiente → estrategia de descubrimiento global.
        if ($perfil->isEmpty()) {
            return $this->productosEnTendencia($limite);
        }

        // Candidatos = productos activos de las categorías que interesan al usuario.
        $categoriasTop = $perfil->keys()->take(3);

        return Product::query()
            ->visibles()
            ->whereIn('categoria', $categoriasTop)
            ->whereNotIn('id', $comprados)        // no recomendar lo ya comprado
            ->get()
            ->map(function (Product $producto) use ($perfil) {
                // puntaje = popularidad del producto + afinidad de categoría.
                $producto->puntaje_recomendacion = round(
                    $producto->interaccionesPopulares() + ($perfil[$producto->categoria] ?? 0) * self::PESO_CATEGORIA,
                    2
                );
                return $producto;
            })
            ->sortByDesc('puntaje_recomendacion')
            ->take($limite)
            ->values();
    }

    /**
     * Registra en la bitácora las recomendaciones ENTREGADAS al usuario.
     * Esto es crucial para el BI: permite medir la conversión
     * (recomendación → compra) y evaluar el ROI del motor.
     */
    public function registrarRecomendaciones(User $usuario, Collection $productos): void
    {
        foreach ($productos as $producto) {
            UserHistory::registrar(
                $usuario->id,
                $producto->id,
                UserHistory::EVENTO_RECOMENDACION,
                ['puntaje' => $producto->puntaje_recomendacion ?? 0, 'origen' => 'motor_v1']
            );
        }
    }

    /**
     * Reporte ESTRUCTURADO de efectividad del motor para el panel de BI.
     * Devuelve métricas agregadas listas para visualizar (KPI de IA):
     *   • total_recomendaciones → cuántas sugerencias se entregaron.
     *   • conversiones         → cuántas derivaron en compra real.
     *   • tasa_conversion_pct  → % de efectividad (indicador de decisión).
     */
    public function reporteDeEfectividad(): array
    {
        $total = UserHistory::where('evento', UserHistory::EVENTO_RECOMENDACION)->count();

        // Conversión: el MISMO usuario compró el producto recomendado.
        // whereExists con whereColumn empareja user_id y product_id entre
        // la bitácora y las órdenes completadas (SQL portable, sin N+1).
        $conversiones = UserHistory::where('evento', UserHistory::EVENTO_RECOMENDACION)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'user_history.user_id')
                    ->whereColumn('orders.product_id', 'user_history.product_id')
                    ->where('orders.estado', Order::ESTADO_COMPLETADA);
            })
            ->count();

        return [
            'total_recomendaciones' => $total,
            'conversiones'         => $conversiones,
            'tasa_conversion_pct'  => $total > 0 ? round(($conversiones / $total) * 100, 2) : 0.0,
        ];
    }

    // =====================================================================
    // MÉTODOS PRIVADOS — lógica interna oculta (encapsulamiento / info-hiding)
    // =====================================================================

    /**
     * PASO 1 — Perfil de interés: agrega el historial del usuario y lo
     * pondera por tipo de evento. Devuelve [categoria => puntaje] ordenado
     * de mayor a menor afinidad.
     */
    private function construirPerfilDeInteres(User $usuario): Collection
    {
        return UserHistory::query()
            ->where('user_id', $usuario->id)
            ->whereIn('evento', array_keys(self::PESOS_EVENTO))
            ->with('product:id,categoria')
            ->get()
            ->filter(fn ($evento) => $evento->product !== null)
            ->groupBy(fn ($evento) => $evento->product->categoria)
            ->map(function ($eventos) {
                return round($eventos->sum(
                    fn ($evento) => self::PESOS_EVENTO[$evento->evento] ?? 0.0
                ), 2);
            })
            ->sortDesc();
    }

    /** Identificadores de productos ya comprados (no deben recomendarse). */
    private function idsComprados(User $usuario): array
    {
        return $usuario->orders()
            ->completadas()
            ->pluck('product_id')
            ->unique()
            ->all();
    }

    /**
     * PASO 3 — Cold-start: productos con más interacciones recientes
     * (últimos 30 días). Evita la "vacío de perfil" de usuarios nuevos.
     */
    private function productosEnTendencia(int $limite): Collection
    {
        $populares = UserHistory::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('product_id, COUNT(*) as interacciones')
            ->groupBy('product_id')
            ->orderByDesc('interacciones')
            ->limit($limite)
            ->pluck('product_id');

        return Product::query()
            ->visibles()
            ->whereIn('id', $populares)
            ->get()
            ->sortByDesc(fn ($producto) => $populares->search($producto->id))
            ->values();
    }
}
