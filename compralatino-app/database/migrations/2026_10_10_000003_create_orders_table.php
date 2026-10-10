<?php

/**
 * ==============================================================================
 * ARCHIVO: database/migrations/2026_10_10_000003_create_orders_table.php
 * CAPA: Capa de Datos y Capa de Persistencia (Clases 09 y 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Entidad débil/transaccional y ciclo de vida asíncrono:
 *
 * La tabla "orders" es el CORAZÓN TRANSACCIONAL del e-commerce. Sus claves
 * foráneas (FK) implementan la INTEGRIDAD REFERENCIAL del modelo relacional:
 *
 *   user_id    → REFERENCES users(id)   ON DELETE RESTRICT
 *   product_id → REFERENCES products(id) ON DELETE RESTRICT
 *
 * Se eligió RESTRICT (y no CASCADE) deliberadamente: en un sistema real NO se
 * debe borrar un usuario que tenga historial de compras, ni un producto que
 * tenga órdenes; la restricción obliga a resolver la dependencia primero
 * (ej. desactivar el producto con 'activo = false' en lugar de eliminarlo).
 * Así el motor de BD actúa como ÚLTIMA LÍNEA DE DEFENSA de la integridad.
 *
 * CICLO DE VIDA ASÍNCRONO (característica central de CompraLatino, Fase II):
 *   pendiente → en_proceso → completada | fallida
 * La columna 'estado' implementa una MÁQUINA DE ESTADOS en la columna; cada
 * transición será validada en la Capa de Negocio (Modelo + Job).
 *
 * SINCRONIZACIÓN CON YAuctions:
 *   • external_reference: acuse (recibo) devuelto por la API japonesa.
 *   • datos_sincronizacion (JSON): BITÁCORA DE AUDITORÍA. Si la comunicación
 *     con la API REST falla, aquí se registra qué se envió, qué se recibió,
 *     el error y los intentos — requisito explícito de la Fase III.
 *   • sincronizado_en: marca temporal de la última comunicación exitosa.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ---- INTEGRIDAD REFERENCIAL (FK) ----
            // Restringido: no se puede borrar la raíz mientras existan hijos.
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            // ---- LÍNEA ECONÓMICA DE LA ORDEN ----
            $table->unsignedInteger('cantidad');
            // Se congela el precio al momento de comprar: si el catálogo cambia
            // después, la orden conserva su valor original (auditabilidad).
            $table->decimal('precio_unitario', 10, 2);
            $table->char('moneda', 3)->default('JPY');
            $table->decimal('total', 12, 2);

            // ---- MÁQUINA DE ESTADOS ----
            $table->enum('estado', [
                'pendiente',   // creada, aún no procesada por el Job
                'en_proceso',  // Job en ejecución, llamada a YAuctions en curso
                'completada',  // sincronizada exitosamente con la tienda externa
                'fallida',     // error de comunicación o de regla de negocio
            ])->default('pendiente')->index();

            // ---- AUDITORÍA DE SINCRONIZACIÓN (Fase III) ----
            $table->enum('plataforma', ['local', 'yauctions'])->default('yauctions');
            $table->string('external_reference', 120)->nullable();
            // JSON: flexible para guardar payloads arbitrarios de la API REST.
            $table->json('datos_sincronizacion')->nullable();
            $table->timestamp('sincronizado_en')->nullable();

            $table->timestamps();

            // ---- ÍNDICES COMPUESTOS (rendimiento + analítica BI) ----
            // Aceleran los consultorios del panel BI: "ventas por estado",
            // "ventas por plataforma en un periodo", etc.
            $table->index(['estado', 'created_at'], 'idx_orders_estado_fecha');
            $table->index(['plataforma', 'created_at'], 'idx_orders_plataforma_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
