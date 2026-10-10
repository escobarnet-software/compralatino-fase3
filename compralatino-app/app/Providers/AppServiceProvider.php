<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * GATE DE AUTORIZACIÓN — Capa de Presentación (Clase 12).
         * Define la política 'administrador' usada por el middleware
         * can:administrador que protege el panel de BI. Centraliza la regla
         * "quién puede ver inteligencia de negocios" en un único punto
         * (fuente única de verdad de la política de acceso).
         */
        Gate::define('administrador', fn (User $usuario) => $usuario->esAdministrador());
    }
}
