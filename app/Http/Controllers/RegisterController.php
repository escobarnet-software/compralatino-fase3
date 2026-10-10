<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Controllers/RegisterController.php
 * CAPA: Capa de Presentación — Alta de cuentas multiplataforma (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Controlador delgado (patrón MVC estricto):
 *
 *  1. VALIDACIÓN EN FRONTERA: StoreRegisterRequest ya validó y autorizó; aquí
 *     solo se usa $request->validated() (datos saneados). El controlador NO
 *     valida a mano (SRP: cada capa hace su trabajo).
 *
 *  2. SEGREGACIÓN DE RESPONSABILIDAD: el hash de la contraseña (Hash::make) es
 *     la ÚNICA transformación permitida aquí; el resto (perfil, bitácora
 *     inicial) vive en el modelo / Capa de Negocio, no en el controlador.
 *
 *  3. ROL POR DEFECTO SEGURO: toda cuenta creada por este flujo nace como
 *     'cliente'. El rol 'administrador' solo puede otorgarse por siembra o por
 *     un flujo admin dedicado (prevención de escalada de privilegios).
 *
 *  4. PRG + SESIÓN: tras crear la cuenta se inicia sesión y se redirige (Post/
 *     Redirect/Get evita el reenvío duplicado del formulario con F5).
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /** Formulario público de alta de cuenta. */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Alta de cuenta multiplataforma: crea el usuario, inicia sesión y lo
     * lleva a la tienda (o al panel BI si fuese administrador).
     */
    public function store(StoreRegisterRequest $request): RedirectResponse
    {
        // Solo datos validados cruzan hacia la Capa de Persistencia.
        $datos = $request->validated();

        $usuario = User::create([
            'name'                => $datos['name'],
            'email'               => $datos['email'],
            // NUNCA en claro: hash irreversible antes de persistir.
            'password'            => Hash::make($datos['password']),
            // Rol seguro por defecto (anti-escalada de privilegios).
            'rol'                 => 'cliente',
            'plataforma_preferida' => $datos['plataforma_preferida'],
        ]);

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()
            ->intended($usuario->esAdministrador() ? route('bi.dashboard') : route('store.index'))
            ->with('status', "¡Bienvenido, {$usuario->name}! Tu cuenta quedó vinculada a la plataforma {$usuario->plataforma_preferida}.");
    }
}
