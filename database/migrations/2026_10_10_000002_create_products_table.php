<?php

/**
 * ==============================================================================
 * ARCHIVO: database/migrations/2026_10_10_000002_create_products_table.php
 * CAPA: Capa de Datos y Capa de Persistencia (Clases 09 y 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Catálogo multiplataforma:
 *
 * CompraLatino es un intermediario: sus "products" pueden ser de dos orígenes:
 *   • 'local'     → inventario propio (se conoce por completo su ficha).
 *   • 'yauctions' → espejo del catálogo de la tienda externa japonesa; solo se
 *                   conoce parte de la información y el registro es UN PROXY
 *                   (espía) sincronizado en segundo plano vía Jobs.
 *
 * Decisiones de diseño relacional documentadas:
 *
 * 1. SKU ÚNICO: la clave de negocio (business key) tiene restricción UNIQUE
 *    para impedir duplicados lógicos, independiente de la PK surrogate.
 *
 * 2. PRECIO con DECIMAL(10,2): los valores monetarios NUNCA se almacenan en
 *    tipos FLOAT (punto flotante), porque producen errores de redondeo
 *    (0.1 + 0.2 ≠ 0.3). DECIMAL es aritmética exacta.
 *
 * 3. MONEDA ISO-4217 (3 caracteres): al operar con Japón se almacena la
 *    moneda original (JPY) para evitar pérdidas por conversión temprana; la
 *    conversión será responsabilidad de la Capa de Negocio.
 *
 * 4. external_id: referencia al recurso en el sistema de YAuctions; permite la
 *    DESACOPLACIÓN INTEGRAL entre el monolito y la API externa (los Jobs la
 *    usarán como parámetro de sincronización).
 *
 * 5. stock y activo: atributos de control de negocio implementados como
 *    columnas atómicas (sin estructura anidada), respetando la 1FN.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Clave de negocio única del producto (1FN: sin repetidos).
            $table->string('sku', 64)->unique();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();

            // ---- Dominio monetario: precisión exacta y moneda explícita ----
            $table->decimal('precio', 10, 2);
            $table->char('moneda', 3)->default('JPY');

            // ---- Proveniencia multiplataforma (Fase III) ----
            $table->enum('plataforma', ['local', 'yauctions'])->default('local');
            // ID del producto en la tienda externa; NULL cuando es inventario local.
            $table->string('external_id', 64)->nullable()->index();

            // Categoría usada por el MOTOR DE RECOMENDACIONES (interés del usuario).
            $table->string('categoria', 80)->index();

            // Control de inventario y publicación.
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
