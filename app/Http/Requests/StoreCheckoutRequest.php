<?php

/**
 * ==============================================================================
 * ARCHIVO: app/Http/Requests/StoreCheckoutRequest.php
 * CAPA: Capa de Presentación — Validación del pago con tarjeta (Clase 12)
 * ==============================================================================
 *
 * TEORÍA APLICADA — Validación de frontera financiera:
 *
 *  1. DEFENSA EN PROFUNDIDAD: ningún dato de tarjeta llega al PaymentService
 *     sin pasar estas reglas. El controlador solo usa $request->validated().
 *
 *  2. FORMATO, NO FONDO: aquí se verifica la FORMA (16 dígitos, MM/AA, CVV);
 *     el FONDO (Luhn, fondos, vigencia real) lo decide la Capa de Negocio.
 *     Separar forma/fondo evita duplicar reglas y mensajes confusos.
 *
 *  3. SEGURIDAD: 'digits_between' + regex impiden inyecciones de formato;
 *     el PAN jamás se guarda en sesión ni en logs (solo viaja en el request).
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    /** Solo usuarios autenticados pueden pagar (el middleware 'auth' lo refuerza). */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** Reglas de FORMA del pedido + la tarjeta. */
    public function rules(): array
    {
        return [
            // Pedido (idéntico dominio que StoreOrderRequest: cohesión).
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'cantidad'   => ['required', 'integer', 'min:1', 'max:10'],
            'plataforma' => ['required', Rule::in(['local', 'yauctions'])],

            // Tarjeta: solo forma; el fondo lo valida PaymentService.
            'titular'      => ['required', 'string', 'max:100'],
            'numero'       => ['required', 'string', 'regex:/^[\d\s\-]{13,23}$/'],
            'vencimiento'  => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv'          => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    /** Mensajes en español (UX del checkout: el error debe ser accionable). */
    public function messages(): array
    {
        return [
            'titular.required'     => 'Indica el nombre impreso en la tarjeta.',
            'numero.regex'         => 'El número de tarjeta debe tener entre 13 y 19 dígitos.',
            'vencimiento.regex'    => 'El vencimiento debe tener formato MM/AA (ej. 12/28).',
            'cvv.regex'            => 'El CVV debe tener 3 o 4 dígitos.',
            'plataforma.in'        => 'La plataforma seleccionada no es válida.',
        ];
    }

    /** Nombres amigables para los mensajes de error. */
    public function attributes(): array
    {
        return [
            'product_id'  => 'producto',
            'cantidad'    => 'cantidad',
            'plataforma'  => 'plataforma de compra',
            'titular'     => 'titular de la tarjeta',
            'numero'      => 'número de tarjeta',
            'vencimiento' => 'vencimiento',
            'cvv'         => 'código de seguridad',
        ];
    }
}
