<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Models/Order.php
 * CAPA: Capa de Persistencia / Modelo de Dominio (Clase 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Eloquent como ORM / DAO:
 *
 * Eloquent es el ORM (Object-Relational Mapper) de Laravel. Actúa como un
 * DAO (Data Access Object) de altísimo nivel porque:
 *
 *  1. ABSTRACCIÓN: el desarrollador manipula OBJETOS PHP (`$order->estado`)
 *     y Eloquent traduce operaciones a sentencias SQL generadas para el driver
 *     configurado. La Capa de Negocio NUNCA escribe SQL a mano → los modelos
 *     son el FRENTE oficial de la base de datos.
 *
 *  2. PROTECCIÓN DE DATOS (seguridad):
 *     • $fillable implementa MASS-ASSIGNMENT PROTECTION: solo los atributos
 *       listados admiten asignación masiva. Sin esto, un atacante podría
 *       enviar `?rol=administrador` y escalar privilegios.
 *     • Las consultas usan BINDINGS PARAMETRIZADOS (prepared statements),
 *       neutralizando por diseño la INYECCIÓN SQL.
 *     • $hidden impide fuga de datos sensibles en serializaciones JSON/Blade.
 *
 *  3. INTEGRIDAD REFERENCIAL EN EL DOMINIO: `belongsTo()` / `hasMany()`
 *     mapean las FK de la BD a relaciones de objetos (grafo relacional sin
 *     JOINs manuales).
 *
 *  4. MÁQUINA DE ESTADOS: `estaSincronizada()`, `puedeSincronizarse()` y los
 *     scopes encapsulan el ciclo de vida de la orden (PRINCIPIO SRP).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    /**
     * WHITELIST de asignación masiva: cualquier clave fuera de esta lista es
     * descartada por Eloquent al usar Order::create($request->validated()),
     * cerrando la brecha de mass-assignment desde la capa de presentación.
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'cantidad',
        'precio_unitario',
        'moneda',
        'total',
        'estado',
        'plataforma',
        'external_reference',
        'datos_sincronizacion',
        'sincronizado_en',
    ];

    /** CASTS: convierten tipos de BD en tipos PHP de dominio. */
    protected $casts = [
        'precio_unitario'      => 'decimal:2',
        'total'                => 'decimal:2',
        'datos_sincronizacion' => 'array', // auditoría JSON ↔ array PHP
        'sincronizado_en'      => 'datetime',
    ];

    /** Nunca exponer la auditoría interna en respuestas serializadas. */
    protected $hidden = [
        'datos_sincronizacion',
    ];

    // ---- CONSTANTES DE DOMINIO (Clean Code: sin "strings mágicos") ----

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_COMPLETADA = 'completada';
    public const ESTADO_FALLIDA    = 'fallida';

    // ---- RELACIONES (mapeo FK → grafo de objetos) ----

    /** Muchas órdenes pertenecen a UN usuario (FK: orders.user_id). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Muchas órdenes referencian UN producto (FK: orders.product_id). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ---- SCOPES (DSL de Eloquent; sustituyen SQL crudo) ----

    /** Filtra ventas efectivas; base de los KPIs del panel BI. */
    public function scopeCompletadas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_COMPLETADA);
    }

    /** Rango temporal: sustenta los cuadros de mando mensuales de BI. */
    public function scopeDelPeriodo(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    // ---- MÉTODOS DE DOMINIO (comportamiento, no solo datos) ----

    /** ¿La orden tiene acuse confirmado por la tienda externa? */
    public function estaSincronizada(): bool
    {
        return $this->estado === self::ESTADO_COMPLETADA
            && ! empty($this->external_reference);
    }

    /**
     * Regla: 'pendiente' inicia el proceso; 'en_proceso' permite CONTINUARLO
     * (los reintentos del worker de Laravel re-ejecutan handle() sobre la
     * misma orden: si 'en_proceso' no fuera sincronizable, el propio reintento
     * se rechazaría a sí mismo). Solo 'completada' es terminal e inmutable
     * (evita dobles cargos); 'fallida' también puede reintentarse.
     */
    public function puedeSincronizarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_EN_PROCESO, self::ESTADO_FALLIDA], true);
    }

    /**
     * Registra la auditoría de la comunicación con la API REST (Fase III).
     * Centraliza la escritura del JSON de auditoría en UN solo punto:
     * todo intento de sincronización —exitoso o fallido— queda trazable para
     * el panel de BI y para la toma de decisiones administrativas.
     */
    public function registrarAuditoria(string $fase, array $datos, int $intentos = 1): void
    {
        $auditoria           = $this->datos_sincronizacion ?? [];
        $auditoria[]         = [
            'fase'     => $fase,
            'fecha'    => now()->toIso8601String(),
            'intentos' => $intentos,
            'detalle'  => $datos,
        ];

        // forceFill + save: escritura controlada desde la Capa de Negocio.
        $this->forceFill(['datos_sincronizacion' => $auditoria])->save();
    }
}
