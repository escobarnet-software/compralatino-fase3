<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Jobs/ProcessYAuctionPurchase.php
 * CAPA: Capa de Negocio — Colas y Excepciones (Clase 11)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Procesamiento asíncrono y resiliencia transaccional:
 *
 * Este JOB implementa el corazón de la arquitectura de CompraLatino: el
 * usuario recibe respuesta INMEDIATA en la interfaz y la comunicación con la
 * API REST de YAuctions (Japón) ocurre EN SEGUNDO PLANO mediante el sistema
 * de colas de Laravel (Redis/database driver + workers).
 *
 *  1. PATRÓN QUEUE OBJECT: el job encapsula una unidad de trabajo
 *     reanudable. `ShouldQueue` lo encola; los workers lo ejecutan fuera del
 *     ciclo de vida de la petición HTTP.
 *
 *  2. MÁXIMA INTEGRIDAD CON DB::transaction(): las escrituras sobre 'orders'
 *     se ejecutan dentro de transacciones ATÓMICAS (ACID). Si algo falla a
 *     mitad, el ROLLBACK devuelve la BD al estado consistente anterior — nunca
 *     quedarán órdenes a medias entre la BD local y la tienda externa.
 *
 *  3. MANEJO DE EXCEPCIONES DE DOMINIO: el bloque try/catch captura
 *     YAuctionCommunicationException (excepción tipada de negocio) y la
 *     registra como AUDITORÍA en orders.datos_sincronizacion, marcando la
 *     orden como 'fallida' para su posterior reproceso (patrón Retry).
 *
 *  4. REINTENTOS CON BACKOFF EXPONENCIAL: `tries` y `backoff()` implementan
 *     tolerancia a fallos transitorios de red (patrón de resiliencia).
 *
 *  5. FALLBACK: el método `failed()` se invoca cuando se agotan los intentos;
 *     deja la orden en estado terminal 'fallida' con auditoría completa,
 *     listo para que un administrador lo revise desde el panel de BI.
 */

namespace App\Jobs;

