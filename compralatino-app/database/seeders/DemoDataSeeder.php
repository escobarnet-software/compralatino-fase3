<?php

/**
 * ==============================================================================
 * ARCHIVO: database/seeders/DemoDataSeeder.php
 * CAPA: Capa de Persistencia — Semillas de demostración (Clase 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Datos de prueba para validar la arquitectura:
 *
 * Los SEEDERS permiten materializar un ENTORNO DE DEMOSTRACIÓN reproducible
 * sin tocar datos reales. Este seeder construye un escenario completo que
 * ejercita TODAS las capas de la Fase III:
 *
 *  1. Usuarios con roles ('administrador' / 'cliente') → prueba la autorización
 *     del panel BI (Gate 'administrador').
 *  2. Catálogo MULTIPLATAFORMA (local + espejos de YAuctions) → prueba las
 *     comparativas del BI por dimensión 'plataforma'.
 *  3. Órdenes en los 4 ESTADOS de la máquina de vida ('pendiente',
 *     'en_proceso', 'completada', 'fallida') distribuidas en 30 días →
 *     alimenta el gráfico de serie temporal y el KPI de fallos.
 *  4. Órdenes 'fallida' con su JSON de AUDITORÍA simulado → prueba la
 *     bitácora de sincronización y la pantalla de auditoría del BI.
 *  5. Bitácora 'user_history' (vistas, carritos, compras y recomendaciones
 *     CON y SIN conversión) → permite demostrar el MOTOR DE RECOMENDACIONES
 *     y su KPI de tasa de conversión.
 *
 * ADVERTENCIA ACADÉMICA: nunca ejecutar seeders en producción sin revisión;
 * sobrescriben el estado de las tablas objetivo.
 */

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Punto de entrada del seeder. Usa TRANSACCIONES implícitas de Eloquent
     * por lote y fábricas de datos deterministas-ish para que cada ejecución
     * produzca un panel de BI con datos plausibles.
     */
    public function run(): void
    {
        // ---- 1. CUENTA ADMINISTRATIVA (protege el panel de BI) ----
        User::create([
            'name'                 => 'Ana Torres (Administradora BI)',
            'email'                => 'admin@compralatino.test',
            'password'             => Hash::make('secret1234'),
            'rol'                  => 'administrador',
            'plataforma_preferida' => 'yauctions',
        ]);

        // ---- 2. CLIENTES DE DEMOSTRACIÓN ----
        $nombres = [
            'Luis Ramírez', 'María Quispe', 'Carlos Medina', 'Lucía Fernández',
            'Jorge Salas', 'Carmen Rojas', 'Diego Paredes', 'Valeria Nuñez',
        ];

        $clientes = collect($nombres)->map(fn (string $nombre, int $i) => User::create([
            'name'                 => $nombre,
            'email'                => 'cliente' . ($i + 1) . '@compralatino.test',
            'password'             => Hash::make('password'),
            'rol'                  => 'cliente',
            'plataforma_preferida' => $i % 2 === 0 ? 'yauctions' : 'local',
        ]));

        // ---- 3. CATÁLOGO MULTIPLATAFORMA ----
        $productos = $this->crearCatalogo();

        // ---- 4. VENTAS SIMULADAS DE LOS ÚLTIMOS 30 DÍAS ----
        $ordenes = $this->simularVentas($clientes, $productos);

        // ---- 5. BITÁCORA DE COMPORTAMIENTO (alimenta el recomendador) ----
        $this->sembrarHistorial($clientes, $productos, $ordenes);

        $this->command?->info('Datos de demostración creados: 9 usuarios, '
            . $productos->count() . ' productos, ' . $ordenes->count() . ' órdenes.');
    }

    // =====================================================================
    // MÉTODOS PRIVADOS — fábricas de datos (encapsulamiento)
    // =====================================================================

    /**
     * CATÁLOGO MULTIPLATAFORMA: ~60% espejos de YAuctions (Japón) y ~40%
     * inventario local. Demuestra que un mismo modelo representa productos
     * de distintas tiendas (requerimiento "compras multiplataforma").
     */
    private function crearCatalogo(): \Illuminate\Support\Collection
    {
        $definiciones = [
            // [nombre, categoria, precio, plataforma]
            ['Cámara Fujifilm X-T5',           'electronica', 189000, 'yauctions'],
            ['Teclado mecánico Keychron Q1',   'electronica',  24500, 'yauctions'],
            ['Monitor LG UltraGear 27"',       'electronica',  52000, 'local'],
            ['Audífonos Sony WH-1000XM5',      'electronica',  38900, 'yauctions'],
            ['Figura Gundam RX-78 escala 1/60','coleccionismo', 31500, 'yauctions'],
            ['Funko Pop Naruto Uzumaki',       'coleccionismo',  4200, 'yauctions'],
            ['Pokémon Card Set Scarlet',       'coleccionismo', 12800, 'yauctions'],
            ['Zapatillas Nike Air Max 90',     'moda',          15900, 'local'],
            ['Chaqueta Levi’s Type III',       'moda',          22400, 'yauctions'],
            ['Reloj Casio Vintage digital',    'moda',           8900, 'yauctions'],
            ['Cafetera Chemex 6 tazas',        'hogar',          9800, 'local'],
            ['Set de sartenes T-fal 3 pzas',   'hogar',         14500, 'local'],
            ['Lámpara Muji de mesa',           'hogar',          6400, 'yauctions'],
            ['Libro: El arte de la guerra',     'libros',         1900, 'local'],
            ['Manga One Piece Vol. 105',       'libros',         1200, 'yauctions'],
            ['Bicicleta GW Lynx 29"',          'deportes',     145000, 'local'],
            ['Mancuernas ajustables 24kg',     'deportes',     11200, 'yauctions'],
            ['Tapeta de yoga eco',             'deportes',       3400, 'local'],
        ];

        return collect($definiciones)->map(fn (array $d, int $i) => Product::create([
            'sku'          => 'CL-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'nombre'       => $d[0],
            'descripcion'  => "Producto de demostración de la categoría {$d[1]}.",
            'precio'       => $d[2], // moneda única JPY para simplificar la analítica BI
            'moneda'       => 'JPY',
            'plataforma'   => $d[3],
            'external_id'  => $d[3] === 'yauctions' ? 'YA-' . (100000 + $i) : null,
            'categoria'    => $d[1],
            'stock'        => random_int(15, 80),
            'activo'       => true,
        ]));
    }

    /**
     * SIMULACIÓN DE VENTAS (30 días): genera órdenes distribuidas en el tiempo
     * con una MEZCLA REALISTA de estados:
     *   • ~75% completadas (con acuse external_reference de YAuctions)
     *   • ~10% fallidas (con JSON de auditoría de fallo de comunicación)
     *   • ~10% pendientes y ~5% en proceso (simulan la cola activa)
     * Esto alimenta el gráfico de serie temporal y el KPI de salud del BI.
     */
    private function simularVentas(\Illuminate\Support\Collection $clientes, \Illuminate\Support\Collection $productos): \Illuminate\Support\Collection
    {
        $ordenes = collect();

        foreach (range(1, 30) as $dia) {
            // Entre 1 y 4 órdenes por día (patrón de negocio no uniforme).
            foreach (range(1, random_int(1, 4)) as $i) {
                $producto = $productos->random();
                $cantidad = random_int(1, 3);
                $cliente  = $clientes->random();
                $fecha    = now()->subDays($dia - 1)->setTime(random_int(8, 20), random_int(0, 59));

                // Distribución ponderada de estados (semilla de demostración).
                $estado = match (true) {
                    $dia <= 2 && $i === 1    => Order::ESTADO_PENDIENTE, // cola reciente
                    $dia <= 2 && $i === 2    => Order::ESTADO_EN_PROCESO,
                    random_int(1, 100) <= 10 => Order::ESTADO_FALLIDA,
                    default                  => Order::ESTADO_COMPLETADA,
                };

                $orden = Order::create([
                    'user_id'        => $cliente->id,
                    'product_id'     => $producto->id,
                    'cantidad'       => $cantidad,
                    'precio_unitario'=> $producto->precio,
                    'moneda'         => $producto->moneda,
                    'total'          => $producto->precio * $cantidad,
                    'estado'         => $estado,
                    'plataforma'     => $producto->plataforma,
                ]);

                // Simular la fecha real de creación (spread para el gráfico BI).
                $orden->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();

                // AUDITORÍA Y ACUSES según el estado alcanzado.
                if ($estado === Order::ESTADO_COMPLETADA) {
                    $orden->forceFill([
                        'external_reference'   => 'YAU-' . random_int(100000, 999999),
                        'sincronizado_en'      => $fecha->copy()->addMinutes(random_int(2, 30)),
                        'datos_sincronizacion' => [[
                            'fase'     => 'sincronizacion_exitosa',
                            'fecha'    => $fecha->copy()->addMinutes(5)->toIso8601String(),
                            'intentos' => 1,
                            'detalle'  => ['respuesta' => ['status' => 201]],
                        ]],
                    ])->save();
                }

                if ($estado === Order::ESTADO_FALLIDA) {
                    // Caso emblemático de la Fase III: fallo de comunicación
                    // con la API REST registrado como bitácora de auditoría.
                    $orden->forceFill([
                        'datos_sincronizacion' => [[
                            'fase'     => 'fallo_comunicacion',
                            'fecha'    => $fecha->copy()->addMinutes(3)->toIso8601String(),
                            'intentos' => 3,
                            'detalle'  => [
                                'mensaje'  => 'YAuctions respondió con el estado HTTP 503.',
                                'contexto' => ['tipo' => 'http_error', 'status' => 503],
                            ],
                        ]],
                    ])->save();
                }

                $ordenes->push($orden);
            }
        }

        return $ordenes;
    }

    /**
     * BITÁCORA DE COMPORTAMIENTO: genera vistas, carritos, compras y
     * RECOMENDACIONES (algunas convertidas y otras no) para que el motor de
     * recomendaciones y el KPI de conversión del BI tengan datos reales.
     */
    private function sembrarHistorial(\Illuminate\Support\Collection $clientes, \Illuminate\Support\Collection $productos, \Illuminate\Support\Collection $ordenes): void
    {
        foreach ($clientes as $cliente) {
            // Vistas aleatorias (señales débiles de interés).
            foreach ($productos->random(6) as $producto) {
                UserHistory::registrar($cliente->id, $producto->id, UserHistory::EVENTO_VISTA);
            }

            // Algunos carritos (señal media de intención).
            foreach ($productos->random(2) as $producto) {
                UserHistory::registrar($cliente->id, $producto->id, UserHistory::EVENTO_CARRITO);
            }

            // Compras registradas en la bitácora (señal fuerte; refleja órdenes).
            $ordenesCliente = $ordenes->where('user_id', $cliente->id);
            foreach ($ordenesCliente->take(5) as $orden) {
                UserHistory::registrar($cliente->id, $orden->product_id, UserHistory::EVENTO_COMPRA);
            }

            // RECOMENDACIONES entregadas: algunas apuntan a productos que el
            // usuario SÍ terminó comprando (conversión real en el KPI de BI)
            // y otras no (para que la tasa no sea artificialmente del 100%).
            $convertidas = $ordenesCliente->pluck('product_id')->unique()->take(2);

            foreach ($convertidas as $productId) {
                UserHistory::registrar($cliente->id, $productId, UserHistory::EVENTO_RECOMENDACION, [
                    'puntaje' => random_int(6, 10), 'origen' => 'motor_v1', 'convertida' => true,
                ]);
            }

            foreach ($productos->random(3)->pluck('id') as $productId) {
                if (! $convertidas->contains($productId)) {
                    UserHistory::registrar($cliente->id, $productId, UserHistory::EVENTO_RECOMENDACION, [
                        'puntaje' => random_int(1, 5), 'origen' => 'motor_v1', 'convertida' => false,
                    ]);
                }
            }
        }
    }
}
