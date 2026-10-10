<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Requests/StoreRegisterRequest.php
 * CAPA: Capa de Presentación — Validación de Entrada / Alta de cuentas (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Validación de frontera (requisito Fase III
 * "creación de cuentas multiplataforma"):
 *
 *  1. DEFENSA EN PROFUNDIDAD: ningún dato de registro llega a la Capa de
 *     Negocio sin pasar estas reglas. El controlador solo recibe
 *     $request->validated() (datos saneados), nunca $request->all().
 *
 *  2. INTEGRIDAD DE DOMINIO: 'unique:users,email' evita duplicados ANTES de
 *     que la BD lance el error (fail-fast con mensaje en español); 'confirmed'
 *     obliga a repetir la contraseña (previene errores de tipeo irreversibles);
 *     'in:local,yauctions' cierra el dominio de la plataforma preferida.
 *
 *  3. SEGURIDAD: 'min:8' impone longitud mínima de contraseña; el hash
 *     (Hash::make) lo aplica el controlador, nunca viaja en claro a la BD.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegisterRequest extends FormRequest
{
    /**
     * El registro es público (invitados); 'guest' en rutas lo refuerza.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * REGLAS DECLARATIVAS del alta de cuenta multiplataforma.
     */
    public function rules(): array
    {
        return [
            // Nombre visible en la tienda y el panel BI.
            'name' => ['required', 'string', 'max:255'],

            // Identidad única: fail-fast antes del UNIQUE de la BD.
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],

            // Secreto: longitud mínima + confirmación (campo password_confirmation).
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // Plataforma preferida: dominio cerrado Fase III.
            'plataforma_preferida' => ['required', Rule::in(['local', 'yauctions'])],
        ];
    }

    /**
     * Mensajes en español (UX: retroalimentación comprensible).
     */
    public function messages(): array
    {
        return [
            'email.unique'      => 'Ese correo ya está registrado. Inicia sesión.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'plataforma_preferida.in' => 'La plataforma seleccionada no es válida.',
        ];
    }

    /**
     * Nombres amigables de atributos para los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'name'                => 'nombre',
            'email'               => 'correo electrónico',
            'password'            => 'contraseña',
            'plataforma_preferida' => 'plataforma preferida',
        ];
    }
}