use App\Exceptions\YAuctionCommunicationException;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class ProcessYAuctionPurchase implements ShouldQueue
{
    // Traits del framework: serialización, cola, reintentos y despacho.
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * REINTENTOS: número máximo de ejecuciones del job.
     * Se acopla con backoff() para esperas crecientes entre intentos.
     */
    public int $tries = 3;

    /**
     * La orden se serializa por IDENTIFICADOR en la cola (SerializesModels),
     * evitando el "fat serialization" y permitiendo re-leerla en estado fresco.
     *
     * NOTA: se usa onQueue() en el constructor (patrón oficial de Laravel) para
     * asignar la cola. NO debe definirse un método queue(): Laravel determina el
     * nombre de la cola mediante la propiedad $queue del trait Queueable o el
     * método onQueue(); un método queue() sería ignorado y el job caería en la
     * cola por defecto ('default').
     */
    public function __construct(
        public Order $order
    ) {
        // Cola dedicada: permite escalar workers especializados (Fase II/III).
        $this->onQueue('compras-yauctions');
    }

    /**
     * Espera exponencial entre reintentos (segundos):
     * intento 1 → 30s, intento 2 → 120s, intento 3 → 300s.
     * Estrategia estándar para no saturar la API externa ante caídas.
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * PUNTO DE EJECUCIÓN del job (lo invoca el worker de la cola).
     *
     * FLUJO RESILIENTE implementado:
     *   [Transacción 1] bloquear orden + validar reglas de negocio
     *          ↓
     *   [HTTP] llamada a la API REST de YAuctions (FUERA de transacción)
     *          ↓
     *   [Transacción 2] persistir resultado + auditoría (commit/rollback)
     *
     * NOTA ACADÉMICA: la llamada HTTP se realiza deliberadamente FUERA de la
     * transacción DB. Mantener un bloqueo (row-lock) abierto durante una
     * llamada externa de segundos violaría las buenas prácticas ACID
     * (largas transacciones = contención, deadlocks y timeouts de BD).
     */
    public function handle(): void
    {
        // ---------------------------------------------------------------
        // FASE 1 — TRANSACCIÓN: marcar 'en_proceso' y validar invariantes.
        // DB::transaction garantiza atomicidad: si algo falla, ROLLBACK.
        // ---------------------------------------------------------------
        try {
            DB::transaction(function () {
                // lockForUpdate(): bloqueo PESSIMISTA de la fila; evita que
                // dos workers procesen la misma orden simultáneamente
                // (condición de carrera clásica en colas).
                $orden = Order::whereKey($this->order->id)->lockForUpdate()->firstOrFail();

                if (! $orden->puedeSincronizarse()) {
                    // Excepción de DOMINIO: regla de negocio violada.
                    throw YAuctionCommunicationException::porReglaDeNegocio(
                        "La orden #{$orden->id} ya fue procesada (estado: {$orden->estado}).",
                        ['orden_id' => $orden->id]
                    );
                }

                $orden->forceFill(['estado' => Order::ESTADO_EN_PROCESO])->save();
                $orden->registrarAuditoria('inicio_sincronizacion', [
                    'plataforma' => $orden->plataforma,
                    'producto'   => $orden->product_id,
                ], $this->attempts() ?: 1);
            });
        } catch (YAuctionCommunicationException $e) {
            // La transacción ya hizo ROLLBACK automáticamente; se audita el fallo.
            $this->auditarFalla($e);
            throw $e; // se propaga para que el gestor de colas registre el intento.
        }

        // ---------------------------------------------------------------
        // FASE 2 — LLAMADA A LA API REST EXTERNA (sin transacción abierta).
        // ATAJO MULTIPLATAFORMA (Fase III): las órdenes 'local' NO viajan a
        // Japón; se confirman directamente (venta doméstica inmediata). Solo
        // las órdenes 'yauctions' consumen la API REST externa.
        // ---------------------------------------------------------------
        try {
            $ordenActual = Order::whereKey($this->order->id)->firstOrFail();

            if ($ordenActual->plataforma !== 'yauctions') {
                DB::transaction(function () use ($ordenActual) {
                    $o = Order::whereKey($ordenActual->id)->firstOrFail();
                    $o->forceFill([
                        'estado'             => Order::ESTADO_COMPLETADA,
                        'external_reference' => 'LOCAL-' . $o->id,
                        'sincronizado_en'    => now(),
                    ])->save();
                    $o->registrarAuditoria('venta_local_confirmada', [
                        'plataforma' => $o->plataforma,
                        'nota'       => 'Sin llamada externa: inventario propio.',
                    ], $this->attempts() ?: 1);
                });

                return;
            }

            $payload = $this->construirPayload();

            try {
                // Http facade: cliente HTTP con timeouts y token de servicio.
                // ESTRATEGIA POR DESTINO (requisito Fase III "ejecutar compras
                // desde una interfaz prevista por YAuctions"): si la URL base
                // apunta a ESTA misma app, se consume la INTERFAZ SIMULADA
                // local (POST /simulador-yauctions/comprar); si apunta a un
                // host externo real, se consume la API japonesa
                // (POST /api/v1/purchases). Ambos caminos comparten el mismo
                // try/catch + auditoría (fail-safe idéntico).
                $baseUrl = (string) config('services.yauctions.url');
                $esSimuladorLocal = str_contains($baseUrl, '127.0.0.1:8000')
                    || str_contains($baseUrl, 'localhost:8000');

                if ($esSimuladorLocal) {
                    // Interfaz prevista por YAuctions (simulador local).
                    $respuesta = Http::baseUrl($baseUrl)
                        ->timeout(15)
                        ->post('/simulador-yauctions/comprar', $payload);
                } else {
                    // API REST real de la tienda japonesa.
                    $respuesta = Http::baseUrl($baseUrl)
                        ->withToken(config('services.yauctions.token'))
                        ->timeout(15)
                        ->post('/api/v1/purchases', $payload);
                }
            } catch (ConnectionException $conexion) {
                // Un fallo de RED (host inaccesible, timeout, DNS) lo convertimos
                // en una EXCEPCIÓN DE DOMINIO tipada. Así el catch externo la
                // audita igual que un error HTTP y queda trazable en el BI
                // (defensa: toda falla de comunicación debe quedar registrada).
                throw YAuctionCommunicationException::porTimeout($payload, $conexion);
            } catch (Throwable $conexion) {
                // NOTA: Guzzle puede lanzar ConnectException directamente (sin
                // envolver en ConnectionException de Laravel) ante un host
                // inaccesible. Este catch genérico garantiza que NINGÚN fallo
                // de red escape sin convertirse en excepción de dominio
                // auditada (fail-safe: mejor auditar de más que perder traza).
                if ($this->esFalloDeConexion($conexion)) {
                    throw YAuctionCommunicationException::porTimeout($payload, $conexion);
                }
                throw $conexion;
            }

            if ($respuesta->failed()) {
                // Convierte el fallo HTTP en EXCEPCIÓN DE DOMINIO tipada.
                throw YAuctionCommunicationException::porErrorHttp(
                    $respuesta->status(),
                    $respuesta->json(),
                    $payload
                );
            }

            $referenciaExterna = $respuesta->json('purchase_id')
                ?? $respuesta->json('external_reference');

            // ---------------------------------------------------------------
            // FASE 3 — TRANSACCIÓN: confirmar la orden con su acuse externo.
            // Solo aquí la BD refleja el éxito COMPLETO del proceso (commit).
            // ---------------------------------------------------------------
            DB::transaction(function () use ($referenciaExterna, $payload, $respuesta) {
                $orden = Order::whereKey($this->order->id)->firstOrFail();

                $orden->forceFill([
                    'estado'             => Order::ESTADO_COMPLETADA,
                    'external_reference' => (string) $referenciaExterna,
                    'sincronizado_en'    => now(),
                ])->save();

                $orden->registrarAuditoria('sincronizacion_exitosa', [
                    'enviado'   => $payload,
                    'respuesta' => $respuesta->json(),
                ], $this->attempts() ?: 1);
            });
        } catch (YAuctionCommunicationException $e) {
            // Excepción de NEGOCIO: API inaccesible, HTTP 4xx/5xx o regla violada.
            // Se audita el detalle completo para el panel de BI y se relanza
            // para activar el mecanismo de REINTENTOS (backoff exponencial).
            $this->auditarFalla($e);
            throw $e;
        } catch (Throwable $e) {
            // Excepción TÉCNICA no prevista (red caída, BD no disponible...).
            Log::critical('Fallo técnico inesperado en sincronización YAuctions', [
                'orden_id' => $this->order->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * CALLBACK DE FALLO TERMINAL: se ejecuta cuando se agotan los $tries.
     * Deja la orden en estado 'fallida' CONSISTENTE y con auditoría completa,
     * habilitando la supervisión administrativa desde el panel de BI.
     */
    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception) {
            $orden = Order::find($this->order->id);
            if (! $orden) {
                return;
            }

            $orden->forceFill(['estado' => Order::ESTADO_FALLIDA])->save();
            $orden->registrarAuditoria('fallo_terminal', [
                'mensaje'                => $exception?->getMessage() ?? 'Desconocido',
                'intentos'               => $this->tries,
                'requiere_revisacion'    => true,
            ], $this->tries);
        });

        Log::error('Orden marcada como FALLIDA tras agotar reintentos', [
            'orden_id' => $this->order->id,
        ]);
    }

    // =====================================================================
    // MÉTODOS PRIVADOS — helpers de encapsulamiento (Clean Code)
    // =====================================================================

    /**
     * Construye el DTO (Data Transfer Object) que viajará por la red.
     * Se serializa SOLO lo necesario: nunca se envía el objeto Eloquent
     * completo (principio de mínimo conocimiento / no exponer más datos).
     */
    private function construirPayload(): array
    {
        return [
            'external_product_id' => $this->order->product?->external_id,
            'quantity'            => $this->order->cantidad,
            'unit_price'          => (float) $this->order->precio_unitario,
            'currency'            => $this->order->moneda,
            'buyer_reference'     => "CL-{$this->order->user_id}-{$this->order->id}",
        ];
    }

    /**
     * ¿La excepción proviene de la capa de RED (DNS, TCP, TLS, timeout)?
     * Detecta las clases de Guzzle/HttpClient por nombre para no acoplar
     * el Job a imports concretos del driver HTTP (bajo acoplamiento).
     */
    private function esFalloDeConexion(Throwable $e): bool
    {
        $clase = get_class($e);

        return str_contains($clase, 'ConnectException')
            || str_contains($clase, 'ConnectionException')
            || str_contains($clase, 'TimeoutException')
            || str_contains($clase, 'TransferException');
    }

    /**
     * Registra la data de AUDITORÍA del fallo de comunicación (requisito Fase III):
     * qué se envió, qué respondió la tienda y por qué se falló. Todo persistido
     * en la propia orden (JSON) + log del framework para trazabilidad técnica.
     */
    private function auditarFalla(YAuctionCommunicationException $e): void
    {
        $orden = Order::find($this->order->id);
        if ($orden) {
            $orden->registrarAuditoria('fallo_comunicacion', [
                'mensaje'  => $e->getMessage(),
                'contexto' => $e->contexto(),
                'intento'  => $this->attempts() ?: 1,
            ], $this->attempts() ?: 1);
        }

        Log::warning('Fallo de comunicación con YAuctions auditado', [
            'orden_id' => $this->order->id,
            'contexto' => $e->contexto(),
        ]);
    }
}
