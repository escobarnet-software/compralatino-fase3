<?php

/**
 * ==============================================================================
 * ARCHIVO: routes/web.php
 * CAPA: Capa de Presentación — Enrutamiento (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — El ruter (mediador) del patrón MVC:
 *
 * Las rutas son el PUNTO DE ENTRADA de la arquitectura: mapean una URL + un
 * verb HTTP a un MÉTODO DE CONTROLADOR. Esto permite:
 *
 *  • DESACOPLAMIENTO: la URL pública es estable aunque cambie la
 *    implementación interna del controlador (facade del patrón MVC).
 *  • SEGURIDAD POR CAPAS: los middlewares 'auth' y 'can:administrador'
 *    actúan como FILTROS PREVIOS: si el usuario no está autenticado o no es
 *    administrador, NUNCA llega a la Capa de Negocio (defensa en profundidad).
 *  • RESOURCE ROUTES: Route::resource genera el CRUD RESTful completo,
 *    estandarizando verbs (GET/POST) y URLs (plural, sin verbos).
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiDashboardController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\YAuctionsFakeController;
use Illuminate\Support\Facades\Route;

/*
 * --------------------------------------------------------------------------
 * AUTENTICACIÓN (demostración local — no forma parte del código académico)
 * --------------------------------------------------------------------------
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');

    // Alta de cuentas multiplataforma (Fase III): formulario + creación.
    Route::get('/registro', [RegisterController::class, 'create'])->name('register');
    Route::post('/registro', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * --------------------------------------------------------------------------
 * MÓDULO DE TIENDA (Capa de presentación — cliente final)
 * --------------------------------------------------------------------------
 * 'auth' → solo usuarios autenticados compran (regla de negocio básica).
 */
Route::middleware('auth')->group(function () {
    // Catálogo público con recomendaciones personalizadas (Fase III).
    Route::get('/', [StoreController::class, 'index'])->name('store.index');

    // Ficha de producto: registra evento 'vista' en la bitácora BI.
    Route::get('/productos/{product}', [StoreController::class, 'show'])
        ->name('store.show');

    // Procesar compra: valida con StoreOrderRequest y despacha el Job en cola.
    Route::post('/ordenes', [StoreController::class, 'store'])
        ->name('orders.store');

    // Historial de compras propias (protegido contra IDOR por el scope).
    Route::get('/mis-ordenes', [StoreController::class, 'myOrders'])
        ->name('orders.index');

    // Recomendaciones personalizadas (motor basado en historial, Fase III).
    Route::get('/para-ti', [StoreController::class, 'recommendations'])
        ->name('store.recommendations');

    // Seguimiento de una orden propia (protegido contra IDOR por el scope).
    Route::get('/ordenes/{ordenId}', [StoreController::class, 'showOrder'])
        ->whereNumber('ordenId')
        ->name('orders.show');
});

/*
 * --------------------------------------------------------------------------
 * INTERFAZ PREVISTA POR YAUCTIONS (simulador local de la tienda japonesa)
 * --------------------------------------------------------------------------
 * Requisito Fase III: "la plataforma debe poder ejecutar las compras de los
 * usuarios desde una interfaz prevista por YAuctions". Contrato:
 *   GET  /simulador-yauctions/catalogo?query=... → catálogo externo (JSON).
 *   POST /simulador-yauctions/comprar            → ejecuta la compra (JSON).
 * El Job usa esta interfaz cuando YACTIONS_URL apunta a esta app; en
 * producción apuntaría a la API real japonesa (mismo contrato).
 */
Route::get('/simulador-yauctions/catalogo', [YAuctionsFakeController::class, 'catalogo'])
    ->name('yauctions.catalogo');
Route::post('/simulador-yauctions/comprar', [YAuctionsFakeController::class, 'comprar'])
    ->name('yauctions.comprar');

/*
 * --------------------------------------------------------------------------
 * MÓDULO DE INTELIGENCIA DE NEGOCIOS (BI) — restringido al área administrativa
 * --------------------------------------------------------------------------
 * 'can:administrador' → Gate/Policy: política de autorización centralizada.
 * La Capa de Presentación protege el panel ANTES de invocar la Capa de
 * Negocio (los KPIs son información sensible del negocio).
 */
Route::middleware(['auth', 'can:administrador'])->prefix('admin')->name('bi.')->group(function () {
    // Panel principal: KPIs, gráficos y rankings de decisión.
    Route::get('/dashboard', [BiDashboardController::class, 'index'])
        ->name('dashboard');

    // Bitácora de auditoría: órdenes fallidas con detalle de sincronización.
    Route::get('/auditoria', [BiDashboardController::class, 'auditoria'])
        ->name('auditoria');
});
