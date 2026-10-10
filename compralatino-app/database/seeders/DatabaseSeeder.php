<?php

/**
 * ==============================================================================
 * ARCHIVO: database/seeders/DatabaseSeeder.php
 * CAPA: Capa de Persistencia — Seeder raíz (Clase 10)
 * ==============================================================================
 * El DatabaseSeeder es el PUNTO ÚNICO de orquestación de las semillas:
 * `php artisan db:seed` invoca exclusivamente este archivo, que delega en los
 * seeders específicos (patrón Composite ligero — una sola fuente de verdad
 * del orden de siembra, evitando dependencias implícitas).
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Registro de seeders a ejecutar en orden determinista.
     */
    public function run(): void
    {
        // Datos de demostración de la Fase III (usuarios, catálogo,
        // órdenes multi-estado y bitácora para el panel de BI).
        $this->call(DemoDataSeeder::class);
    }
}
