<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Models/UserHistory.php
 * CAPA: Capa de Persistencia / Modelo de Dominio (Clase 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Bitácora que alimenta inteligencia de negocios:
 *
 * Este modelo encapsula la tabla 'user_history'. Además de mapear la entidad,
 * ofrece el método estático `registrar()` que funciona como FÁBRICA DE EVENTOS:
 * cualquier capa (controladores, jobs, servicios) registrará interacciones a
 * través de él, garantizando un PUNTO ÚNICO DE VERDAD para el formato de la
 * bitácora que consumirá el RecommendationService.
 *
 * Eloquent aquí también protege los datos: solo los eventos del dominio cerrado
 * ('vista','carrito','compra','recomendacion') son válidos, y la asignación
 * masiva está restringida por $fillable.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHistory extends Model
{
    /** Eventos admitidos (dominio cerrado — evita basura en la bitácora). */
    public const EVENTO_VISTA         = 'vista';
    public const EVENTO_CARRITO       = 'carrito';
    public const EVENTO_COMPRA        = 'compra';
    public const EVENTO_RECOMENDACION = 'recomendacion';

    protected $table = 'user_history';

    protected $fillable = [
        'user_id',
        'product_id',
        'evento',
        'contexto',
    ];

    protected $casts = [
        'contexto' => 'array',
    ];

    // ---------------------------------------------------------------------
    // RELACIONES
    // ---------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ---------------------------------------------------------------------
    // FÁBRICA DE EVENTOS (punto único de registro)
    // ---------------------------------------------------------------------

    /**
     * Persiste un evento de comportamiento. Usado por StoreController y por
     * el motor de recomendaciones para trazar cada recomendación entregada
     * (permite medir su tasa de conversión en el panel BI).
     */
    public static function registrar(int $userId, int $productId, string $evento, array $contexto = []): self
    {
        return self::create([
            'user_id'   => $userId,
            'product_id'=> $productId,
            'evento'    => $evento,
            'contexto'  => $contexto,
        ]);
    }
}
