<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Controllers/AuthController.php
 * CAPA: Capa de Presentación — Autenticación de demostración
 * ==============================================================================
 * NOTA ACADÉMICA: este controlador NO es parte del código fuente de la Fase III;
 * existe SOLO para poder ejecutar y demostrar el proyecto en local sin instalar
 * un paquete de scaffolding de autenticación (Breeze/Jetstream). Implementa un
 * login mínimo con las primitivas de sesión de Laravel.
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Formulario de inicio de sesión. */
    public function create(): View
    {
        return view('auth.login');
    }

    /** Credenciales -> sesión (patrón Guard del framework). */
    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con ningún usuario registrado.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Redirigir según el rol: administrador → panel BI; cliente → tienda.
        return redirect()->intended(
            $request->user()->esAdministrador()
                ? route('bi.dashboard')
                : route('store.index')
        );
    }

    /** Cierre de sesión con invalidación de sesión y token (anti-fixación). */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
