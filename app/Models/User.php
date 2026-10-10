<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Models/User.php
 * CAPA: Capa de Persistencia / Modelo de Dominio (Clase 10)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Modelo anfitrión de Laravel:
 *
 * Eloquent abstrae la tabla 'users' como objeto de dominio. Además de la
 * autenticación estándar, este modelo expone las RELACIONES que sustentan el
 * requerimiento de la Fase III "compras multiplataforma" y el "motor de
 * recomendaciones":
 *
 *   • orders()   → historial de compras del usuario (alimenta el BI).
 *   • history()  → bitácora de interacciones (alimenta el recomendador).
 *
 * Seguridad: 'password' se oculta ($hidden) y nunca puede asignarse masivamente
 * fuera de un flujo controlado; el hash se genera con Hash::make() en la Capa
 * de Negocio, cumpliendo defensa en profundidad.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** Solo estos atributos admiten asignación masiva (protección ORM). */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'plataforma_preferida',
    ];

    /** Contraseña jamás visible en vistas ni respuestas JSON. */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELACIONES (integridad referencial proyectada al dominio)
    // ---------------------------------------------------------------------

    /** Un usuario acumula MUCHAS órdenes de compra. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Un usuario genera MUCHOS eventos de comportamiento (bitácora BI). */
    public function history(): HasMany
    {
        return $this->hasMany(UserHistory::class);
    }

    // ---------------------------------------------------------------------
    // MÉTODOS DE DOMINIO
    // ---------------------------------------------------------------------

    /**
     * Regla de autorización: ¿el usuario pertenece al área administrativa?
     * Usada por el middleware 'can:administrador' que protege el panel BI,
     * demostrando separación de capas: el CONTROLADOR no valida permisos a mano.
     */
    public function esAdministrador(): bool
    {
        return $this->rol === 'administrador';
    }

    /**
     * Tienda externa por defecto para nuevas compras (multiplataforma).
     */
    public function plataformaPorDefecto(): string
    {
        return $this->plataforma_preferida ?? 'local';
    }
}
