<?php

/**
 * ==============================================================================
 * ARCHIVO: database/migrations/2026_10_11_000001_add_payment_to_orders_table.php
 * CAPA: Capa de Datos (Clase 09) — Evolución del esquema relacional
 * ==============================================================================
 *
 * TEORÍA APLICADA — Alteración de esquema sin pérdida de datos:
 *
 * El negocio exige cobrar con tarjeta ANTES de ejecutar la compra. La tabla
 * 'orders' evoluciona con columnas de PAGO, aplicando dos principios:
 *
 *  1. SEGURIDAD PCI-DSS (nivel conceptual): JAMÁS se guarda el número completo
 *     de la tarjeta ni el CVV. Solo persisten datos NO sensibles: la MARCA
 *     (Visa/Mastercard), los ÚLTIMOS 4 dígitos (para que el usuario reconozca
 *     su tarjeta: "**** 4242") y la REFERENCIA del cargo devuelta por la
 *     pasarela. El PAN completo y el CVV viven solo en memoria durante el
 *     request y se descartan (defensa en profundidad).
 *
 *  2. MIGRACIÓN REVERSIBLE: 'up()' agrega columnas nullable (las órdenes
 *     históricas anteriores al checkout siguen válidas) y 'down()' las retira,
 *     permitiendo rollback seguro en cualquier entorno.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Marca detectada de la tarjeta (visa | mastercard | amex | desconocida).
            $table->string('marca_tarjeta', 20)->nullable()->after('plataforma');

            // Últimos 4 dígitos: identificación visual sin exponer el PAN.
            $table->char('tarjeta_ultimos4', 4)->nullable()->after('marca_tarjeta');

            // Referencia del cargo aprobada por la pasarela (trazabilidad).
            $table->string('referencia_pago', 120)->nullable()->after('tarjeta_ultimos4');

            // Momento en que el cobro quedó confirmado (auditoría financiera).
            $table->timestamp('pagado_en')->nullable()->after('referencia_pago');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['marca_tarjeta', 'tarjeta_ultimos4', 'referencia_pago', 'pagado_en']);
        });
    }
};
