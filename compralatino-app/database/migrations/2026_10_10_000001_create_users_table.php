<?php

/**
 * ==============================================================================
 * ARCHIVO: database/migrations/2026_10_10_000001_create_users_table.php
 * CAPA: Capa de Datos y Capa de Persistencia (Clases 09 y 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Diseño de bases de datos relacionales:
 *
 * Esta migración materializa la tabla "users" (entidad central de la BD) y
 * demuestra los principios del modelo relacional:
 *
 * 1. INTEGRIDAD DE ENTIDAD: la columna "id" es la CLAVE PRIMARIA (PK). Garantiza
 *    que cada registro sea identificable de forma única e inequívoca, evitando
 *    filas duplicadas o ambiguas (Primer Forma Normal implícita).
 *
 * 2. INTEGRIDAD DE REFERENCIAL: otras tablas (orders, user_history) referenciarán
 *    a esta tabla mediante CLAVES FORÁNEAS (Foreign Keys) con restricciones
 *    ON DELETE / ON UPDATE. El motor relacional impedirá que existan "huérfanos":
 *    no podrá existir una orden sin un usuario válido en el sistema.
 *
 * 3. INTEGRIDAD DE DOMINIO: las columnas tienen TIPOS ESTRUCTURADOS (string,
 *    enum, timestamp) que restringen los valores admisibles a nivel de storage,
 *    y "email" posee un índice ÚNICO que imparte la restricción de unicidad
 *    a nivel físico (no solo lógico).
 *
 * 4. MULTILATAFORMA (Requerimiento Fase III): "plataforma_preferida" registra la
 *    tienda externa asociada al usuario (ej. "yauctions"), permitiendo que el
 *    mismo cuenta de usuario opere sobre distintos marketplaces —una tienda
 *    central (monolito) como fachada de varias plataformas—.
 *
 * 5. SEGURIDAD: la contraseña se almacena hasheada (string 255). Laravel nunca
 *    persiste contraseñas en texto plano; esto se complementará en la Capa de
 *    Negocio con hashing al momento de crear la cuenta.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la creación de la tabla users.
     * Los métodos de Schema facade abstraen el DDL SQL (CREATE TABLE) según el
     * driver configurado (MySQL / PostgreSQL / SQLite), aportando portabilidad.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // ---------- CLAVE PRIMARIA ----------
            // Identificador surrogate key autoincremental: PK de la entidad.
            $table->id();

            // ---------- ATRIBUTOS DEL DOMINIO ----------
            $table->string('name', 120);
            // Índice UNIQUE: refuerza la integridad de entidad (un correo = una cuenta).
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Hash bcrypt/argon generado por la Capa de Negocio; nunca texto plano.
            $table->string('password');

            // ---------- MULTILATAFORMA (Fase III) ----------
            // Rol de autorización: separa clientes del personal administrativo (BI).
            $table->enum('rol', ['cliente', 'administrador'])->default('cliente');

            // Tienda externa preferida del usuario; NULL = solo compra local.
            $table->enum('plataforma_preferida', ['local', 'yauctions'])->nullable();

            // ---------- METADATOS DE AUTENTICACIÓN ----------
            $table->rememberToken();
            $table->timestamps(); // created_at y updated_at (auditoría temporal)
        });

        // ---------------------------------------------------------------
        // Tablas de soporte del framework (adaptación del entorno de
        // demostración): la sesión se guarda en BD (SESSION_DRIVER=database)
        // y Laravel necesita la tabla de tokens de restablecimiento.
        // ---------------------------------------------------------------
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reversión segura de la migración (rollback).
     * Se elimina la tabla completa; las FKs dependientes deben revertirse antes
     * (Laravel invoca los rollbacks en orden inverso de creación).
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
