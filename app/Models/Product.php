<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Models/Product.php
 * CAPA: Capa de Persistencia / Modelo de Dominio (Clase 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Entidad de catálogo multiplataforma:
 *
 * El modelo Product abstrae el catálogo unificado de CompraLatino. Sus
 * comentarios y métodos demuestran:
 *
 *  • ORM/DAO: el acceso a 'products' (tabla física) se realiza vía objetos;
 *    no hay SQL disperso en el código. Las consultas se construyen con el
 *    DSL de Eloquent y se ejecutan con bindings parametrizados (anti-injection).
 *
 *  • PROTECCIÓN DE DATOS: $fillable restringe la asignación masiva; un
 *    atacante no podría enviar 'precio=0' vía formulario si 'precio' no está
 *    en la whitelist (la Capa de Negocio fija el precio de forma controlada).
 *
 *  • MULTILATAFORMA: 'plataforma' + 'external_id' identifican el producto en
 *    la tienda japonesa, permitiendo que el mismo modelo represente productos
 *    locales y espejos de YAuctions (patrón Anti-Corruption Layer ligero).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'nombre',
        'descripcion',
        'precio',
        'moneda',
        'plataforma',
        'external_id',
        'categoria',
        'stock',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'stock'  => 'integer',
        'activo' => 'boolean',
    ];

    // ---------------------------------------------------------------------
    // RELACIONES
    // ---------------------------------------------------------------------

    /** Un producto puede ser comprado en MUCHAS órdenes. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Un producto genera MUCHOS eventos en la bitácora de usuarios. */
    public function history(): HasMany
    {
        return $this->hasMany(UserHistory::class);
    }

    // ---------------------------------------------------------------------
    // SCOPES Y MÉTODOS DE DOMINIO
    // ---------------------------------------------------------------------

    /**
     * Catálogo visible: solo productos activos. Centraliza la regla "lo no
     * publicado no se muestra" en el modelo (fuente única de verdad).
     */
    public function scopeVisibles($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Regla de inventario: un producto es comprable si tiene stock y está activo.
     * El Job de compra invocará esta validación ANTES de llamar a YAuctions.
     */
    public function hayStockDisponible(int $cantidad = 1): bool
    {
        return $this->activo && $this->stock >= $cantidad;
    }

    /**
     * Precio formateado para presentación (la capa de presentación delega aquí
     * el formato del dominio monetario, evitando duplicar lógica en vistas).
     */
    public function precioFormateado(): string
    {
        return number_format((float) $this->precio, 2) . ' ' . $this->moneda;
    }

    /**
     * Fuerza de popularidad del producto en la bitácora (reciente).
     * Utilizado por RecommendationService para puntuar candidatos: un producto
     * con muchas interacciones de la comunidad es una sugerencia más segura
     * (filtrado colaborativo basado en popularidad).
     */
    public function interaccionesPopulares(int $dias = 30): float
    {
        return (float) $this->history()
            ->where('created_at', '>=', now()->subDays($dias))
            ->count();
    }
}
