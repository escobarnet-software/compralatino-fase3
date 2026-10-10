<?php

/**
 * ==============================================================================
 * ARCHIVO: database/migrations/2026_10_10_000004_create_user_history_table.php
 * CAPA: Capa de Datos y Capa de Persistencia (Clases 09 y 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Bitácora de comportamiento que alimenta el BI:
 *
 * "user_history" es una tabla BITÁCORA (log) que registra las INTERACCIONES
 * del usuario con el catálogo: vistas, agregados al carrito, compras y
 * recomendaciones mostradas. Es la FUENTE DE DATOS del MOTOR DE
 * RECOMENDACIONES de la Fase III.
 *
 * 1. INTEGRIDAD REFERENCIAL EN CASCADA:
 *    Si un usuario se elimina del sistema (caso exceencial, por derecho al
 *    olvido), su historial queda huérfano; por eso se usa ON DELETE CASCADE.
 *    A diferencia de 'orders' (RESTRICT), aquí el historial ES un derivado del
 *    usuario y su consistencia es menos crítica para la contabilidad.
 *
 * 2. TABLA DE ASOCIACIÓN MANY-TO-MANY ENRIQUECIDA:
 *    users <──< user_history >──> products. No es una simple tabla pivote
 *    porque cada fila agrega ATRIBUTOS PROPIOS del evento (tipo, contexto).
 *
 * 3. EVENTO TIPO + CONTEXTO JSON:
 *    'evento' clasifica la acción (dominio cerrado/enum) y 'contexto' guarda
 *    metadatos variables (página, dispositivo, score del recomendador) sin
 *    romper la 1FN gracias a que es un valor atómico en una columna propia.
 *
 * 4. ÍNDICES COMPUESTOS:
 *    El recomendador consultará "últimos eventos del usuario" y el BI
 *    "eventos por tipo en un periodo"; los índices (user_id, evento) y
 *    (evento, created_at) evitan TABLE SCANS sobre millones de filas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_history', function (Blueprint $table) {
            $table->id();

            // FKs con cascade: el historial es un DERIVADO del usuario/producto.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Clasificación del evento: dominio cerrado controlado.
            $table->enum('evento', [
                'vista',         // el usuario visitó la ficha del producto
                'carrito',       // lo agregó al carrito (intención de compra)
                'compra',        // compró el producto (señal fuerte de preferencia)
                'recomendacion', // el motor le recomendó este producto (para medir conversión)
            ])->index();

            // Metadatos flexibles del evento (dispositivo, origen, puntaje, etc.).
            $table->json('contexto')->nullable();

            $table->timestamps();

            // ---- Consultas típicas soportadas por estos índices ----
            // • Recomendador: WHERE user_id = ? ORDER BY created_at DESC
            // • BI:          WHERE evento = 'compra' AND created_at BETWEEN ...
            $table->index(['user_id', 'evento'], 'idx_history_user_evento');
            $table->index(['evento', 'created_at'], 'idx_history_evento_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_history');
    }
};
